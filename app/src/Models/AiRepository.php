<?php

namespace Models;

use PDO;

/*
 * This class use PDO to recover 
 * all data about AI in the database
*/

class AiRepository{

    private PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getModelByName(string $name_AI): ?array
    {
        $query = $this->pdo->prepare('
        SELECT * FROM models where name = :name
        ');

        $query->execute(['name' => $name_AI]);

        $result = $query->fetch();

        if ($result === false) {
            return null;
        }

        return $result;
    }

    /**
     * Active models, for the session create/edit model picker.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllActive(): array
    {
        $query = $this->pdo->prepare('SELECT id, name, version, context_window, is_active FROM models WHERE is_active = :a ORDER BY name');
        $query->bindValue(':a', true, PDO::PARAM_BOOL);
        $query->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $query->fetchAll();

        return $rows;
    }

    /**
     * Id of the first active model — the default used when a session has no
     * explicitly authorised model. Null when no model is active.
     */
    public function firstActiveId(): ?int
    {
        $query = $this->pdo->prepare('SELECT id FROM models WHERE is_active = TRUE ORDER BY id LIMIT 1');
        $query->execute();
        $id = $query->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Models matching the given ids, for the session dashboard.
     *
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params       = [];
        foreach ($ids as $i => $id) {
            $key            = ':id' . $i;
            $placeholders[] = $key;
            $params[$key]   = $id;
        }

        $sql   = 'SELECT id, name, version, context_window, is_active FROM models WHERE id IN (' . implode(', ', $placeholders) . ') ORDER BY name';
        $query = $this->pdo->prepare($sql);
        $query->execute($params);

        /** @var list<array<string, mixed>> $rows */
        $rows = $query->fetchAll();

        return $rows;
    }
}