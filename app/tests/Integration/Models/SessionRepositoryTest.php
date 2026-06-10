<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use Models\SessionRepository;
use Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for SessionRepository against a real PostgreSQL database.
 *
 * The headline assertion is the database-side access-code generation: the
 * trg_generate_session_access_code trigger (02_triggers.sql) must mint a
 * unique 6-char code the moment a session moves to SCHEDULED/ACTIVE, and stay
 * silent while it is DRAFT. A mocked PDO can never exercise that.
 *
 * insert()/update() do not open their own transaction, so the default
 * wrapping-transaction isolation applies: the seeded graph
 * (place -> department -> user -> teacher -> resource -> session) is rolled
 * back after each test.
 */
final class SessionRepositoryTest extends IntegrationTestCase
{
    private SessionRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SessionRepository($this->pdo);
    }

    public function testInsertDraftHasNoAccessCode(): void
    {
        $resourceId = $this->seedResource();

        $result = $this->repo->insert($this->sessionData($resourceId, 'DRAFT'));

        self::assertGreaterThan(0, $result['id']);
        // DRAFT is below the SCHEDULED/ACTIVE threshold: the trigger stays idle.
        self::assertNull($result['access_code']);
    }

    public function testSchedulingGeneratesAccessCodeViaTrigger(): void
    {
        $resourceId = $this->seedResource();
        $id = $this->repo->insert($this->sessionData($resourceId, 'DRAFT'))['id'];

        $this->repo->update($id, $this->sessionData($resourceId, 'SCHEDULED'));

        $row = $this->repo->findById($id);
        self::assertNotNull($row);
        /** @var array<string, mixed> $row */
        $code = $row['access_code'];
        self::assertIsString($code);
        self::assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $code);

        // The minted code resolves back to the very same session.
        $found = $this->repo->findByAccessCode($code);
        self::assertNotNull($found);
        /** @var array<string, mixed> $found */
        self::assertSame($id, (int) $found['id']);
    }

    /**
     * @return array<string, scalar|null>
     */
    private function sessionData(int $resourceId, string $status): array
    {
        return [
            'resource_id'          => $resourceId,
            'name'                 => 'IT Session',
            'type'                 => null,
            'status'               => $status,
            'starts_at'            => null,
            'ends_at'              => null,
            'closed_at'            => null,
            'pre_prompt_override'  => null,
            'post_prompt_override' => null,
            'instructions'         => null,
            'max_input_size'       => null,
            'max_tokens'           => null,
            'documents_max_bytes'  => null,
        ];
    }

    /**
     * Seeds the minimal FK graph a session needs and returns the resource id:
     * place -> department -> user -> teacher (raw, owner) -> resource.
     */
    private function seedResource(): int
    {
        $placeId = $this->insertId("INSERT INTO places (name) VALUES ('IT Place') RETURNING id");
        $deptId  = $this->insertId(
            "INSERT INTO departments (place_id, name) VALUES (:p, 'IT Dept') RETURNING id",
            ['p' => $placeId]
        );
        $userId = $this->insertId(
            "INSERT INTO users (email, password_hash, department_id)
             VALUES (:email, 'x', :dept) RETURNING id",
            ['email' => 'it-teacher-' . bin2hex(random_bytes(6)) . '@example.test', 'dept' => $deptId]
        );
        // Teacher role inserted directly (the owner FK target).
        $this->pdo->prepare('INSERT INTO teachers (id) VALUES (:id)')->execute(['id' => $userId]);

        return $this->insertId(
            "INSERT INTO resources (owner_id, department_id, code, name)
             VALUES (:owner, :dept, :code, 'IT Resource') RETURNING id",
            ['owner' => $userId, 'dept' => $deptId, 'code' => 'IT-' . bin2hex(random_bytes(4))]
        );
    }

    /**
     * @param array<string, scalar|null> $params
     */
    private function insertId(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}
