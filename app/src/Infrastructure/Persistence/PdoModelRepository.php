<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTOs\ModelMetaView;
use App\Application\Ports\ModelReadRepositoryInterface;

/**
 * Read-only PostgreSQL implementation of {@see ModelReadRepositoryInterface}.
 *
 * Aligned with the live schema: table is `models`, PK is `id`, and the
 * scoping columns (`department_id` / `resource_id` / `is_shareable`) exist
 * but are not surfaced to the Sessions UI yet — spec 03 will take that on.
 */
final class PdoModelRepository extends PdoRepository implements ModelReadRepositoryInterface
{
    private const COLUMNS = 'id, name, version, context_window, is_active';

    public function findAllActive(): array
    {
        $rows = $this->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM models WHERE is_active = :a ORDER BY name',
            ['a' => true]
        );
        return array_map(static fn ($r) => ModelMetaView::fromRow($r), $rows);
    }

    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_map(static fn ($i) => ':id' . $i, array_keys($ids)));
        $params = [];
        foreach ($ids as $i => $id) {
            $params['id' . $i] = $id;
        }

        $sql = 'SELECT ' . self::COLUMNS
             . ' FROM models'
             . ' WHERE id IN (' . $placeholders . ')'
             . ' ORDER BY name';

        $rows = $this->fetchAll($sql, $params);
        return array_map(static fn ($r) => ModelMetaView::fromRow($r), $rows);
    }
}
