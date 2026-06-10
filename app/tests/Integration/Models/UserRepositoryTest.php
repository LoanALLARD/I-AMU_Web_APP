<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use Models\UserRepository;
use PDOException;
use Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for UserRepository against a real PostgreSQL database.
 *
 * createUserWithRole() opens its OWN transaction, which cannot nest inside the
 * base class' wrapping transaction — so this class opts out of it
 * ($useTransaction = false) and deletes the rows it creates in tearDown
 * (the role tables cascade from users). Emails are randomised so a crash that
 * skips tearDown never collides with a later run.
 */
final class UserRepositoryTest extends IntegrationTestCase
{
    protected bool $useTransaction = false;

    private UserRepository $repo;

    /** @var list<int> User ids to delete in tearDown (role rows cascade). */
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new UserRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        $delete = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        foreach ($this->createdUserIds as $id) {
            $delete->execute(['id' => $id]);
        }
        $this->createdUserIds = [];
        parent::tearDown();
    }

    public function testCreateStudentThenReadRoundTrip(): void
    {
        $email = $this->uniqueEmail();

        $id = $this->createUser($email, 'student');

        self::assertGreaterThan(0, $id);
        self::assertTrue($this->repo->emailExists($email));
        self::assertTrue($this->repo->hasRole($id, 'students'));

        $byEmail = $this->repo->findByEmail($email);
        self::assertNotNull($byEmail);
        /** @var array<string, mixed> $byEmail */
        self::assertSame($id, (int) $byEmail['id']);
        self::assertSame($email, $byEmail['email']);
    }

    public function testCreateTeacherInsertsIntoTeachersTable(): void
    {
        // Regression guard: createUserWithRole() used to run `INSERT INTO
        // teacher` (singular) — a table that does not exist — so registering a
        // teacher crashed at runtime. This passes only against the real schema.
        $id = $this->createUser($this->uniqueEmail(), 'teacher');

        self::assertTrue($this->repo->hasRole($id, 'teachers'));
        self::assertFalse($this->repo->hasRole($id, 'students'));
    }

    public function testEmailVerificationIsSingleUse(): void
    {
        $id = $this->createUser($this->uniqueEmail(), 'student');
        $token = 'it-token-' . bin2hex(random_bytes(8));

        $this->repo->setVerifyToken($id, $token);

        self::assertTrue($this->repo->verifyEmail($token));
        // The token is cleared on use: a second verification finds nothing.
        self::assertFalse($this->repo->verifyEmail($token));
    }

    public function testUpdateThemePersists(): void
    {
        $email = $this->uniqueEmail();
        $id = $this->createUser($email, 'student');

        $this->repo->updateTheme($id, 'DARK');

        $row = $this->repo->findByEmail($email);
        self::assertNotNull($row);
        /** @var array<string, mixed> $row */
        self::assertSame('DARK', $row['theme']);
    }

    public function testRoleExclusivityRejectsASecondRole(): void
    {
        $id = $this->createUser($this->uniqueEmail(), 'student');

        // The user already holds the student role; the enforce_role_exclusivity
        // trigger must reject a second role row.
        $this->expectException(PDOException::class);
        $this->pdo->prepare('INSERT INTO teachers (id) VALUES (:id)')
            ->execute(['id' => $id]);
    }

    /**
     * @param 'teacher'|'student' $role
     */
    private function createUser(string $email, string $role): int
    {
        $id = $this->repo->createUserWithRole([
            'email'           => $email,
            'password_hash'   => 'integration-test',
            'first_name'      => 'It',
            'last_name'       => 'Test',
            'department_id'   => null,
            'consent_version' => '1.0',
            'promo_year'      => 2,
        ], $role);

        $this->createdUserIds[] = $id;

        return $id;
    }

    private function uniqueEmail(): string
    {
        return 'it-user-' . bin2hex(random_bytes(6)) . '@example.test';
    }
}
