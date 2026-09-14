<?php
declare(strict_types=1);

require_once __DIR__ . '/ProviderUser.php';
require_once __DIR__ . '/AuthProvider.php';
require_once __DIR__ . '/Http.php';
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/GithubProvider.php';
require_once __DIR__ . '/GoogleProvider.php';

/**
 * Every provider the app knows about, keyed by name.
 *
 * @return array<string, AuthProvider>
 */
function auth_providers(): array
{
    static $providers = null;

    if ($providers === null) {
        $providers = [];
        foreach ([new GithubProvider(), new GoogleProvider()] as $p) {
            $providers[$p->name()] = $p;
        }
    }

    return $providers;
}

/** Only those with credentials set — what the sign-in page should offer. */
function auth_providers_configured(): array
{
    return array_filter(auth_providers(), static fn(AuthProvider $p) => $p->isConfigured());
}

function auth_provider(string $name): ?AuthProvider
{
    return auth_providers()[$name] ?? null;
}
