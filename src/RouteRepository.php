<?php
declare(strict_types=1);

class RouteRepository{
    public function __construct(private PDO $pdo){}

    public function create(int $userId, string $name, ?string $description,
                        ?int $distanceM, array $points): int{
        $this->pdo->beginTransaction();

        try{
            $insertRoute = $this->pdo->prepare(       // ← ADD: the route insert
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

    public function listAll(): array{
        $grouped = [];
        $sql = "SELECT r.id, r.name, r.description, r.distance_m, r.created_at, u.username AS author,
                p.longitude, p.latitude
                FROM routes r
                JOIN users u ON u.id = r.user_id
                LEFT JOIN route_points p ON p.route_id = r.id
                WHERE r.is_public = 1
                ORDER BY r.created_at DESC, r.id, p.position";

        $stmt = $this->pdo->query($sql);

        $id=-1;
        foreach ($stmt as $row){
            if ($row['id'] != $id){
                $grouped[$row['id']] = [
                    'id'          => (int) $row['id'],
                    'name'        => $row['name'],
                    'description' => $row['description'],
                    'distance_m'  => $row['distance_m'] !== null ? (int) $row['distance_m'] : null,
                    'author'      => $row['author'],
                    'points'      => [],
                ];
                $id = $row['id'];
            }
            if ($row['longitude'] !== null){
                $grouped[$row['id']]['points'][] = array($row['longitude'], $row['latitude']);
            }
        }

        return array_values($grouped);
    }
}