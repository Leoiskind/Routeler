<?php
declare(strict_types=1);

namespace App\Auth;

use RuntimeException;

/**
 * Google, via OpenID Connect.
 *
 * Written now so the interface has two implementations and you can see
 * that only this file knows anything Google-specific. It stays inactive
 * until GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET are set, so the sign-in
 * page simply won't offer it.
 */
final class GoogleProvider implements AuthProvider
{
    private const AUTHORIZE = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN     = 'https://oauth2.googleapis.com/token';
    private const USERINFO  = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function name(): string  { return 'google'; }
    public function label(): string { return 'Google'; }

    private function clientId(): string     { return (string) (getenv('GOOGLE_CLIENT_ID') ?: ''); }
    private function clientSecret(): string { return (string) (getenv('GOOGLE_CLIENT_SECRET') ?: ''); }
    private function redirectUri(): string  { return (string) (getenv('OAUTH_REDIRECT_URI') ?: ''); }

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function authUrl(string $state, string $codeChallenge): string
    {
        return self::AUTHORIZE . '?' . http_build_query([
            'client_id'     => $this->clientId(),
            'redirect_uri'  => $this->redirectUri(),
            // Google requires response_type explicitly; GitHub assumes it.
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function exchange(string $code, string $codeVerifier): ProviderUser
    {
        $token = Http::postForm(self::TOKEN, [
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri(),
            'grant_type'    => 'authorization_code',   // Google requires this, GitHub does not
            'code_verifier' => $codeVerifier,
        ]);

        $accessToken = $token['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Google returned no access token');
        }

        $user = Http::getJson(self::USERINFO, ['Authorization: Bearer ' . $accessToken]);

        // "sub" (subject) is OpenID Connect's stable user id. Note it is
        // only unique within Google — which is exactly why the schema keys
        // on (provider, provider_uid) rather than the uid alone.
        $sub = $user['sub'] ?? null;
        if ($sub === null) {
            throw new RuntimeException('Google returned no subject id');
        }

        $email = isset($user['email']) ? (string) $user['email'] : null;
        $local = $email !== null ? strstr($email, '@', true) : false;

        return new ProviderUser(
            provider:          $this->name(),
            uid:               (string) $sub,
            displayName:       trim((string) ($user['name'] ?? '')) ?: (string) ($local ?: 'user'),
            suggestedUsername: (string) ($local ?: 'user'),
            email:             $email,
            avatarUrl:         $user['picture'] ?? null,
        );
    }
}
