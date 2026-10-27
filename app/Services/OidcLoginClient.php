<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The customer's half of an OpenID Connect sign-in (authorization code with
 * PKCE), for an identity provider the operator runs, such as Zitadel.
 *
 * Socialite has no generic OIDC driver, and a driver per provider would be a
 * package to install on every deployment, so this does the three steps itself:
 * build the address to send the customer to, trade the code that comes back for
 * tokens, and read who signed in.
 *
 * The endpoints come from the provider's discovery document
 * (/.well-known/openid-configuration), cached for an hour, with the usual
 * Zitadel paths as the fallback when it cannot be read.
 *
 * The ID token arrives straight from the token endpoint over TLS, which is the
 * case where OpenID Connect Core 3.1.3.7 lets a client skip checking its
 * signature. Everything else about it is checked: issuer, audience, expiry and
 * the nonce. The person's details are then read from the userinfo endpoint with
 * the access token.
 */
class OidcLoginClient
{
    public function __construct(
        private readonly string $issuer,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
        private readonly string $scopes = 'openid profile email',
    ) {}

    /**
     * Where to send the customer, and what to keep in the session to finish.
     *
     * @return array{url: string, state: array{state: string, nonce: string, verifier: string}}
     */
    public function authorization(): array
    {
        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = Str::random(96);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $url = $this->endpoint('authorization_endpoint', '/oauth/v2/authorize').'?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => $this->scopes,
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return ['url' => $url, 'state' => ['state' => $state, 'nonce' => $nonce, 'verifier' => $verifier]];
    }

    /**
     * Finish the sign-in: the claims of the person who signed in.
     *
     * @param  array{state: string, nonce: string, verifier: string}  $kept  what authorization() returned
     * @return array{sub: string, email: string, email_verified: bool, name: string, given_name: string, family_name: string}
     *
     * @throws RuntimeException when anything about the answer cannot be trusted
     */
    public function user(string $code, string $returnedState, array $kept): array
    {
        if ($returnedState === '' || ! hash_equals($kept['state'], $returnedState)) {
            throw new RuntimeException('state does not match');
        }

        $form = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->clientId,
            'code_verifier' => $kept['verifier'],
        ];
        $request = Http::asForm()->acceptJson()->timeout(15);
        if ($this->clientSecret !== '') {
            // client_secret_basic; without a secret the client is a public one and PKCE alone proves it.
            $request = $request->withBasicAuth(rawurlencode($this->clientId), rawurlencode($this->clientSecret));
        }

        try {
            $response = $request->post($this->endpoint('token_endpoint', '/oauth/v2/token'), $form);
        } catch (ConnectionException $e) {
            throw new RuntimeException('token endpoint unreachable');
        }
        if (! $response->successful() || ! is_string($response->json('access_token')) || ! is_string($response->json('id_token'))) {
            throw new RuntimeException('token endpoint refused the code: '.$response->status());
        }

        $claims = $this->idTokenClaims((string) $response->json('id_token'), $kept['nonce']);

        $info = [];
        try {
            $userinfo = Http::withToken((string) $response->json('access_token'))->acceptJson()->timeout(15)
                ->get($this->endpoint('userinfo_endpoint', '/oidc/v1/userinfo'));
            $info = $userinfo->successful() ? (array) $userinfo->json() : [];
        } catch (ConnectionException $e) {
            // The ID token alone may be enough; checked below.
        }

        // A userinfo answer for another subject is never about this person.
        if (isset($info['sub']) && (string) $info['sub'] !== (string) $claims['sub']) {
            throw new RuntimeException('userinfo is about another subject');
        }
        $merged = $info + $claims;

        return [
            'sub' => (string) $claims['sub'],
            'email' => strtolower(trim((string) ($merged['email'] ?? ''))),
            'email_verified' => filter_var($merged['email_verified'] ?? false, FILTER_VALIDATE_BOOL),
            'name' => trim((string) ($merged['name'] ?? '')),
            'given_name' => trim((string) ($merged['given_name'] ?? '')),
            'family_name' => trim((string) ($merged['family_name'] ?? '')),
        ];
    }

    /** The claims of an ID token the provider just handed over, after the checks that apply. */
    private function idTokenClaims(string $jwt, string $nonce): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new RuntimeException('id_token is not a JWT');
        }
        $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (! is_array($claims) || ! isset($claims['sub']) || (string) $claims['sub'] === '') {
            throw new RuntimeException('id_token has no subject');
        }

        if (rtrim((string) ($claims['iss'] ?? ''), '/') !== rtrim($this->issuer, '/')) {
            throw new RuntimeException('id_token issuer is not ours to trust');
        }
        if (! in_array($this->clientId, (array) ($claims['aud'] ?? []), true)) {
            throw new RuntimeException('id_token is for another client');
        }
        // A minute of clock difference is allowed between the two servers.
        if (! isset($claims['exp']) || (int) $claims['exp'] + 60 < time()) {
            throw new RuntimeException('id_token has expired');
        }
        if (! isset($claims['nonce']) || ! hash_equals($nonce, (string) $claims['nonce'])) {
            throw new RuntimeException('id_token nonce does not match');
        }

        return $claims;
    }

    private function endpoint(string $name, string $fallbackPath): string
    {
        $issuer = rtrim($this->issuer, '/');
        $key = 'oidc.discovery.'.sha1($issuer);
        $doc = Cache::get($key);
        if (! is_array($doc)) {
            $doc = [];
            try {
                $r = Http::acceptJson()->timeout(8)->get($issuer.'/.well-known/openid-configuration');
                $doc = $r->successful() && is_array($r->json()) ? $r->json() : [];
            } catch (\Throwable $e) {
                // The usual paths are tried below.
            }
            // Only a document that was read is kept; a failure is retried next time.
            if ($doc !== []) {
                Cache::put($key, $doc, 3600);
            }
        }

        $found = (string) ($doc[$name] ?? '');
        // Only an address under the issuer's own host is followed: a discovery
        // document that points elsewhere is not one to send a code to.
        if ($found !== '' && parse_url($found, PHP_URL_HOST) === parse_url($issuer, PHP_URL_HOST) && str_starts_with($found, 'https://')) {
            return $found;
        }

        return $issuer.$fallbackPath;
    }
}
