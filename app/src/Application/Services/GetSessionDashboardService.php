<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\SessionDashboardView;
use App\Application\Ports\ClockInterface;
use App\Application\Ports\ModelReadRepositoryInterface;
use App\Domain\Exceptions\SessionNotFoundException;
use App\Domain\Repositories\SessionRepositoryInterface;

/**
 * Use-case: build the teacher dashboard for a single session.
 *
 * Combines the Session aggregate, the list of authorised models and the
 * derived status into a flat {@see SessionDashboardView} the view can
 * render without calling any business method.
 */
final class GetSessionDashboardService
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessions,
        private readonly ModelReadRepositoryInterface $models,
        private readonly ClockInterface $clock,
    ) {
    }

    public function execute(int $id): SessionDashboardView
    {
        $session = $this->sessions->findById($id) ?? throw SessionNotFoundException::withId($id);

        $modelIds         = $this->sessions->authorizedModelIdsOf($id);
        $authorizedModels = $this->models->findByIds($modelIds);

        // SessionDashboardView expects array-shaped rows for the authorized
        // models; flatten the DTOs to keep the view-model storage agnostic.
        $authorizedRows = array_map(
            static fn ($m) => [
                'model_id' => $m->id,
                'name'     => $m->name,
                'version'  => $m->version,
            ],
            $authorizedModels
        );

        return SessionDashboardView::fromEntity($session, $authorizedRows, $this->clock);
    }
}
