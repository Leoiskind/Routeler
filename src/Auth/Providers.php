<?php
declare(strict_types=1);

namespace App\Auth;

/**
 * The registry of sign-in providers.
 *
 * Static rather than injected because there is exactly one set of
 * providers for the lifetime of a request and every caller wants the
 * same one. If that stops being true this becomes a real object built
 * once in bootstrap.
 */
final class Providers
{
    /** @var array<string, AuthProvider>|null */
    private static ?array $providers = null;

    /**
     * Every provider the app knows about, keyed by name.
     *
     * @return array<string, AuthProvider>
     */
    public static function all(): array
    {
        if (self::$providers === null) {
            self::$providers = [];
            foreach ([new GithubProvider(), new GoogleProvider()] as $p) {
                self::$providers[$p->name()] = $p;
            }
        }

        return self::$providers;
    }

    /**
     * Only those with credentials set — what the sign-in page should offer.
     *
     * @return array<string, AuthProvider>
     */
    public static function configured(): array
    {
        return array_filter(self::all(), static fn (AuthProvider $p) => $p->isConfigured());
    }

    public static function get(string $name): ?AuthProvider
    {
        return self::all()[$name] ?? null;
    }
}
