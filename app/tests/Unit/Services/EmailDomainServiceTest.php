<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Models\EmailDomainRepository;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Services\EmailDomainService;

/**
 * Unit tests for EmailDomainService. The repository is mocked, so no DB:
 * these tests cover the service's own logic (validation, normalisation,
 * enum checks, delegation) — not the SQL.
 */
final class EmailDomainServiceTest extends TestCase
{
    private const ROLES = ['STUDENT', 'TEACHER', 'RESEARCHER'];

    /** @var EmailDomainRepository&MockObject */
    private EmailDomainRepository $repo;

    private EmailDomainService $service;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(EmailDomainRepository::class);
        // PDO is never used once a repository is injected; a stub avoids connecting.
        $this->service = new EmailDomainService(
            $this->createStub(PDO::class),
            $this->repo
        );
    }

    // ----------------------------------------------------------------
    // add() — validation branches + happy path
    // ----------------------------------------------------------------

    public function testAddRejectsEmptyDomain(): void
    {
        $this->repo->expects(self::never())->method('add');

        $result = $this->service->add('   ', 'STUDENT', 1);

        self::assertFalse($result['success']);
        self::assertSame('Le domaine est requis.', $result['error']);
    }

    public function testAddRejectsInvalidRole(): void
    {
        $this->repo->method('findRoleValues')->willReturn(self::ROLES);
        $this->repo->expects(self::never())->method('add');

        $result = $this->service->add('univ-amu.fr', 'ADMIN', 1);

        self::assertFalse($result['success']);
        self::assertSame('Le rôle sélectionné est invalide.', $result['error']);
    }

    public function testAddRejectsMalformedDomain(): void
    {
        $this->repo->method('findRoleValues')->willReturn(self::ROLES);
        $this->repo->expects(self::never())->method('add');

        $result = $this->service->add('not a domain', 'STUDENT', 1);

        self::assertFalse($result['success']);
        self::assertSame('Le domaine est invalide (ex. : univ-amu.fr).', $result['error']);
    }

    public function testAddRejectsDuplicateDomain(): void
    {
        $this->repo->method('findRoleValues')->willReturn(self::ROLES);
        $this->repo->method('existsByDomain')->with('univ-amu.fr')->willReturn(true);
        $this->repo->expects(self::never())->method('add');

        $result = $this->service->add('univ-amu.fr', 'STUDENT', 1);

        self::assertFalse($result['success']);
        self::assertSame('Ce domaine est déjà configuré.', $result['error']);
    }

    public function testAddSucceedsAndNormalisesInput(): void
    {
        $this->repo->method('findRoleValues')->willReturn(self::ROLES);
        $this->repo->method('existsByDomain')->willReturn(false);
        // Input is trimmed + lower-cased (domain) and upper-cased (role) before insert.
        $this->repo->expects(self::once())
            ->method('add')
            ->with('univ-amu.fr', 'TEACHER', 42)
            ->willReturn(7);

        $result = $this->service->add('  UNIV-AMU.FR  ', '  teacher ', 42);

        self::assertTrue($result['success']);
    }

    // ----------------------------------------------------------------
    // changeRole() — not-found, invalid role, happy path
    // ----------------------------------------------------------------

    public function testChangeRoleRejectsUnknownDomain(): void
    {
        $this->repo->method('findById')->with(99)->willReturn(null);
        $this->repo->expects(self::never())->method('updateRole');

        $result = $this->service->changeRole(99, 'STUDENT');

        self::assertFalse($result['success']);
        self::assertSame('Domaine introuvable.', $result['error']);
    }

    public function testChangeRoleRejectsInvalidRole(): void
    {
        $this->repo->method('findById')->willReturn(
            ['id' => 1, 'domain' => 'univ-amu.fr', 'role' => 'STUDENT', 'is_active' => true]
        );
        $this->repo->method('findRoleValues')->willReturn(self::ROLES);
        $this->repo->expects(self::never())->method('updateRole');

        $result = $this->service->changeRole(1, 'ADMIN');

        self::assertFalse($result['success']);
        self::assertSame('Le rôle sélectionné est invalide.', $result['error']);
    }

    public function testChangeRoleSucceedsAndNormalisesRole(): void
    {
        $this->repo->method('findById')->willReturn(
            ['id' => 1, 'domain' => 'univ-amu.fr', 'role' => 'STUDENT', 'is_active' => true]
        );
        $this->repo->method('findRoleValues')->willReturn(self::ROLES);
        $this->repo->expects(self::once())
            ->method('updateRole')
            ->with(1, 'TEACHER');

        $result = $this->service->changeRole(1, '  teacher ');

        self::assertTrue($result['success']);
    }

    // ----------------------------------------------------------------
    // setActive() — not-found + happy path (both states)
    // ----------------------------------------------------------------

    public function testSetActiveRejectsUnknownDomain(): void
    {
        $this->repo->method('findById')->with(99)->willReturn(null);
        $this->repo->expects(self::never())->method('setActive');

        $result = $this->service->setActive(99, false);

        self::assertFalse($result['success']);
        self::assertSame('Domaine introuvable.', $result['error']);
    }

    public function testSetActiveDisablesExistingDomain(): void
    {
        $this->repo->method('findById')->willReturn(
            ['id' => 1, 'domain' => 'univ-amu.fr', 'role' => 'STUDENT', 'is_active' => true]
        );
        $this->repo->expects(self::once())
            ->method('setActive')
            ->with(1, false);

        $result = $this->service->setActive(1, false);

        self::assertTrue($result['success']);
    }

    public function testSetActiveReenablesExistingDomain(): void
    {
        $this->repo->method('findById')->willReturn(
            ['id' => 1, 'domain' => 'univ-amu.fr', 'role' => 'STUDENT', 'is_active' => false]
        );
        $this->repo->expects(self::once())
            ->method('setActive')
            ->with(1, true);

        $result = $this->service->setActive(1, true);

        self::assertTrue($result['success']);
    }
}
