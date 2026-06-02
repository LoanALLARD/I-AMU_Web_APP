<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\DTOs\ModelMetaView;

/**
 * Read-side port for the LLM `model` table.
 *
 * Returns lightweight {@see ModelMetaView} DTOs rather than full Model
 * entities because the Sessions vertical slice only needs to display
 * metadata. The full Model aggregate, Ollama synchronisation and active /
 * inactive bookkeeping are introduced by spec 03 (Chat & LLM).
 *
 * Lives in Application/Ports (not Domain/Repositories) because the
 * Model aggregate is not yet part of the Domain layer and the interface
 * carries an Application DTO — putting it in Domain would violate the
 * "Domain depends on nothing outside itself" rule.
 */
interface ModelReadRepositoryInterface
{
    /**
     * Active models, alphabetical, for the create/edit form.
     *
     * @return list<ModelMetaView>
     */
    public function findAllActive(): array;

    /**
     * Models matching the given ids (active or not, so inactive but still
     * authorised models still show up in the dashboard).
     *
     * @param list<int> $ids
     * @return list<ModelMetaView>
     */
    public function findByIds(array $ids): array;
}
