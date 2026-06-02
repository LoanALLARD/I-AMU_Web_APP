<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entities\Session;
use App\Domain\Repositories\SessionRepositoryInterface;
use App\Domain\ValueObjects\AccessCode;
use App\Domain\ValueObjects\SessionStatus;
use App\Domain\ValueObjects\SessionType;
use DateTimeImmutable;

/**
 * PostgreSQL implementation of {@see SessionRepositoryInterface}.
 *
 * Important notes about the live schema (init-scripts/IAMU_db.sql):
 *   - tables are PLURAL (`sessions`, `resources`, `teachers`, `models`, ...)
 *   - PK column is `id` everywhere (not `session_id` / `user_id` / ...)
 *   - The session's owning teacher is NOT a column on `sessions` — it is
 *     derived from `resources.owner_id`. Every SELECT therefore joins
 *     `resources` to surface `teacher_id` to the entity hydration.
 *   - The M:N session ↔ model table is `session_models` (was `authorizes`).
 */
final class PdoSessionRepository extends PdoRepository implements SessionRepositoryInterface
{
    /**
     * Columns selected from `sessions` plus the derived `teacher_id` from
     * the joined `resources` row.
     */
    private const SELECT_SQL = <<<'SQL'
        SELECT s.id, s.resource_id, r.owner_id AS teacher_id,
               s.name, s.type, s.status, s.access_code,
               s.starts_at, s.ends_at, s.closed_at,
               s.pre_prompt_override, s.post_prompt_override,
               s.instructions, s.max_input_size
        FROM sessions s
        JOIN resources r ON r.id = s.resource_id
    SQL;

    public function findById(int $id): ?Session
    {
        $row = $this->fetchOne(self::SELECT_SQL . ' WHERE s.id = :id', ['id' => $id]);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByAccessCode(AccessCode $code): ?Session
    {
        $row = $this->fetchOne(
            self::SELECT_SQL . ' WHERE s.access_code = :code',
            ['code' => $code->value]
        );
        return $row === null ? null : $this->hydrate($row);
    }

    public function findAllByTeacher(int $teacherId): array
    {
        // sessions has no created_at column on the live schema, so we
        // sort by starts_at desc (NULLs last, i.e. drafts first), then
        // by id desc as a deterministic tiebreaker.
        $rows = $this->fetchAll(
            self::SELECT_SQL
                . ' WHERE r.owner_id = :tid'
                . ' ORDER BY s.starts_at DESC NULLS FIRST, s.id DESC',
            ['tid' => $teacherId]
        );
        return array_map(fn ($row) => $this->hydrate($row), $rows);
    }

    public function save(Session $session): void
    {
        if ($session->id() === null) {
            $this->insert($session);
        } else {
            $this->update($session);
        }
    }

    public function authorizedModelIdsOf(int $sessionId): array
    {
        $rows = $this->fetchAll(
            'SELECT model_id FROM session_models WHERE session_id = :sid ORDER BY model_id',
            ['sid' => $sessionId]
        );
        return array_map(static fn ($r) => (int) $r['model_id'], $rows);
    }

    public function setAuthorizedModels(int $sessionId, array $modelIds): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->query(
                'DELETE FROM session_models WHERE session_id = :sid',
                ['sid' => $sessionId]
            );

            $uniqueIds = array_values(array_unique(array_map('intval', $modelIds)));
            foreach ($uniqueIds as $modelId) {
                $this->db->query(
                    'INSERT INTO session_models (session_id, model_id) VALUES (:sid, :mid)',
                    ['sid' => $sessionId, 'mid' => $modelId]
                );
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function generateUniqueAccessCode(): AccessCode
    {
        // Full [A-Z0-9] alphabet for 36^6 ≈ 2.17e9 codes.
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $alphaLen = strlen($alphabet) - 1;

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = '';
            for ($i = 0; $i < 6; $i++) {
                $candidate .= $alphabet[random_int(0, $alphaLen)];
            }
            $exists = $this->fetchOne(
                'SELECT 1 FROM sessions WHERE access_code = :code',
                ['code' => $candidate]
            );
            if ($exists === null) {
                return new AccessCode($candidate);
            }
        }
        throw new \RuntimeException("Could not generate a unique access code after 10 attempts.");
    }

    // ----------------------------------------------------------------
    // Hydration / persistence
    // ----------------------------------------------------------------

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Session
    {
        return new Session(
            id:                  (int) $row['id'],
            resourceId:          (int) $row['resource_id'],
            teacherId:           (int) $row['teacher_id'],
            name:                (string) $row['name'],
            type:                SessionType::from((string) $row['type']),
            status:              SessionStatus::from((string) $row['status']),
            accessCode:          new AccessCode((string) $row['access_code']),
            startsAt:            $row['starts_at']  !== null ? new DateTimeImmutable((string) $row['starts_at'])  : null,
            endsAt:              $row['ends_at']    !== null ? new DateTimeImmutable((string) $row['ends_at'])    : null,
            closedAt:            $row['closed_at']  !== null ? new DateTimeImmutable((string) $row['closed_at'])  : null,
            prePromptOverride:   $row['pre_prompt_override']  !== null ? (string) $row['pre_prompt_override']  : null,
            postPromptOverride:  $row['post_prompt_override'] !== null ? (string) $row['post_prompt_override'] : null,
            instructions:        $row['instructions']         !== null ? (string) $row['instructions']         : null,
            maxInputSize:        $row['max_input_size']       !== null ? (int) $row['max_input_size']          : null,
        );
    }

    private function insert(Session $session): void
    {
        $sql = <<<'SQL'
            INSERT INTO sessions (
                resource_id, name, type, status, access_code,
                starts_at, ends_at, closed_at,
                pre_prompt_override, post_prompt_override, instructions,
                max_input_size
            ) VALUES (
                :resource_id, :name, :type, :status, :access_code,
                :starts_at, :ends_at, :closed_at,
                :pre_prompt, :post_prompt, :instructions,
                :max_input
            )
            RETURNING id
        SQL;

        $stmt = $this->db->query($sql, $this->serialize($session));
        $id   = (int) $stmt->fetchColumn();
        $session->assignId($id);
    }

    private function update(Session $session): void
    {
        $sql = <<<'SQL'
            UPDATE sessions SET
                name                 = :name,
                status               = :status,
                starts_at            = :starts_at,
                ends_at              = :ends_at,
                closed_at            = :closed_at,
                pre_prompt_override  = :pre_prompt,
                post_prompt_override = :post_prompt,
                instructions         = :instructions,
                max_input_size       = :max_input
            WHERE id = :id
        SQL;

        $payload       = $this->serialize($session);
        $payload['id'] = $session->id();
        // resource_id, type, access_code are immutable post-insert.
        unset($payload['resource_id'], $payload['type'], $payload['access_code']);

        $this->db->query($sql, $payload);
    }

    /**
     * @return array<string, scalar|null>
     */
    private function serialize(Session $session): array
    {
        return [
            'resource_id'  => $session->resourceId(),
            'name'         => $session->name(),
            'type'         => $session->type()->value,
            'status'       => $session->status()->value,
            'access_code'  => $session->accessCode()->value,
            'starts_at'    => $session->startsAt()?->format('c'),
            'ends_at'      => $session->endsAt()?->format('c'),
            'closed_at'    => $session->closedAt()?->format('c'),
            'pre_prompt'   => $session->prePromptOverride(),
            'post_prompt'  => $session->postPromptOverride(),
            'instructions' => $session->instructions(),
            'max_input'    => $session->maxInputSize(),
        ];
    }
}
