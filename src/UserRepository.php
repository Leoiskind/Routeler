<?php
declare(strict_types=1);

class UserRepository{
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
}