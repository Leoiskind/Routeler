<?php
declare(strict_types=1);

namespace App\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Base class for tests that need a real database.
 *
 * These are integration tests, not unit tests: they talk to MySQL. That
 * is deliberate. The repositories are almost entirely SQL, and the things
 * most likely to break — CHECK constraints, the case-insensitive unique
 * key on username, ON DELETE CASCADE, transaction rollback — cannot be
 * tested against a fake. A test double here would only assert that the
 * code calls the methods the code calls.
 *
 * The cost is a database in CI, which is what the services: block in the
 * workflow provides.
 *
 * Connection details come from the environment so the same tests run
 * against the compose database locally and the throwaway one in Actions:
 *
 *   TEST_DB_HOST  default 'db'          (127.0.0.1 in CI)
 *   TEST_DB_PORT  default '3306'
 *   TEST_DB_USER  default 'root'        needs CREATE DATABASE
 *   TEST_DB_PASS  default $DB_PASS
 *   TEST_DB_NAME  default 'routeler_test'
 */
abstract class DatabaseTestCase extends TestCase
{
    private static ?PDO $pdo = null;

    /** @var list<string> Truncated before each test, children before parents. */
    private const TABLES = ['ratings', 'follows', 'route_points', 'routes', 'users'];

    protected function pdo(): PDO
    {
        return self::$pdo ??= self::connectAndLoadSchema();
    }

    /**
     * Every test starts from empty tables.
     *
     * Rebuilding the schema per test would be correct but slow; emptying
     * it is enough, and it keeps tests independent of each other's data.
     * Test order is not guaranteed, so a test that relies on a row another
     * test created is a test that will fail eventually, on someone else's
     * machine, for no visible reason.
     */
    protected function setUp(): void
    {
        $pdo = $this->pdo();

        // TRUNCATE is refused on a table another table references, so the
        // constraint check goes off for the duration.
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABLES as $table) {
            $pdo->exec("TRUNCATE TABLE {$table}");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    private static function connectAndLoadSchema(): PDO
    {
        $host = getenv('TEST_DB_HOST') ?: 'db';
        $port = getenv('TEST_DB_PORT') ?: '3306';
        $user = getenv('TEST_DB_USER') ?: 'root';
        $pass = getenv('TEST_DB_PASS') ?: (getenv('DB_PASS') ?: '');
        $name = getenv('TEST_DB_NAME') ?: 'routeler_test';

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        // Connect to the server rather than to a database, because the
        // next thing we do is drop and recreate that database.
        $server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, $options);

        $server->exec("DROP DATABASE IF EXISTS `{$name}`");
        $server->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");

        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, $options);

        foreach (self::schemaStatements() as $statement) {
            $pdo->exec($statement);
        }

        return $pdo;
    }

    /**
     * The production schema, split into statements.
     *
     * Tests run against docs/schema.mysql.sql itself rather than a copy.
     * A separate test schema drifts, and then the tests pass against a
     * database that no longer resembles the real one.
     *
     * @return list<string>
     */
    private static function schemaStatements(): array
    {
        $path = __DIR__ . '/../docs/schema.mysql.sql';
        $sql  = file_get_contents($path);

        if ($sql === false) {
            throw new RuntimeException("Could not read {$path}");
        }

        // Comments are stripped first: one of them ends in
        // 'BOOLEAN is TINYINT(1); PDO returns "1"/"0"', and that semicolon
        // would otherwise split a CREATE TABLE in half.
        $sql = preg_replace('/--[^\n]*/', '', $sql) ?? '';

        return array_values(array_filter(
            array_map(trim(...), explode(';', $sql)),
            static fn (string $s): bool => $s !== '',
        ));
    }
}
