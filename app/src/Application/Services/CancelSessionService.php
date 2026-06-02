<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\ClockInterface;
use App\Domain\Entities\Session;
use App\Domain\Exceptions\SessionNotFoundException;
use App\Domain\Repositories\SessionRepositoryInterface;

/**
 * Use-case: a teacher cancels a session.
 *
 * Now takes the clock so the entity can stamp `closed_at` at cancellation.
 */
final class CancelSessionService
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessions,
        private readonly ClockInterface $clock,
    ) {
    }

    public function execute(int $id): Session
    {
        $session = $this->sessions->findById($id) ?? throw SessionNotFoundException::withId($id);
        $session->cancel($this->clock->now());
        $this->sessions->save($session);

        return $session;
    }
}
