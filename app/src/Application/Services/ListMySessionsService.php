<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\SessionListView;
use App\Application\Ports\ClockInterface;
use App\Domain\Repositories\SessionRepositoryInterface;

/**
 * Use-case: a teacher lists their own sessions.
 *
 * Maps the Session aggregates returned by the repository to a list of
 * {@see SessionListView} flat view-models so the view template never has
 * to call methods on the entity.
 */
final class ListMySessionsService
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessions,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return list<SessionListView>
     */
    public function execute(int $teacherId): array
    {
        $entities = $this->sessions->findAllByTeacher($teacherId);

        return array_map(
            fn ($s) => SessionListView::fromEntity($s, $this->clock),
            $entities
        );
    }
}
