<?php
declare(strict_types=1);

/**
 * Everything the sign-in flow keeps in the session.
 */
final class Session
{
    private const STATE    = 'oauth_state';
    private const VERIFIER = 'oauth_code_verifier';
    private const PROVIDER = 'oauth_provider';
    private const USER_ID  = 'user_id';

    /**
     * Begin a sign-in: remember which provider, a one-time state, and a
     * PKCE verifier. Returns [$state, $codeChallenge] for the auth URL.
     *
     * @return array{0: string, 1: string}
     */
    public static function beginLogin(string $provider): array
    {
        $state    = bin2hex(random_bytes(32));
        $verifier = self::base64Url(random_bytes(64));

        $_SESSION[self::STATE]    = $state;
        $_SESSION[self::VERIFIER] = $verifier;
        $_SESSION[self::PROVIDER] = $provider;

        // PKCE: send the hash now, the original on the token request. An
        // attacker who intercepts the code cannot redeem it without the
        // verifier, which never leaves this server.
        $challenge = self::base64Url(hash('sha256', $verifier, true));

        return [$state, $challenge];
    }

    /**
     * Check the state that came back and consume it.
     *
     * This is the CSRF defence for the callback. Without it, someone can
     * feed you a code from *their* account and log you into it — you would
     * then add routes to an account they control. The state proves the
     * callback belongs to a sign-in this browser actually started.
     *
     * One-time use: cleared whether or not it matched, so a stolen
     * callback URL cannot be replayed.
     */
    public static function consumeState(?string $sent): bool
    {
        $expected = $_SESSION[self::STATE] ?? null;
        unset($_SESSION[self::STATE]);

        return is_string($expected)
            && $expected !== ''
            && is_string($sent)
            && hash_equals($expected, $sent);
    }

    public static function codeVerifier(): ?string
    {
        $v = $_SESSION[self::VERIFIER] ?? null;
        unset($_SESSION[self::VERIFIER]);
        return is_string($v) ? $v : null;
    }

    public static function pendingProvider(): ?string
    {
        $p = $_SESSION[self::PROVIDER] ?? null;
        unset($_SESSION[self::PROVIDER]);
        return is_string($p) ? $p : null;
    }

    /**
     * Sign a user in.
     *
     * session_regenerate_id() defends against session fixation: if an
     * attacker managed to fix your session id before you signed in, that
     * id is now worthless because a fresh one is issued at the moment
     * privileges change. Always regenerate on login.
     */
    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION[self::USER_ID] = $userId;
    }

    public static function logout(): void
    {
        $_SESSION = [];

        // Also expire the cookie itself, or the browser keeps sending a
        // session id that no longer has data behind it.
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    public static function userId(): ?int
    {
        $id = $_SESSION[self::USER_ID] ?? null;
        return is_int($id) ? $id : null;
    }

    /** base64url — base64 with URL-safe characters and no padding, per RFC 7636. */
    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
