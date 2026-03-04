<?php

/**
 * QubitPdo — Standalone PDO wrapper (Symfony-free).
 *
 * Drop-in replacement for lib/QubitPdo.class.php that uses Laravel's
 * database connection instead of Propel::getConnection().
 *
 * All static methods behave identically to the original:
 *   QubitPdo::fetchAll($sql, $params)
 *   QubitPdo::fetchOne($sql, $params)
 *   QubitPdo::fetchColumn($sql, $params, $col)
 *   QubitPdo::modify($sql, $params)
 *   QubitPdo::prepare($sql)
 *   QubitPdo::prepareAndExecute($sql, $params)
 *   QubitPdo::lastInsertId()
 *
 * Connection resolution order:
 *   1. Laravel Capsule (if booted)
 *   2. Propel (if available — legacy fallback)
 *   3. Raw PDO from config/config.php
 *
 * Original: Vic Cherubini / David Juhasz (Artefactual Systems)
 * Standalone adaptation: AHG Framework
 */
class QubitPdo
{
    protected static $conn;

    public static function fetchAll($query, $parameters = [], $options = [])
    {
        $readStmt = self::prepareAndExecute($query, $parameters);

        $fetchMode = isset($options['fetchMode']) ? $options['fetchMode'] : \PDO::FETCH_CLASS;
        $fetchedRows = $readStmt->fetchAll($fetchMode);

        $readStmt->closeCursor();
        unset($readStmt);

        return $fetchedRows;
    }

    public static function fetchOne($query, $parameters = [])
    {
        $readStmt = self::prepareAndExecute($query, $parameters);

        $fetchedRow = $readStmt->fetchObject();
        if (!is_object($fetchedRow)) {
            $fetchedRow = false;
        }

        $readStmt->closeCursor();
        unset($readStmt);

        return $fetchedRow;
    }

    public static function fetchColumn($query, $parameters = [], $column = 0)
    {
        $column = abs((int) $column);

        $readStmt = self::prepareAndExecute($query, $parameters);
        $fetchedColumn = $readStmt->fetchColumn($column);

        $readStmt->closeCursor();
        unset($readStmt);

        return $fetchedColumn;
    }

    public static function modify($query, $parameters = [])
    {
        $modifyStmt = self::prepareAndExecute($query, $parameters);

        return $modifyStmt->rowCount();
    }

    public static function prepare($query)
    {
        if (!isset(self::$conn)) {
            self::$conn = self::resolveConnection();
        }

        return self::$conn->prepare($query);
    }

    public static function prepareAndExecute($query, $parameters = [])
    {
        $prepStmt = self::prepare($query);
        $prepStmt->execute($parameters);

        return $prepStmt;
    }

    public static function lastInsertId()
    {
        if (!isset(self::$conn)) {
            self::$conn = self::resolveConnection();
        }

        return self::$conn->lastInsertId();
    }

    /**
     * Resolve a PDO connection from the best available source.
     */
    private static function resolveConnection(): \PDO
    {
        // 1. Laravel Capsule (preferred)
        if (class_exists(\Illuminate\Database\Capsule\Manager::class, false)) {
            try {
                return \Illuminate\Database\Capsule\Manager::connection()->getPdo();
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        // 2. Propel (legacy fallback)
        if (class_exists('Propel', false) && \Propel::isInit()) {
            return \Propel::getConnection();
        }

        // 3. Raw PDO from config/config.php
        $rootDir = defined('ATOM_ROOT_PATH') ? ATOM_ROOT_PATH : (
            class_exists('\sfConfig', false) ? \sfConfig::get('sf_root_dir', '') : ''
        );

        if ($rootDir) {
            $dbConfig = \AtomFramework\Services\ConfigService::parseDbConfig($rootDir);
            if ($dbConfig) {
                return new \PDO(
                    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbConfig['host'], $dbConfig['port'], $dbConfig['database']),
                    $dbConfig['username'],
                    $dbConfig['password'],
                    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
                );
            }
        }

        throw new \RuntimeException('QubitPdo: No database connection available. Boot Laravel Capsule or Propel first.');
    }

    /**
     * Reset the cached connection (for testing or reconnection).
     */
    public static function reset(): void
    {
        self::$conn = null;
    }
}
