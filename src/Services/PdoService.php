<?php
declare(strict_types=1);
namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * PDO Service - Replaces QubitPdo
 * Uses Laravel Query Builder but provides PDO-style interface
 */
class PdoService
{
    /**
     * Fetch all rows
     */
    public static function fetchAll(string $sql, array $params = [], array $options = []): array
    {
        $fetchMode = $options['fetchMode'] ?? \PDO::FETCH_OBJ;
        
        $results = DB::select($sql, $params);
        
        if ($fetchMode === \PDO::FETCH_ASSOC) {
            return array_map(fn($r) => (array) $r, $results);
        }
        
        return $results;
    }

    /**
     * Fetch single column value
     */
    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        $result = DB::selectOne($sql, $params);
        if ($result) {
            $arr = (array) $result;
            return reset($arr);
        }
        return null;
    }

    /**
     * Fetch single row
     */
    public static function fetchOne(string $sql, array $params = []): ?object
    {
        return DB::selectOne($sql, $params);
    }

    /**
     * Execute statement
     */
    public static function execute(string $sql, array $params = []): bool
    {
        return DB::statement($sql, $params);
    }

    /**
     * Get raw PDO connection (for edge cases)
     */
    public static function getConnection(): \PDO
    {
        return DB::connection()->getPdo();
    }
}
