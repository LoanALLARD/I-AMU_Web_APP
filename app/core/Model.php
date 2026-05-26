<?php

namespace App\Core;

/**
 * Modèle de base.
 * Fournit les opérations CRUD communes pour toutes les entités.
 */
abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';

    protected function db(): Database
    {
        return Database::getInstance();
    }

    /**
     * Trouve un enregistrement par son ID.
     */
    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM \"{$this->table}\" WHERE {$this->primaryKey} = :id";
        $stmt = $this->db()->query($sql, ['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Retourne tous les enregistrements.
     */
    public function findAll(string $orderBy = '', int $limit = 0): array
    {
        $sql = "SELECT * FROM \"{$this->table}\"";
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }
        if ($limit > 0) {
            $sql .= " LIMIT $limit";
        }
        return $this->db()->query($sql)->fetchAll();
    }

    /**
     * Trouve des enregistrements selon des conditions.
     */
    public function findBy(array $conditions, string $orderBy = ''): array
    {
        $where = [];
        $params = [];
        foreach ($conditions as $column => $value) {
            $where[] = "$column = :$column";
            $params[$column] = $value;
        }

        $sql = "SELECT * FROM \"{$this->table}\" WHERE " . implode(' AND ', $where);
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }

        return $this->db()->query($sql, $params)->fetchAll();
    }

    /**
     * Trouve un seul enregistrement selon des conditions.
     */
    public function findOneBy(array $conditions): ?array
    {
        $results = $this->findBy($conditions);
        return $results[0] ?? null;
    }

    /**
     * Insère un nouvel enregistrement.
     */
    public function create(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO \"{$this->table}\" ($columns) VALUES ($placeholders)";
        $this->db()->query($sql, $data);

        $sequence = $this->table . '_' . $this->primaryKey . '_seq';
        return (int) $this->db()->lastInsertId($sequence);
    }

    /**
     * Met à jour un enregistrement.
     */
    public function update(int $id, array $data): bool
    {
        $set = [];
        $params = ['id' => $id];
        foreach ($data as $column => $value) {
            $set[] = "$column = :$column";
            $params[$column] = $value;
        }

        $sql = "UPDATE \"{$this->table}\" SET " . implode(', ', $set) . " WHERE {$this->primaryKey} = :id";
        $stmt = $this->db()->query($sql, $params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Supprime un enregistrement.
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM \"{$this->table}\" WHERE {$this->primaryKey} = :id";
        $stmt = $this->db()->query($sql, ['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Compte les enregistrements.
     */
    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM \"{$this->table}\"";
        $params = [];

        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $column => $value) {
                $where[] = "$column = :$column";
                $params[$column] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $result = $this->db()->query($sql, $params)->fetch();
        return (int) $result['total'];
    }
}
