<?php

namespace App\Repositories;

use PDO;

// Shared PDO access. All queries use prepared statements.
abstract class BaseRepository
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    protected function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function transaction(callable $fn)
    {
        $this->db->beginTransaction();
        try {
            $result = $fn();
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // Escapes LIKE wildcards in user search input.
    protected static function like(string $term): string
    {
        return '%' . addcslashes($term, '%_\\\\') . '%';
    }
}
