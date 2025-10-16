<?php
namespace System\Database;

class DB
{
    /**
     * Get a new QueryBuilder instance for a specific table
     */
    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder($table);
    }

    /**
     * Run raw query directly
     */
    public static function raw(string $query, array $bindings = []): array|bool
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare($query);
        $success = $stmt->execute($bindings);

        // যদি SELECT query হয়, তাহলে result return করবে
        if (str_starts_with(strtolower(trim($query)), 'select')) {
            return $stmt->fetchAll();
        }

        // অন্যথায় true/false return করবে (insert/update/delete)
        return $success;
    }
}
