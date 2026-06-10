<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Base class for integration tests that hit a REAL PostgreSQL database.
 *
 * Unlike the unit suite (which mocks repositories and stubs PDO), these
 * tests exercise the actual SQL: enum types, constraints, triggers and the
 * BOOLEAN binding in setActive() — things a mock can never catch.
 *
 * The connection uses the same DB_* environment variables the app reads
 * (see Config/config.php). When no database is reachable the test is
 * SKIPPED, never failed, so `vendor/bin/phpunit` still runs green for a
 * developer who only has the unit suite set up. CI provides a Postgres
 * service (see .github/workflows/ci.yml) so the suite actually runs there.
 *
 * To run locally, point DB_* at a throwaway database that has the schema
 * loaded (database/schema/01_schema.sql + 02_triggers.sql) — e.g. from
 * inside the Docker stack, where DB_* already resolve to the `db` service:
 *
 *     docker compose exec app vendor/bin/phpunit --testsuite integration
 *
 * Each test runs inside a transaction that is rolled back in tearDown(), so
 * tests are isolated and leave no trace, even against a populated database.
 */
abstract class IntegrationTestCase extends TestCase
{
    private static ?PDO $sharedPdo = null;
    private static bool $connectionAttempted = false;

    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = self::connectOrSkip();
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /**
     * Opens a single connection for the whole process (reused across tests).
     * On any failure the calling test is skipped, not failed — the database
     * is optional for a local unit-only run.
     */
    private static function connectOrSkip(): PDO
    {
        if (self::$sharedPdo instanceof PDO) {
            return self::$sharedPdo;
        }

        // Remember a failed attempt so a missing DB skips fast instead of
        // re-attempting a (slow) connection for every test in the suite.
        if (self::$connectionAttempted) {
            self::markTestSkipped('Integration database unavailable (DB_* not reachable).');
        }
        self::$connectionAttempted = true;

        $host = getenv('DB_HOST') ?: 'db';
        $port = getenv('DB_PORT') ?: '5432';
        $name = getenv('DB_NAME') ?: 'iamu';
        $user = getenv('DB_USER') ?: 'iamu_user';
        $pass = getenv('DB_PASSWORD') ?: '';

        // connect_timeout (the pgsql driver's real knob) keeps a missing
        // server from hanging the suite; it fails fast into markTestSkipped.
        $dsn = "pgsql:host={$host};port={$port};dbname={$name};connect_timeout=3";

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 3,
            ]);
        } catch (PDOException $e) {
            self::markTestSkipped('Integration database unavailable: ' . $e->getMessage());
        }

        self::$sharedPdo = $pdo;

        return $pdo;
    }
}
