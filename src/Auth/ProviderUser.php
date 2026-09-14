<?php
declare(strict_types=1);

namespace App\Auth;

/**
 * What every provider boils down to.
 *
 * GitHub and Google return quite different JSON. Each provider converts
 * its own response into one of these before anything else sees it, so no
 * other code has to know or care which provider was used. Normalising at
 * the boundary is what keeps the rest of the app provider-agnostic.
 *
 * readonly: once built it cannot be modified. An identity that arrived
 * from an external service should not be quietly editable afterwards.
 */
final class ProviderUser
{
    public function __construct(
        /** 'github' | 'google' — matches users.provider */
        public readonly string $provider,

        /** Their stable id at that provider — matches users.provider_uid.
         *  A number for GitHub, an opaque string for Google. Always stored
         *  as a string: it is an identifier, never something to do maths on. */
        public readonly string $uid,

        /** Best available human name. Never empty — providers fall back to
         *  the login handle, because users.display_name is NOT NULL. */
        public readonly string $displayName,

        /** A starting point for the @handle. Not guaranteed unique — the
         *  repository resolves collisions. */
        public readonly string $suggestedUsername,

        public readonly ?string $email = null,
        public readonly ?string $avatarUrl = null,
    ) {
    }
}
