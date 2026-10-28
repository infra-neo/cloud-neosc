<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Mail\EmailVerificationMail;
use App\Models\BannedEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * The "confirm your email" step that closes the sign-up wizard.
 *
 * Verification is informational: it does not gate ordering, because checkout
 * opens accounts in-line and a visitor mid-purchase must not be stopped by an
 * inbox round trip. The page always offers a way forward.
 */
class EmailVerificationController extends Controller
{
    public function notice(Request $request)
    {
        $user = $request->user();
        if ($user->email_verified_at) {
            return redirect()->route('client.home');
        }

        return view('client.auth.verify-email', ['user' => $user]);
    }

    /** Signed link from the mail; works from any browser, logged in or not. */
    public function verify(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->email), $hash)) {
            abort(403);
        }

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $target = Auth::check() ? 'client.home' : 'client.login';

        return redirect()->route($target)->with('success', __('client.auth.verify_done'));
    }

    public function resend(Request $request)
    {
        $user = $request->user();
        if ($user->email_verified_at) {
            return redirect()->route('client.home');
        }

        self::send($user);

        return back()->with('success', __('client.auth.verify_resent'));
    }

    /** "Wrong address? Change it" - only while the address is unconfirmed. */
    public function changeEmail(Request $request)
    {
        $user = $request->user();
        if ($user->email_verified_at) {
            return redirect()->route('client.home');
        }

        $validated = $request->validate([
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
        ]);

        if (BannedEmail::blocks($validated['email'])) {
            return back()->withErrors(['email' => __('auth.email_not_accepted')])->onlyInput('email');
        }

        $old = $user->email;
        $user->update(['email' => $validated['email']]);
        // The client record that was opened with the old address follows it.
        foreach ($user->clients()->wherePivot('owner', true)->get() as $client) {
            if ($client->email === $old) {
                $client->update(['email' => $validated['email']]);
            }
        }

        self::send($user->fresh());

        return back()->with('success', __('client.auth.verify_resent'));
    }

    public static function url(User $user): string
    {
        return URL::temporarySignedRoute(
            'client.verification.verify',
            now()->addHours(24),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );
    }

    /** Best effort: a mail outage must never turn a sign-up into an error page. */
    public static function send(User $user): void
    {
        try {
            Mail::to($user->email)->send(new EmailVerificationMail(self::url($user), $user->email));
        } catch (\Throwable $e) {
            Log::warning('Email verification mail failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
