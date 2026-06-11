<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use Models\SuperAdministratorRepository;
use Tests\Integration\IntegrationTestCase;

/**
 * @covers \Models\SuperAdministratorRepository
 */
final class SuperAdministratorRepositoryTest extends IntegrationTestCase
{
    private SuperAdministratorRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SuperAdministratorRepository($this->pdo);
    }

    public function testCreateReturnsIdAndRowIsFindable(): void
    {
        $id = $this->repo->create('root@univ-amu.fr', 'hash', 'Root', 'Admin');

        self::assertGreaterThan(0, $id);

        $byId = $this->repo->findById($id);
        self::assertNotNull($byId);
        self::assertSame('root@univ-amu.fr', $byId['email']);
        // findById must NOT leak the password hash (it's not in the projection).
        self::assertArrayNotHasKey('password_hash', $byId);
    }

    public function testFindByEmailReturnsHashForAuthButNullWhenAbsent(): void
    {
        $this->repo->create('a@univ-amu.fr', 'the-hash', 'A', 'B');

        $row = $this->repo->findByEmail('a@univ-amu.fr');
        self::assertNotNull($row);
        self::assertSame('the-hash', $row['password_hash']);

        self::assertNull($this->repo->findByEmail('missing@univ-amu.fr'));
    }

    public function testEmailExistsReflectsPresence(): void
    {
        $this->repo->create('here@univ-amu.fr', 'h', 'A', 'B');

        self::assertTrue($this->repo->emailExists('here@univ-amu.fr'));
        self::assertFalse($this->repo->emailExists('elsewhere@univ-amu.fr'));
    }

    public function testCountReflectsNumberOfAccounts(): void
    {
        // This count is what the run-once bootstrap relies on to refuse a
        // second super admin — so the off-by-nothing matters.
        self::assertSame(0, $this->repo->count());

        $this->repo->create('one@univ-amu.fr', 'h', 'A', 'B');
        self::assertSame(1, $this->repo->count());

        $this->repo->create('two@univ-amu.fr', 'h', 'C', 'D');
        self::assertSame(2, $this->repo->count());
    }

    public function testTouchLastLoginStampsTimestamp(): void
    {
        $id = $this->repo->create('login@univ-amu.fr', 'h', 'A', 'B');

        $before = $this->pdo->query("SELECT last_login_at FROM super_administrators WHERE id = $id")->fetchColumn();
        self::assertNull($before);

        $this->repo->touchLastLogin($id);

        $after = $this->pdo->query("SELECT last_login_at FROM super_administrators WHERE id = $id")->fetchColumn();
        self::assertNotNull($after);
    }

    public function testUpdatePasswordStoresVerifiableHash(): void
    {
        $id = $this->repo->create('pw@univ-amu.fr', 'old', 'A', 'B');

        self::assertTrue($this->repo->updatePassword($id, 'brand-new'));

        $hash = $this->repo->findByEmail('pw@univ-amu.fr')['password_hash'];
        self::assertTrue(password_verify('brand-new', $hash));
    }

    public function testUpdateEmailAndNamesPersist(): void
    {
        $id = $this->repo->create('orig@univ-amu.fr', 'h', 'Orig', 'Name');

        self::assertTrue($this->repo->updateEmail($id, 'changed@univ-amu.fr'));
        self::assertTrue($this->repo->updateFirstName($id, 'Changed'));
        self::assertTrue($this->repo->updateLastName($id, 'Person'));

        $row = $this->repo->findByEmail('changed@univ-amu.fr');
        self::assertNotNull($row);
        self::assertSame('Changed', $row['first_name']);
        self::assertSame('Person', $row['last_name']);
    }
}