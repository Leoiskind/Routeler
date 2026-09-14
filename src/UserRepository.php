<?php
declare(strict_types=1);

namespace App;

use App\Auth\ProviderUser;
use PDO;
use PDOException;
use RuntimeException;

class UserRepository{
    /** Longest a generated handle may be before its collision suffix. */
    private const USERNAME_BASE_MAX = 26;

    /** Give up rather than loop forever if something is badly wrong. */
    private const MAX_USERNAME_TRIES = 100;

    public function __construct(private PDO $pdo){}

    public function findByUsername(string $username): ?array
    {
        $sql = 'SELECT id, username, display_name, bio, location, avatar_url, created_at
                FROM users
                WHERE username = :username';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['username' => $username]);

        if (!($row = $stmt->fetch())){
            return null;
        }
        $row['id'] = (int) $row['id'];
        return $row;
    }

    public function findByProvider(string $provider, string $providerUid): ?array
    {
        $sql = 'SELECT id, username, display_name, bio, location, avatar_url, created_at
                FROM users
                WHERE provider = :provider AND provider_uid = :provider_uid';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'provider' => $provider,
            'provider_uid' => $providerUid,
            ]);

        if (!($row = $stmt->fetch())){
            return null;
        }
        $row['id'] = (int) $row['id'];
        return $row;
    }

    public function updateProfile(int $id, string $displayName, ?string $bio, ?string $location): void
    {
        $sql = 'UPDATE users
                SET display_name = :display_name,
                bio = :bio,
                location = :location
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'display_name' => $displayName,
            'bio' => $bio,
            'location' => $location,
            'id' => $id,
        ]);
    }

    /**
     * Find the account behind an OAuth identity, creating it on first sign-in.
     *
     * Returns the user id either way, so callback.php can just do:
     *
     *     $userId = $users->upsert($identity);
     *     Session::login($userId);
     *
     * The lookup is on (provider, provider_uid) — never on email. Emails
     * change hands; a provider's user id does not.
     */
    public function upsert(ProviderUser $u): int
    {
        $existing = $this->findByProvider($u->provider, $u->uid);
        if ($existing !== null) {
            return $existing['id'];
        }

        $base = $this->usernameBase($u->suggestedUsername);

        /*
         * Two attempts, because of a race that is easy to miss:
         *
         *   1. freeUsername() checks that 'leo' is unused  -> it is
         *   2. someone else's request inserts 'leo'
         *   3. our INSERT hits users_username_uq and throws
         *
         * The window is milliseconds and you will probably never see it,
         * but the correct fix is not to make the check cleverer — no
         * amount of checking closes a gap between two statements. The
         * unique key is the real guarantee; the check just picks a nice
         * name. So: let the insert fail, then pick again.
         */
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                return $this->insert($u, $this->freeUsername($base));
            } catch (PDOException $e) {
                // 23000 is SQLSTATE for "integrity constraint violation" —
                // one of this table's unique keys rejected the row.
                if ($e->getCode() !== '23000') {
                    throw $e;
                }

                // It might have been users_provider_uq rather than the
                // username: two sign-ins for the SAME person at once. In
                // that case the other request already created the account
                // and retrying would fail forever — so take their row.
                $raced = $this->findByProvider($u->provider, $u->uid);
                if ($raced !== null) {
                    return $raced['id'];
                }
            }
        }

        throw new RuntimeException("Could not create an account for {$u->provider}:{$u->uid}");
    }

    /**
     * Turn a provider handle into something users.username will accept.
     *
     * The column is CHECK (username REGEXP '^[A-Za-z0-9_]{3,30}$'), so a
     * GitHub login like "leo-morgan" or a Google local part like
     * "leo.morgan+dev" would be rejected by the database. Clean it here
     * and the constraint never fires — the constraint is the safety net,
     * not the validator.
     *
     * Lowercased deliberately: the unique key uses a case-insensitive
     * collation, so @Leo and @leo could never coexist anyway. Doing it
     * explicitly makes that a decision rather than an accident.
     */
    private function usernameBase(string $suggested): string
    {
        $base = preg_replace('/[^a-z0-9_]/', '', strtolower($suggested)) ?? '';

        // Trim before the suffix is appended, or "leo…30-characters" would
        // become 31 characters as soon as it collided.
        $base = substr($base, 0, self::USERNAME_BASE_MAX);

        // Nothing usable left — an all-emoji handle, say.
        if (strlen($base) < 3) {
            $base = 'user';
        }

        return $base;
    }

    /** The base if it is free, otherwise base2, base3, ... */
    private function freeUsername(string $base): string
    {
        if ($this->findByUsername($base) === null) {
            return $base;
        }

        for ($n = 2; $n <= self::MAX_USERNAME_TRIES; $n++) {
            $candidate = $base . $n;
            if ($this->findByUsername($candidate) === null) {
                return $candidate;
            }
        }

        // Not expected. Better a clear exception than a silent loop.
        throw new RuntimeException("No free username near '{$base}'");
    }

    /** One INSERT. No transaction needed for a single statement. */
    private function insert(ProviderUser $u, string $username): int
    {
        $sql = 'INSERT INTO users (provider, provider_uid, username, display_name, avatar_url)
                VALUES (:provider, :provider_uid, :username, :display_name, :avatar_url)';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'provider'     => $u->provider,
            'provider_uid' => $u->uid,
            'username'     => $username,
            // mb_substr, not substr: the columns are counted in characters,
            // and cutting a multi-byte character in half would corrupt it.
            'display_name' => mb_substr($u->displayName, 0, 120),
            'avatar_url'   => $u->avatarUrl !== null ? mb_substr($u->avatarUrl, 0, 512) : null,
        ]);

        // bio and location are left NULL — the user fills those in on the
        // settings page. created_at has a default.
        return (int) $this->pdo->lastInsertId();
    }
}
