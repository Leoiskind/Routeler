<?php
declare(strict_types=1);

class RouteRepository{
    public function __construct(private PDO $pdo){}

    public function create(int $userId, string $name, ?string $description,
                        ?int $distanceM, array $points): int{
        $this->pdo->beginTransaction();

        try{
            $insertRoute = $this->pdo->prepare(
                'INSERT INTO routes (user_id, name, description, distance_m)
                VALUES (:user_id, :name, :description, :distance_m)'
            );

            $insertRoute->execute([
                'user_id'     => $userId,
                'name'        => $name,
                'description' => $description,
                'distance_m'  => $distanceM,
            ]);

            $insertPoint = $this->pdo->prepare(
                'INSERT INTO route_points (route_id, position, latitude, longitude)
                VALUES (:route_id, :position, :latitude, :longitude)'
            );

            $id = (int)$this->pdo->lastInsertId();
            foreach ($points as $i => $point){
                $lng = $point[0];
                $lat = $point[1];
                $insertPoint->execute([
                    'route_id'=> $id,
                    'position'=> $i,
                    'latitude'=> $lat,
                    'longitude'=> $lng
                ]);
            }

            $this->pdo->commit();
            return $id;

        } catch (Throwable $e){
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** The columns every route query returns. Kept in one place so the
     *  shape groupRows() expects can't drift between queries. */
    private const SELECT_COLUMNS = '
        r.id, r.name, r.description, r.distance_m, r.created_at,
        u.username AS author,
        p.longitude, p.latitude';

    /**
     * Every public route, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAll(): array{
        $sql = 'SELECT ' . self::SELECT_COLUMNS . '
                FROM routes r
                JOIN users u ON u.id = r.user_id
                LEFT JOIN route_points p ON p.route_id = r.id
                WHERE r.is_public = 1
                ORDER BY r.created_at DESC, r.id, p.position';

        return array_values($this->groupRows($this->pdo->query($sql)));
    }

    /**
     * One route by id, or null if there is no such route.
     *
     * Note this does NOT filter on is_public — a route page needs to be
     * reachable by its owner even when private. Once Phase 5 adds auth,
     * route.php should check ownership before rendering a private route.
     */
    public function find(int $id): ?array{
        $sql = 'SELECT ' . self::SELECT_COLUMNS . '
                FROM routes r
                JOIN users u ON u.id = r.user_id
                LEFT JOIN route_points p ON p.route_id = r.id
                WHERE r.id = :id
                ORDER BY p.position';

        // A value from the URL, so: placeholder, never interpolation.
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $grouped = $this->groupRows($stmt);

        // At most one route, but the key is its id rather than 0.
        return array_values($grouped)[0] ?? null;
    }

    /**
     * Collapse the flat join result into one entry per route.
     *
     * A join repeats the route columns once per point, so this walks the
     * rows and builds the nested shape the views expect. Shared by every
     * query above — the SQL differs, the shape does not.
     *
     * Returned keyed by route id; callers use array_values() to get a list.
     *
     * @param iterable<array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function groupRows(iterable $rows): array{
        $grouped = [];

        foreach ($rows as $row){
            $id = (int) $row['id'];

            if (!isset($grouped[$id])){
                $grouped[$id] = [
                    'id'          => $id,
                    'name'        => $row['name'],
                    'description' => $row['description'],
                    'distance_m'  => $row['distance_m'] !== null ? (int) $row['distance_m'] : null,
                    'author'      => $row['author'],
                    'created_at'  => $row['created_at'],
                    'points'      => [],
                ];
            }

            // LEFT JOIN: a route with no points yields one row of nulls.
            if ($row['longitude'] !== null){
                $grouped[$id]['points'][] = [
                    (float) $row['longitude'],
                    (float) $row['latitude'],
                ];
            }
        }

        return $grouped;
    }

    public function listByAuthor(string $username): array
    {
       $sql = 'SELECT ' . self::SELECT_COLUMNS . '
                FROM routes r
                JOIN users u ON u.id = r.user_id
                LEFT JOIN route_points p ON p.route_id = r.id
                WHERE u.username = :username
                ORDER BY r.created_at DESC, r.id, p.position';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['username' => $username]);

        return array_values($this->groupRows($stmt));
    }

}
