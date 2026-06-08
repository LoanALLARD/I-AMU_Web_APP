<?php

namespace Models;

use PDO;

/*
 * This class use PDO to recover 
 * all data about AI in the database
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
    public function findAllActiveBySession(?int $sessionID): array
    {
        if ($sessionID === null) {
            $query = $this->pdo->prepare(
                'SELECT id, name, size, context_window, is_active
           FROM models WHERE is_active = TRUE ORDER BY name' // all active models
            );
            $query->execute();
        } else {
            $query = $this->pdo->prepare(
                'SELECT id, name, size, context_window, is_active
                   FROM models WHERE is_active = TRUE AND resource_id = :r_id ORDER BY name'
            );
            $query->execute(['r_id' => $sessionID]);
        }

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
        $params = [];
        foreach ($ids as $i => $id) {
            $key  = ':id' . $i;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $sql   = 'SELECT id, name, size, context_window, is_active FROM models WHERE id IN (' . implode(', ', $placeholders) . ') ORDER BY name';
        $query = $this->pdo->prepare($sql);
        $query->execute($params);

        /** @var list<array<string, mixed>> $rows */
        $rows = $query->fetchAll();

        return $rows;
    }

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
    public function addModel(?int $department_id,?string $resource_id,string $name, string $size, string $provider, string $adapter, string $apiUrl, int $maxTokens, int $contextWindow, string $isShareable)
    {
        $query = $this->pdo->prepare(
            'INSERT INTO models (department_id,resource_id,name,size,provider,max_tokens,context_window,api_url,adapter,is_shareable)
            VALUES (:department_id,:resource_id,:name,:size,:provider,:max_tokens,:context_window,:api_url,:adapter,:is_shareable)'
        );

        $query->execute([
            'department_id' => $department_id,
            'resource_id'    => $resource_id,
            'name'           => $name,
            'size'           => $size,
            'provider'       => $provider,
            'max_tokens'     => $maxTokens,
            'context_window' => $contextWindow,
            'api_url'        => $apiUrl,
            'adapter'        => $adapter,
            'is_shareable'   => $isShareable
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