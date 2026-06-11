<?php

namespace Models;

use PDO;

/**
 * Data access for the `models` table (the LLM models a department can use).
 *
 * Holds the SQL for picking active models when creating or running a session,
 * resolving a model by name, and registering a new model.
 */
class AiRepository
{

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
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
    public function findAllActiveBySession(?int $sessionID, ?int $dep_id = null): array
    {
        if ($sessionID == null){
            $query = $this->pdo->prepare(
                'SELECT m.name,m.id 
                from models m 
                where resource_id is NULL
                and department_id = :dep_id 
                and is_active = :a
                UNION
                SELECT name,id 
                FROM models 
                where resource_id is NULL
                and is_shareable = true'
            );
            $query->execute([
                "a"    => true,
                "dep_id"  => $dep_id
            ]);
        }
        else{
            $query = $this->pdo->prepare(
                'SELECT m.name, m.id FROM models m
                INNER JOIN session_models sm
                on sm.model_id = m.id
                where sm.session_id = :session_id
                and m.is_active = :a '
            );
            $query->execute([
                "a"    => true,
                "session_id"  => $sessionID
            ]);
        }
        /** @var list<array<string, mixed>> $rows */
        $rows = $query->fetchAll();

        return $rows;
    }

    /**
     * Active models linked to a given resource, for the session model picker.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllActiveByResource(int $resource_id): array
    {
        $query = $this->pdo->prepare(
            'SELECT m.name,m.id from models m
            INNER JOIN model_resource_accesses mra
            on m.resource_id = mra.model_id
            where mra.resource_id = :r_id
        ');
        $query->execute([
            ":r_id" => $resource_id
        ]);
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
        $params = [];
        foreach ($ids as $i => $id) {
            $key = ':id' . $i;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $sql = 'SELECT id, name, size, context_window, is_active FROM models WHERE id IN (' . implode(', ', $placeholders) . ') ORDER BY name';
        $query = $this->pdo->prepare($sql);
        $query->execute($params);

        /** @var list<array<string, mixed>> $rows */
        $rows = $query->fetchAll();

        return $rows;
    }

    /**
     * Returns the distinct adapter values currently in use across models,
     * to populate the adapter picker on the add-model form. Returns a single
     * fetched row (`false` when no model exists).
     */
    public function getAllTypeOfAdapters(): mixed
    {
        $query = $this->pdo->prepare(
            'SELECT adapter FROM models GROUP BY adapter'
        );

        $query->execute();

        $result = $query->fetch();

        return $result;
    }
    /**
     * @return array<string, mixed>|null
     */
    public function addModel(?int $department_id,?string $resource_id,string $name, string $size, string $provider, string $adapter, string $apiUrl, int $contextWindow, string $isShareable)
    {
        $query = $this->pdo->prepare(
            'INSERT INTO models (department_id,resource_id,name,size,provider,context_window,api_url,adapter,is_shareable)
            VALUES (:department_id,:resource_id,:name,:size,:provider,:context_window,:api_url,:adapter,:is_shareable)'
        );

        $query->execute([
            'department_id' => $department_id,
            'resource_id' => $resource_id,
            'name' => $name,
            'size' => $size,
            'provider' => $provider,
            'context_window' => $contextWindow,
            'api_url' => $apiUrl,
            'adapter' => $adapter,
            'is_shareable' => $isShareable
        ]);

        $idGenere = $this->pdo->lastInsertId();

        if (!$idGenere) {
            return null;
        }

        $querySelect = $this->pdo->prepare('SELECT * FROM models WHERE id = :id');
        $querySelect->execute(['id' => $idGenere]);

        $result = $querySelect->fetch();

        return $result ?: null;
    }
}