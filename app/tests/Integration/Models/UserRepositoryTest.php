<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use Models\UserRepository;
use PDO;
use Tests\Integration\IntegrationTestCase;

/**
 * @covers \Models\UserRepository
 */
final class UserRepositoryTest extends IntegrationTestCase
{
    private UserRepository $repo;

    /**
     * createUserWithRole() and createDepartmentAdmin() open their OWN
     * transaction, which pgsql cannot nest inside the wrapping one. Run these
     * tests without the wrapping transaction and clean up with TRUNCATE.
     */
    protected bool $useTransaction = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new UserRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        // No wrapping transaction here: wipe the rows these tests create.
        // CASCADE follows the FKs, RESTART IDENTITY keeps ids predictable.
        $this->pdo->exec(
            'TRUNCATE researcher_authorizations, department_administrators,
                      researchers, teachers, students, users, laboratories,
                      departments, places, super_administrators
             RESTART IDENTITY CASCADE'
        );
        parent::tearDown();
    }

    // findByEmail

    public function testFindByEmailReturnsTheRowWhenItExists(): void
    {
        $id = $this->seedUser(['email' => 'alice@univ-amu.fr']);

        $row = $this->repo->findByEmail('alice@univ-amu.fr');

        self::assertNotNull($row);
        self::assertSame($id, (int) $row['id']);
        self::assertSame('alice@univ-amu.fr', $row['email']);
        // The projection must include the columns the auth flow reads.
        self::assertArrayHasKey('password_hash', $row);
        self::assertArrayHasKey('is_active', $row);
        self::assertArrayHasKey('email_verified_at', $row);
    }

    public function testFindByEmailReturnsNullForUnknownEmail(): void
    {
        self::assertNull($this->repo->findByEmail('nobody@univ-amu.fr'));
    }

    public function testFindByEmailIsExactNotPartial(): void
    {
        $this->seedUser(['email' => 'bob@univ-amu.fr']);

        // A substring must not match — guards against a stray LIKE creeping in.
        self::assertNull($this->repo->findByEmail('bob'));
        self::assertNull($this->repo->findByEmail('bob@univ-amu.fr '));
    }

    // emailExists

    public function testEmailExistsIsTrueOnlyForRegisteredEmail(): void
    {
        $this->seedUser(['email' => 'taken@univ-amu.fr']);

        self::assertTrue($this->repo->emailExists('taken@univ-amu.fr'));
        self::assertFalse($this->repo->emailExists('free@univ-amu.fr'));
    }

    // deactivate / reactivate  (the bug you were chasing)

    public function testDeactivateFlipsAnActiveAccountAndReportsOneRow(): void
    {
        $id = $this->seedUser(['is_active' => true]);

        $affected = $this->repo->deactivate($id);

        self::assertSame(1, $affected);
        self::assertFalse((bool) $this->repo->findById($id)['is_active']);
    }

    public function testDeactivateIsANoopOnAnAlreadyInactiveAccount(): void
    {
        $id = $this->seedUser(['is_active' => false]);

        // The WHERE is_active = TRUE guard means no row changes — must report 0,
        // not silently "succeed". Callers rely on rowCount to detect this.
        self::assertSame(0, $this->repo->deactivate($id));
    }

    public function testReactivateFlipsAnInactiveAccountAndReportsOneRow(): void
    {
        $id = $this->seedUser(['is_active' => false]);

        $affected = $this->repo->reactivate($id);

        self::assertSame(1, $affected);
        self::assertTrue((bool) $this->repo->findById($id)['is_active']);
    }

    public function testReactivateIsANoopOnAnAlreadyActiveAccount(): void
    {
        $id = $this->seedUser(['is_active' => true]);

        self::assertSame(0, $this->repo->reactivate($id));
    }

    public function testReactivateUnknownIdAffectsNothing(): void
    {
        self::assertSame(0, $this->repo->reactivate(999999));
    }

    // updateTheme  (enum cast)

    public function testUpdateThemePersistsEachEnumValue(): void
    {
        $id = $this->seedUser();

        foreach (['LIGHT', 'DARK', 'AUTO'] as $theme) {
            $this->repo->updateTheme($id, $theme);
            $row = $this->repo->findByEmail($this->emailOf($id));
            self::assertSame($theme, $row['theme']);
        }
    }

    // updateName

    public function testUpdateNameChangesBothParts(): void
    {
        $id = $this->seedUser(['first_name' => 'Old', 'last_name' => 'Name']);

        $this->repo->updateName($id, 'New', 'Person');

        $row = $this->repo->findByEmail($this->emailOf($id));
        self::assertSame('New', $row['first_name']);
        self::assertSame('Person', $row['last_name']);
    }

    // touchLastLogin

    public function testTouchLastLoginStampsATimestamp(): void
    {
        $id = $this->seedUser();

        $before = $this->pdo->query("SELECT last_login_at FROM users WHERE id = $id")->fetchColumn();
        self::assertNull($before);

        $this->repo->touchLastLogin($id);

        $after = $this->pdo->query("SELECT last_login_at FROM users WHERE id = $id")->fetchColumn();
        self::assertNotNull($after);
    }

    // createUserWithRole  (transaction + role branch)

    public function testCreateStudentInsertsUserAndStudentRow(): void
    {
        $dept = $this->seedDepartment();

        $id = $this->repo->createUserWithRole($this->newUserPayload([
            'department_id' => $dept,
            'promo_year'    => 2,
        ]), 'student');

        self::assertGreaterThan(0, $id);
        self::assertTrue($this->repo->hasRole($id, 'students'));
        self::assertFalse($this->repo->hasRole($id, 'teachers'));
        $year = $this->pdo->query("SELECT year FROM students WHERE id = $id")->fetchColumn();
        self::assertSame(2, (int) $year);
    }

    public function testCreateTeacherInsertsTeacherRow(): void
    {
        $dept = $this->seedDepartment();

        $id = $this->repo->createUserWithRole($this->newUserPayload([
            'department_id' => $dept,
        ]), 'teacher');

        self::assertTrue($this->repo->hasRole($id, 'teachers'));
        self::assertFalse($this->repo->hasRole($id, 'students'));
    }

    public function testCreateResearcherHasNullDepartmentAndAttachesLaboratory(): void
    {
        $labId = $this->seedLaboratory();

        $id = $this->repo->createUserWithRole($this->newUserPayload([
            'department_id' => null,
            'laboratory_id' => $labId,
        ]), 'researcher');

        self::assertTrue($this->repo->hasRole($id, 'researchers'));
        $lab = $this->pdo->query("SELECT laboratory_id FROM researchers WHERE id = $id")->fetchColumn();
        self::assertSame($labId, (int) $lab);
        $dept = $this->pdo->query("SELECT department_id FROM users WHERE id = $id")->fetchColumn();
        self::assertNull($dept);
    }

    public function testCreateUserWithRoleRollsBackWhenRoleInsertFails(): void
    {
        // A researcher payload pointing at a laboratory that does not exist
        // violates the FK on researchers.laboratory_id. The whole transaction
        // must roll back — no orphan user row may survive.
        $emailBefore = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

        try {
            $this->repo->createUserWithRole($this->newUserPayload([
                'department_id' => null,
                'laboratory_id' => 424242, // no such lab
            ]), 'researcher');
            self::fail('Expected the FK violation to throw.');
        } catch (\Throwable $e) {
            // expected
        }

        $emailAfter = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        self::assertSame($emailBefore, $emailAfter, 'A half-created user leaked despite rollback.');
    }

    // isDepartmentMember / findMemberRow  (IDOR scoping)

    public function testIsDepartmentMemberTrueOnlyForMembersOfThatDepartment(): void
    {
        $deptA = $this->seedDepartment('A');
        $deptB = $this->seedDepartment('B');

        $studentA = $this->seedUser(['department_id' => $deptA]);
        $this->pdo->exec("INSERT INTO students (id, year) VALUES ($studentA, 1)");

        self::assertTrue($this->repo->isDepartmentMember($studentA, $deptA));
        // Forged id from another department must be rejected (IDOR guard).
        self::assertFalse($this->repo->isDepartmentMember($studentA, $deptB));
    }

    public function testIsDepartmentMemberFalseForUserWithoutRole(): void
    {
        $dept = $this->seedDepartment();
        // A bare user with neither teacher nor student row is not a "member".
        $bare = $this->seedUser(['department_id' => $dept]);

        self::assertFalse($this->repo->isDepartmentMember($bare, $dept));
    }

    public function testFindMemberRowIsScopedToTheDepartment(): void
    {
        $deptA = $this->seedDepartment('A');
        $deptB = $this->seedDepartment('B');
        $student = $this->seedUser(['department_id' => $deptA]);
        $this->pdo->exec("INSERT INTO students (id, year) VALUES ($student, 1)");

        self::assertNotNull($this->repo->findMemberRow($student, $deptA));
        // Cannot read a member through the wrong department.
        self::assertNull($this->repo->findMemberRow($student, $deptB));
    }

    // listDepartmentMembers / count  (cursor pagination)

    public function testListDepartmentMembersReturnsOnlyMembersOrderedByName(): void
    {
        $dept = $this->seedDepartment();
        $this->seedStudent($dept, 'Zoe', 'Zimmer');
        $this->seedStudent($dept, 'Anna', 'Albers');
        $this->seedTeacher($dept, 'Mia', 'Moreau');
        // Noise: a bare user and a member of another department.
        $this->seedUser(['department_id' => $dept]);
        $other = $this->seedDepartment('Other');
        $this->seedStudent($other, 'Should', 'NotAppear');

        $rows = $this->repo->listDepartmentMembers($dept);

        self::assertCount(3, $rows);
        $lastNames = array_column($rows, 'last_name');
        self::assertSame(['Albers', 'Moreau', 'Zimmer'], $lastNames);
    }

    public function testListDepartmentMembersCursorSkipsAlreadySeenRows(): void
    {
        $dept = $this->seedDepartment();
        $this->seedStudent($dept, 'Anna', 'Albers');
        $this->seedStudent($dept, 'Bea', 'Bernard');
        $this->seedStudent($dept, 'Cleo', 'Charpentier');

        $firstPage = $this->repo->listDepartmentMembers($dept, null, 2);
        self::assertCount(2, $firstPage);
        self::assertSame('Albers', $firstPage[0]['last_name']);
        self::assertSame('Bernard', $firstPage[1]['last_name']);

        $cursor = [
            'last_name'  => $firstPage[1]['last_name'],
            'first_name' => $firstPage[1]['first_name'],
            'id'         => (int) $firstPage[1]['id'],
        ];
        $secondPage = $this->repo->listDepartmentMembers($dept, $cursor, 2);
        self::assertCount(1, $secondPage);
        self::assertSame('Charpentier', $secondPage[0]['last_name']);
    }

    public function testCountDepartmentMembersCountsOnlyRoledMembers(): void
    {
        $dept = $this->seedDepartment();
        $this->seedStudent($dept, 'A', 'A');
        $this->seedTeacher($dept, 'B', 'B');
        $this->seedUser(['department_id' => $dept]); // bare user, not counted

        self::assertSame(2, $this->repo->CountDepartmentMembers($dept));
    }

    // findUsers  (search)

    public function testFindUsersMatchesFirstOrLastNameWithinDepartment(): void
    {
        $dept = $this->seedDepartment();
        $this->seedStudent($dept, 'Martin', 'Dupont');
        $this->seedStudent($dept, 'Sophie', 'Martin');
        $this->seedStudent($dept, 'Paul', 'Durand');

        $rows = $this->repo->findUsers('Martin', $dept);

        // Matches both the first name "Martin" and the last name "Martin".
        self::assertCount(2, $rows);
    }

    // email verification

    public function testVerifyEmailConsumesTheTokenExactlyOnce(): void
    {
        $id = $this->seedUser();
        $this->repo->setVerifyToken($id, 'tok-123');

        self::assertTrue($this->repo->verifyEmail('tok-123'));
        // Second use must fail: token cleared and already verified.
        self::assertFalse($this->repo->verifyEmail('tok-123'));

        $verifiedAt = $this->pdo->query("SELECT email_verified_at FROM users WHERE id = $id")->fetchColumn();
        self::assertNotNull($verifiedAt);
    }

    public function testVerifyEmailRejectsUnknownToken(): void
    {
        self::assertFalse($this->repo->verifyEmail('does-not-exist'));
    }

    // research opposition (RGPD)

    public function testUpdateResearchOppositionTogglesTheFlag(): void
    {
        $id = $this->seedUser();

        $this->repo->updateResearchOpposition($id, true);
        self::assertTrue((bool) $this->pdo->query("SELECT research_opposed FROM users WHERE id = $id")->fetchColumn());

        $this->repo->updateResearchOpposition($id, false);
        self::assertFalse((bool) $this->pdo->query("SELECT research_opposed FROM users WHERE id = $id")->fetchColumn());
    }

    // teacher specialisation

    public function testIsTeacherSpecializedReflectsTheFlag(): void
    {
        $dept = $this->seedDepartment();
        $specialised = $this->seedTeacher($dept, 'Spec', 'Ialised');
        $plain       = $this->seedTeacher($dept, 'Plain', 'Teacher');
        $this->pdo->exec("UPDATE teachers SET is_specialised = TRUE WHERE id = $specialised");

        self::assertTrue($this->repo->isTeacherSpecialized($specialised));
        self::assertFalse($this->repo->isTeacherSpecialized($plain));
    }

    // department admin lifecycle

    public function testCreateDepartmentAdminMarksEmailVerifiedAndLinksInviter(): void
    {
        $dept    = $this->seedDepartment();
        $inviter = $this->seedSuperAdmin();

        $adminId = $this->repo->createDepartmentAdmin([
            'email'           => 'admin@univ-amu.fr',
            'password_hash'   => password_hash('x', PASSWORD_DEFAULT),
            'first_name'      => 'Admin',
            'last_name'       => 'Person',
            'department_id'   => $dept,
            'consent_version' => '1.0',
        ], $inviter);

        $verified = $this->pdo->query("SELECT email_verified_at FROM users WHERE id = $adminId")->fetchColumn();
        self::assertNotNull($verified, 'Invited admins should be pre-verified.');

        $invitedBy = $this->pdo->query("SELECT invited_by_id FROM department_administrators WHERE id = $adminId")->fetchColumn();
        self::assertSame($inviter, (int) $invitedBy);
    }

    public function testDeleteAdminRemovesTheUserRow(): void
    {
        $dept    = $this->seedDepartment();
        $inviter = $this->seedSuperAdmin();
        $adminId = $this->repo->createDepartmentAdmin([
            'email'         => 'todelete@univ-amu.fr',
            'password_hash' => 'h',
            'first_name'    => 'X',
            'last_name'     => 'Y',
            'department_id' => $dept,
        ], $inviter);

        self::assertSame(1, $this->repo->deleteAdmin($adminId));
        self::assertNull($this->repo->findById($adminId));
    }

    // profile field updates

    public function testUpdatePasswordStoresAVerifiableHash(): void
    {
        $id = $this->seedUser();

        self::assertTrue($this->repo->updatePassword($id, 'new-secret'));

        $hash = $this->repo->findById($id)['password_hash'];
        self::assertTrue(password_verify('new-secret', $hash));
        self::assertFalse(password_verify('wrong', $hash));
    }

    public function testUpdateEmailChangesTheAddress(): void
    {
        $id = $this->seedUser(['email' => 'before@univ-amu.fr']);

        self::assertTrue($this->repo->updateEmail($id, 'after@univ-amu.fr'));
        self::assertSame('after@univ-amu.fr', $this->repo->findByEmail('after@univ-amu.fr')['email']);
    }

    // helpers

    private function emailOf(int $id): string
    {
        return (string) $this->pdo->query("SELECT email FROM users WHERE id = $id")->fetchColumn();
    }

    /**
     * Inserts a user with sensible defaults and returns its id.
     *
     * @param array<string, mixed> $overrides
     */
    private function seedUser(array $overrides = []): int
    {
        $u = array_merge([
            'email'         => 'user' . uniqid() . '@univ-amu.fr',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            'first_name'    => 'Jane',
            'last_name'     => 'Doe',
            'is_active'     => true,
            'department_id' => null,
        ], $overrides);

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, password_hash, first_name, last_name, is_active, department_id)
             VALUES (:email, :hash, :fn, :ln, :active, :dept) RETURNING id'
        );
        $stmt->bindValue('email', $u['email']);
        $stmt->bindValue('hash', $u['password_hash']);
        $stmt->bindValue('fn', $u['first_name']);
        $stmt->bindValue('ln', $u['last_name']);
        $stmt->bindValue('active', $u['is_active'], PDO::PARAM_BOOL);
        $stmt->bindValue('dept', $u['department_id'], $u['department_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Seeds a department. departments.place_id is NOT NULL in the real schema,
     * so a place is created first.
     */
    private function seedDepartment(string $name = 'Informatique'): int
    {
        $placeId = (int) $this->pdo
            ->query("INSERT INTO places (name) VALUES ('Site') RETURNING id")
            ->fetchColumn();

        $stmt = $this->pdo->prepare(
            'INSERT INTO departments (place_id, name) VALUES (:p, :n) RETURNING id'
        );
        $stmt->execute(['p' => $placeId, 'n' => $name]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function newUserPayload(array $overrides = []): array
    {
        return array_merge([
            'email'           => 'new' . uniqid() . '@univ-amu.fr',
            'password_hash'   => password_hash('secret', PASSWORD_DEFAULT),
            'first_name'      => 'New',
            'last_name'       => 'User',
            'department_id'   => null,
            'consent_version' => '1.0',
            'promo_year'      => 1,
        ], $overrides);
    }

    private function seedLaboratory(string $code = 'LIS', string $name = 'Laboratoire'): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO laboratories (code, name) VALUES (:c, :n) RETURNING id');
        $stmt->execute(['c' => $code, 'n' => $name]);
        return (int) $stmt->fetchColumn();
    }

    private function seedStudent(int $dept, string $first, string $last): int
    {
        $id = $this->seedUser(['department_id' => $dept, 'first_name' => $first, 'last_name' => $last]);
        $this->pdo->exec("INSERT INTO students (id, year) VALUES ($id, 1)");
        return $id;
    }

    private function seedTeacher(int $dept, string $first, string $last): int
    {
        $id = $this->seedUser(['department_id' => $dept, 'first_name' => $first, 'last_name' => $last]);
        $this->pdo->exec("INSERT INTO teachers (id) VALUES ($id)");
        return $id;
    }

    private function seedSuperAdmin(): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO super_administrators (email, password_hash, first_name, last_name)
             VALUES (:e, 'h', 'Super', 'Admin') RETURNING id"
        );
        $stmt->execute(['e' => 'super' . uniqid() . '@univ-amu.fr']);
        return (int) $stmt->fetchColumn();
    }
}