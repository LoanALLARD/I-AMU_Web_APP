<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\UpdateSessionRequest;
use App\Application\Ports\ClockInterface;
use App\Domain\Entities\Session;
use App\Domain\Exceptions\SessionNotFoundException;
use App\Domain\Repositories\SessionRepositoryInterface;
use InvalidArgumentException;

/**
 * Use-case: a teacher edits an existing session.
 *
 * The mutators on the Session entity already refuse changes once the
 * session is no longer editable. This service threads the request through
 * them and persists the result.
 */
final class UpdateSessionService
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessions,
        private readonly ClockInterface $clock,
    ) {
    }

    public function execute(int $id, UpdateSessionRequest $request): Session
    {
        if ($request->modelIds === []) {
            throw new InvalidArgumentException("Une session doit autoriser au moins un modèle.");
        }

        $session = $this->sessions->findById($id) ?? throw SessionNotFoundException::withId($id);

        $now = $this->clock->now();

        $endsAt = null;
        if ($request->startsAt !== null && $request->durationMinutes > 0) {
            $endsAt = $request->startsAt->modify("+{$request->durationMinutes} minutes");
        }

        $session->rename($request->name, $now);
        $session->reschedule($request->startsAt, $endsAt, $now);
        $session->reconfigure(
            prePromptOverride:  $request->prePrompt,
            postPromptOverride: $request->postPrompt,
            instructions:       $request->instructions,
            maxInputSize:       $request->maxInputSize,
            now:                $now,
        );

        $this->sessions->save($session);
        $this->sessions->setAuthorizedModels($id, $request->modelIds);

        return $session;
    }
}
