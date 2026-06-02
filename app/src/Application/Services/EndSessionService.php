<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\ClockInterface;
use App\Domain\Entities\Session;
use App\Domain\Exceptions\SessionNotFoundException;
use App\Domain\Repositories\SessionRepositoryInterface;

/**
 * Use-case: a teacher manually ends a session.
 * Mirror of {@see StartSessionService}.
 */
final class EndSessionService
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessions,
        private readonly ClockInterface $clock,
    ) {
    }

    public function execute(int $id): Session
    {
        $session = $this->sessions->findById($id) ?? throw SessionNotFoundException::withId($id);
        $session->end($this->clock->now());
        $this->sessions->save($session);

        return $session;
    }
}
