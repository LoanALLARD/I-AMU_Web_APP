<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use Models\EmailDomainRepository;
use Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for EmailDomainRepository against a real PostgreSQL
 * database. These cover what the EmailDomainService unit tests cannot (the
 * repository there is mocked): the actual SQL, the `domain_role_type` enum,
 * and the BOOLEAN binding in setActive() — PG rejects '' for a boolean, see
 * the repository's own comment.
 */
final class EmailDomainRepositoryTest extends IntegrationTestCase
{
    private EmailDomainRepository $repo;
    private int $addedById;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new EmailDomainRepository($this->pdo);
        $this->addedById = $this->seedSuperAdmin();
    }

    public function testFindRoleValuesReturnsEnumLabelsInDeclaredOrder(): void
    {
        // Reads the real pg_enum catalog: proves domain_role_type exists and
        // is declared as the service expects.
        self::assertSame(
            ['STUDENT', 'TEACHER', 'RESEARCHER'],
            $this->repo->findRoleValues()
        );
    }

    public function testAddThenReadRoundTrip(): void
    {
        $domain = 'it-roundtrip.example';

        $id = $this->repo->add($domain, 'STUDENT', $this->addedById);

        self::assertGreaterThan(0, $id);
        self::assertTrue($this->repo->existsByDomain($domain));
        self::assertSame('STUDENT', $this->repo->findRoleByDomain($domain));
    }

    public function testFindByIdReturnsTheStoredRow(): void
    {
        $id = $this->repo->add('it-findbyid.example', 'RESEARCHER', $this->addedById);

        $row = $this->repo->findById($id);

        self::assertNotNull($row);
        /** @var array{id:int, domain:string, role:string, is_active:bool} $row */
        self::assertSame('it-findbyid.example', $row['domain']);
        self::assertSame('RESEARCHER', $row['role']);
        self::assertTrue($row['is_active']);
    }

    public function testUpdateRolePersists(): void
    {
        $id = $this->repo->add('it-updaterole.example', 'STUDENT', $this->addedById);

        $this->repo->updateRole($id, 'TEACHER');

        self::assertSame('TEACHER', $this->repo->findRoleByDomain('it-updaterole.example'));
    }

    public function testSetActiveTogglesDomainVisibility(): void
    {
        // Regression guard for the PARAM_BOOL binding in setActive(): a plain
        // execute() would send false as '' and PG would reject it for BOOLEAN.
        $domain = 'it-disable.example';
        $id = $this->repo->add($domain, 'TEACHER', $this->addedById);

        $this->repo->setActive($id, false);
        // A disabled domain is invisible to the active-only role lookup.
        self::assertNull($this->repo->findRoleByDomain($domain));

        $this->repo->setActive($id, true);
        self::assertSame('TEACHER', $this->repo->findRoleByDomain($domain));
    }

    /**
     * Inserts a minimal super administrator — the FK target for
     * email_domain_configs.added_by_id — and returns its id. Rolled back with
     * the surrounding test transaction.
     */
    private function seedSuperAdmin(): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO super_administrators (email, password_hash)
             VALUES (:email, :hash) RETURNING id'
        );
        $stmt->execute([
            'email' => 'it-admin@example.test',
            'hash'  => 'integration-test',
        ]);

        return (int) $stmt->fetchColumn();
    }
}
