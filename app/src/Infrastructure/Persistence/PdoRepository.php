<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

/**
 * Shared base for every PDO repository.
 *
 * Holds the injected {@see PdoConnection} (so concrete repositories don't
 * each redeclare the constructor) and exposes two convenience fetch
 * helpers. Concrete repositories still declare which Domain interface
 * they implement and the methods they want to expose.
 *
 * Per the architecture, this is the ONLY layer allowed to talk to PDO.
 */
abstract class PdoRepository
{
    public function __construct(
        protected readonly PdoConnection $db,
    ) {
    }

    /**
     * Returns the first matching row as an associative array, or null.
     *
     * @param array<int|string, scalar|null> $params
     * @return array<string, mixed>|null
     */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->db->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Returns every matching row as a list of associative arrays.
     *
     * @param array<int|string, scalar|null> $params
     * @return list<array<string, mixed>>
     */
    protected function fetchAll(string $sql, array $params = []): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->db->query($sql, $params)->fetchAll();
        return $rows;
    }
}
