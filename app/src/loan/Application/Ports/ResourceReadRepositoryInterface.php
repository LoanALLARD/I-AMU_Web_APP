<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\DTOs\ResourceMetaView;

/**
 * Read-side port for the `resources` table.
 *
 * The Session feature only needs to:
 *   - list a teacher's resources (so they can attach a session to one of them)
 *   - look up a single resource to enforce the ownership invariant at write time
 *
 * The full Resource aggregate (state transitions, students enrolment, …)
 * belongs to a later spec.
 */
interface ResourceReadRepositoryInterface
{
    /**
     * Returns the resources owned by the given teacher, ordered by code.
     *
     * @return list<ResourceMetaView>
     */
    public function findAllByOwner(int $teacherId): array;

    public function findById(int $id): ?ResourceMetaView;
}
