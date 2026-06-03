<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTOs\ResourceMetaView;
use App\Application\Ports\ResourceReadRepositoryInterface;

/**
 * Read-only PostgreSQL implementation of {@see ResourceReadRepositoryInterface}.
 *
 * Reads from the live `resources` table; matches the column layout defined
 * in init-scripts/IAMU_db.sql (`id, owner_id, department_id, code, name,
 * description, semester, state`).
 */
final class PdoResourceRepository extends PdoRepository implements ResourceReadRepositoryInterface
{
    private const COLUMNS = 'id, owner_id, code, name, state';

    public function findAllByOwner(int $teacherId): array
    {
        $rows = $this->fetchAll(
            'SELECT ' . self::COLUMNS
                . ' FROM resources WHERE owner_id = :tid'
                . ' ORDER BY code',
            ['tid' => $teacherId]
        );
        return array_map(static fn ($r) => ResourceMetaView::fromRow($r), $rows);
    }

    public function findById(int $id): ?ResourceMetaView
    {
        $row = $this->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM resources WHERE id = :id',
            ['id' => $id]
        );
        return $row === null ? null : ResourceMetaView::fromRow($row);
    }
}
