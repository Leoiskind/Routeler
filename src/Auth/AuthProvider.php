<?php
declare(strict_types=1);

namespace App\Auth;

use RuntimeException;

/**
 * One OAuth provider.
 *
 * The whole point of this interface is that callback.php never mentions
 * GitHub or Google by name. It asks the registry for a provider, calls
 * exchange(), and gets a ProviderUser back. Adding a third provider means
 * writing one class and registering it — nothing else changes.
 */
interface AuthProvider
{
    /** Machine name, stored in users.provider. */
    public function name(): string;

    /** Human name for the sign-in button. */
    public function label(): string;

    /** True when this provider has credentials configured. */
    public function isConfigured(): bool;

    /**
     * Where to send the browser to begin.
     *
     * @param string $state         one-time value we check on return
     * @param string $codeChallenge PKCE challenge derived from the verifier
     */
    public function authUrl(string $state, string $codeChallenge): string;

    /**
     * Swap the authorization code for the user's identity.
     *
     * Two server-to-server calls: code for token, token for profile. The
     * browser is not involved and never sees the client secret.
     *
     * @throws RuntimeException when the provider rejects the exchange
     */
    public function exchange(string $code, string $codeVerifier): ProviderUser;
}
