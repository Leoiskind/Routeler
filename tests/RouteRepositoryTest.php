<?php
declare(strict_types=1);

namespace App\Tests;

use App\RouteRepository;

final class RouteRepositoryTest extends DatabaseTestCase
{
    public function testCreateStoresPointsInOrder(): void
    {
        $sql = "INSERT INTO users (provider, provider_uid, username, display_name)
                VALUES ('github', '1', 'testuser', 'Test User')";

        $this->pdo()->exec($sql);

        $userId = (int) $this->pdo()->lastInsertId();

        $repo = new RouteRepository($this->pdo());

        $id = $repo->create($userId, 'Test route', 'Two points', 1200, [
            [-2.2300, 53.4700],
            [-2.2400, 53.4000]
        ]);

        $this->assertGreaterThan(0, $id);

        $route = $repo->find($id);
        $this->assertNotNull($route);

        $this->assertSame('Test route', $route['name']);
        $this->assertSame(1200, $route['distance_m']);

        $this->assertCount(2, $route['points']);
        $this->assertSame([-2.2300, 53.4700], $route['points'][0]);
        $this->assertSame([-2.2400, 53.4000], $route['points'][1]);
    }
}