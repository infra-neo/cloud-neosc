<?php

namespace App\Http\Controllers\Client;

use App\Enums\ClientStatus;
use App\Http\Controllers\Controller;
use App\Models\AuthAccountLink;
use App\Models\BannedEmail;
use App\Models\Setting;
use App\Models\User;
use App\Services\ClientRegistrationService;
use App\Services\LoginRecorder;
use App\Services\OidcLoginClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Signing in with the operator's own identity provider (Zitadel), the door the
 * company's staff already use for the connection portal, so one sign-in opens
 * both.
 *
 * The same rules as the Google and GitHub doors:
 *
 * - off until an operator turns it on and gives the provider's address and the
 *   client the provider made for this application;
 * - a login is recognised by the provider's subject id (auth_account_links),
 *   never by the email alone;
 * - an existing account is linked by address only when the provider says it has
 *   verified that address;
 * - a closed account stays closed, a banned address is not opened, and the
 *   second factor is still asked for.
 *
 * Unlike the public doors, a person with no account here is turned away unless
 * the operator has also allowed new accounts: the provider's users are the
 * company's own, and accounts are normally made by the sign-up form, which
 * creates the matching user at the provider.
 */
class ZitadelLoginController extends Controller
{
    public const PROVIDER = 'zitadel';

    private const SESSION = 'oidc_zitadel';

    public static function enabled(): bool
    {
        return (string) Setting::get('ZitadelLoginEnabled', '0') === '1'
            && preg_match('#^https://[^\s/]+#i', trim((string) Setting::get('ZitadelIssuer', ''))) === 1
            && trim((string) Setting::get('ZitadelClientId', '')) !== '';
    }

    public function redirect(Request $request)
    {
        abort_unless(self::enabled(), 404);

        $flow = $this->client()->authorization();
        $request->session()->put(self::SESSION, $flow['state']);

        return redirect()->away($flow['url']);
    }

    public function callback(Request $request)
    {
        abort_unless(self::enabled(), 404);

        $kept = $request->session()->pull(self::SESSION);
        if (! is_array($kept) || $request->filled('error') || ! $request->filled('code')) {
            if ($request->filled('error')) {
                Log::info('Zitadel sign-in not completed: '.$request->query('error'));
            }

            return redirect()->route('client.login')->with('error', __('auth.social_failed_zitadel'));
        }

        try {
            $who = $this->client()->user((string) $request->query('code'), (string) $request->query('state'), $kept);
        } catch (\Throwable $e) {
            Log::warning('Zitadel sign-in failed: '.$e->getMessage());

            return redirect()->route('client.login')->with('error', __('auth.social_failed_zitadel'));
        }

        $link = AuthAccountLink::where('provider', self::PROVIDER)->where('provider_user_id', $who['sub'])->first();
        $user = $link?->user;

        if (! $user) {
            if ($who['email'] === '' || ! $who['email_verified']) {
                Log::warning('Zitadel sign-in refused: address missing or not verified', ['sub' => $who['sub']]);

                return redirect()->route('client.login')->with('error', __('auth.zitadel_no_verified_email'));
            }

            $user = User::where('email', $who['email'])->first();

            if (! $user) {
                if ((string) Setting::get('ZitadelAutoRegister', '0') !== '1') {
                    return redirect()->route('client.login')->with('error', __('auth.zitadel_no_account'));
                }
                if (BannedEmail::blocks($who['email'])) {
                    return redirect()->route('client.login')->with('error', __('auth.email_not_accepted'));
                }

                [$user] = app(ClientRegistrationService::class)->register([
                    'first_name' => $who['given_name'] ?: (explode(' ', $who['name'])[0] ?: (strstr($who['email'], '@', true) ?: $who['email'])),
                    'last_name' => $who['family_name'] ?: (trim(implode(' ', array_slice(explode(' ', $who['name']), 1))) ?: '-'),
                    'email' => $who['email'],
                ], $request);
                $user->markEmailAsVerified();
                $this->link($user, $who);

                Auth::login($user);
                $request->session()->regenerate();
                app(LoginRecorder::class)->succeeded($user, $request, self::PROVIDER);

                return redirect()->route('client.account.profile')->with('success', __('auth.social_complete_profile'));
            }

            // Same person, already a customer here, and the provider verified
            // the address: link, and settle any verification still outstanding.
            $this->link($user, $who);
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
        }

        if ($user->clients()->exists() && ! $user->clients()->where('status', '!=', ClientStatus::Closed->value)->exists()) {
            return redirect()->route('client.login')->with('error', __('auth.account_closed'));
        }

        Auth::login($user);
        $request->session()->regenerate();

        if ($user->second_factor_type && $user->second_factor_secret) {
            session(['login_method' => self::PROVIDER]);
        } else {
            app(LoginRecorder::class)->succeeded($user, $request, self::PROVIDER);
        }

        return redirect()->intended(route('client.home'));
    }

    private function link(User $user, array $who): void
    {
        AuthAccountLink::updateOrCreate(
            ['provider' => self::PROVIDER, 'provider_user_id' => $who['sub']],
            ['user_id' => $user->id, 'data' => ['email' => $who['email']]],
        );
    }

    private function client(): OidcLoginClient
    {
        return new OidcLoginClient(
            trim((string) Setting::get('ZitadelIssuer', '')),
            trim((string) Setting::get('ZitadelClientId', '')),
            trim((string) Setting::get('ZitadelClientSecret', '')),
            route('client.social.zitadel.callback'),
        );
    }
}
