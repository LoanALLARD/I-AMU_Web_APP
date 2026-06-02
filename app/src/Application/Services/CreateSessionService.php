<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\CreateSessionRequest;
use App\Domain\Entities\Session;
use App\Domain\Repositories\SessionRepositoryInterface;
use App\Domain\ValueObjects\AccessCode;
use App\Domain\ValueObjects\SessionStatus;
use InvalidArgumentException;

/**
 * Use-case: a teacher creates a brand-new session.
 *
 * - Derives ends_at from starts_at + durationMinutes when both are set.
 * - Keeps the previewed access code if still free, otherwise asks the
 *   repository for a fresh one.
 * - Initial status: DRAFT if no schedule, SCHEDULED otherwise.
 * - Requires at least one authorised model (the 1..n cardinality of
 *   `session_models` can't be expressed in DDL).
 *
 * The caller is responsible for verifying that the teacher actually owns
 * the target resource (i.e. `resources.owner_id == $teacherId`) before
 * dispatching to this service — the service trusts the resourceId.
 */
final class CreateSessionService
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessions,
    ) {
    }

    public function execute(CreateSessionRequest $request, int $teacherId): Session
    {
        if ($request->modelIds === []) {
            throw new InvalidArgumentException("Une session doit autoriser au moins un modèle.");
        }
        if ($request->resourceId <= 0) {
            throw new InvalidArgumentException("Une ressource est obligatoire.");
        }

        $code = $this->resolveAccessCode($request->accessCode);

        $endsAt = null;
        if ($request->startsAt !== null && $request->durationMinutes > 0) {
            $endsAt = $request->startsAt->modify("+{$request->durationMinutes} minutes");
        }

        $initialStatus = $request->startsAt === null
            ? SessionStatus::Draft
            : SessionStatus::Scheduled;

        $session = new Session(
            id:                  null,
            resourceId:          $request->resourceId,
            teacherId:           $teacherId,
            name:                $request->name,
            type:                $request->type,
            status:              $initialStatus,
            accessCode:          $code,
            startsAt:            $request->startsAt,
            endsAt:              $endsAt,
            closedAt:            null,
            prePromptOverride:   $request->prePrompt,
            postPromptOverride:  $request->postPrompt,
            instructions:        $request->instructions,
            maxInputSize:        $request->maxInputSize,
        );

        $this->sessions->save($session);
        $sessionId = $session->id();
        if ($sessionId === null) {
            throw new \RuntimeException("Session id was not assigned by the repository.");
        }
        $this->sessions->setAuthorizedModels($sessionId, $request->modelIds);

        return $session;
    }

    private function resolveAccessCode(?string $proposed): AccessCode
    {
        if ($proposed !== null && $proposed !== '') {
            try {
                $code = new AccessCode($proposed);
                if ($this->sessions->findByAccessCode($code) === null) {
                    return $code;
                }
            } catch (InvalidArgumentException) {
                // Ignore: fall through to generation.
            }
        }
        return $this->sessions->generateUniqueAccessCode();
    }
}
