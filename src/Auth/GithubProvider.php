<?php
declare(strict_types=1);

namespace App\Auth;

use RuntimeException;

final class GithubProvider implements AuthProvider
{
    private const AUTHORIZE = 'https://github.com/login/oauth/authorize';
    private const TOKEN     = 'https://github.com/login/oauth/access_token';
    private const USER      = 'https://api.github.com/user';
    private const EMAILS    = 'https://api.github.com/user/emails';

    public function name(): string  { return 'github'; }
    public function label(): string { return 'GitHub'; }

    private function clientId(): string     { return (string) (getenv('GITHUB_CLIENT_ID') ?: ''); }
    private function clientSecret(): string { return (string) (getenv('GITHUB_CLIENT_SECRET') ?: ''); }
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
            // read:user for the profile, user:email because GitHub hides
            // the address from the profile endpoint when it is private.
            'scope'         => 'read:user user:email',
            'state'         => $state,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function exchange(string $code, string $codeVerifier): ProviderUser
    {
        // Step 1: code -> access token. Server to server; the client
        // secret never touches the browser.
        $token = Http::postForm(self::TOKEN, [
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri(),
            'code_verifier' => $codeVerifier,
        ]);

        // GitHub reports failure with HTTP 200 and an "error" key, so the
        // status code alone is not enough to tell whether this worked.
        if (isset($token['error'])) {
            throw new RuntimeException('GitHub rejected the code: ' . (string) $token['error']);
        }

        $accessToken = $token['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('GitHub returned no access token');
        }

        $auth = ['Authorization: Bearer ' . $accessToken];

        // Step 2: token -> profile.
        $user = Http::getJson(self::USER, $auth);

        $uid = $user['id'] ?? null;
        if ($uid === null) {
            throw new RuntimeException('GitHub returned no user id');
        }

        $login = (string) ($user['login'] ?? '');

        return new ProviderUser(
            provider:          $this->name(),
            uid:               (string) $uid,
            // "name" is null for anyone who never filled it in, so fall
            // back to the login. display_name is NOT NULL in the schema.
            displayName:       trim((string) ($user['name'] ?? '')) ?: $login,
            suggestedUsername: $login,
            email:             $this->primaryEmail($user, $auth),
            avatarUrl:         $user['avatar_url'] ?? null,
        );
    }

    /**
     * The profile endpoint returns null for email when the user keeps it
     * private, so fall back to the dedicated endpoint and take the
     * verified primary. Best effort — email is optional here.
     */
    private function primaryEmail(array $user, array $auth): ?string
    {
        if (!empty($user['email'])) {
            return (string) $user['email'];
        }

        try {
            foreach (Http::getJson(self::EMAILS, $auth) as $entry) {
                if (($entry['primary'] ?? false) && ($entry['verified'] ?? false)) {
                    return (string) $entry['email'];
                }
            }
        } catch (RuntimeException) {
            // Scope not granted, or the call failed. Not worth failing login over.
        }

        return null;
    }
}
