<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTOs\LlmModelConfig;
use App\Application\Ports\LlmModelRepositoryInterface;

/**
 * Read-only PostgreSQL implementation of {@see LlmModelRepositoryInterface}.
 *
 * Resolves the `models` row whose `name` matches the tag sent by the
 * chat client, returning only the fields needed to route the request.
 */
final class PdoLlmModelRepository extends PdoRepository implements LlmModelRepositoryInterface
{
    public function findByName(string $name): ?LlmModelConfig
    {
        $row = $this->fetchOne(
            'SELECT name, adapter, api_url FROM models WHERE name = :name',
            ['name' => $name]
        );

        return $row === null ? null : LlmModelConfig::fromRow($row);
    }
}
