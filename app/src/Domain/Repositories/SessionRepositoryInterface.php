<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\Session;
use App\Domain\ValueObjects\AccessCode;

/**
 * Contract for any storage backend persisting Session aggregates.
 *
 * Implemented by `App\Infrastructure\Persistence\PdoSessionRepository`
 * at runtime, and by in-memory fakes in tests. The Application layer
 * depends on this interface only, never on the concrete class.
 */
interface SessionRepositoryInterface
{
    /**
     * Returns the session matching the given id, or null if none exists.
     */
    public function findById(int $id): ?Session;

    /**
     * Returns the session matching the given access code, or null.
     */
    public function findByAccessCode(AccessCode $code): ?Session;

    /**
     * Lists all sessions owned by the given teacher, most recent first.
     *
     * @return list<Session>
     */
    public function findAllByTeacher(int $teacherId): array;

    /**
     * Persists the session. For new sessions (id() === null) an INSERT is
     * performed and {@see Session::assignId()} is called with the generated
     * id. For existing sessions an UPDATE is performed.
     *
     * Authorised models are NOT persisted here — use
     * {@see setAuthorizedModels()} after save() to update the M:N table.
     */
    public function save(Session $session): void;

    /**
     * Returns the list of model ids authorised for the given session.
     *
     * @return list<int>
     */
    public function authorizedModelIdsOf(int $sessionId): array;

    /**
     * Replaces the full set of authorised models for the given session.
     * Runs in a transaction (DELETE then INSERT each id).
     *
     * @param list<int> $modelIds
     */
    public function setAuthorizedModels(int $sessionId, array $modelIds): void;

    /**
     * Generates a brand new access code that is guaranteed unique against
     * the current state of the table. Used both to preview the code in the
     * create form and as a fallback when the previewed code has been
     * grabbed by another session between preview and save.
     */
    public function generateUniqueAccessCode(): AccessCode;
}
