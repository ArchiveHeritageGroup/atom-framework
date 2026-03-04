<?php

/**
 * Propel ORM Shim — Standalone replacement for Propel 1.4.
 *
 * Provides the Propel static API that AtoM code calls, backed by
 * Laravel's database connection. This allows code using
 * Propel::getConnection() to work without the full Propel ORM.
 *
 * Shimmed methods:
 *   Propel::getConnection()      → Returns Laravel's PDO
 *   Propel::isInit()             → true when Laravel Capsule is booted
 *   Propel::initialize()         → no-op (Capsule handles init)
 *   Propel::setConfiguration()   → no-op
 *   Propel::setDefaultDB()       → no-op
 *   Propel::configure()          → no-op
 *   Propel::getDatabaseMap()     → returns stub DatabaseMap
 *   Propel::close()              → no-op
 *
 * NOT shimmed (not needed in standalone mode):
 *   Propel::getDB()              → Propel adapter (unused in AHG code)
 *   Propel::getPeer()            → Propel peer (unused in AHG code)
 */
class Propel
{
    private static bool $initialized = false;

    /**
     * Get a PDO connection from Laravel Capsule.
     *
     * @param string|null $name Connection name (ignored — single DB)
     *
     * @return \PDO
     */
    public static function getConnection($name = null)
    {
        if (class_exists(\Illuminate\Database\Capsule\Manager::class, false)) {
            return \Illuminate\Database\Capsule\Manager::connection()->getPdo();
        }

        throw new \RuntimeException(
            'Propel::getConnection() — Laravel Capsule not booted. '
            . 'Call Kernel::boot() or bootstrap.php first.'
        );
    }

    /**
     * Check whether the ORM layer is initialized.
     */
    public static function isInit(): bool
    {
        if (self::$initialized) {
            return true;
        }

        // Consider initialized if Laravel Capsule has a connection
        if (class_exists(\Illuminate\Database\Capsule\Manager::class, false)) {
            try {
                \Illuminate\Database\Capsule\Manager::connection()->getPdo();
                self::$initialized = true;

                return true;
            } catch (\Throwable $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Initialize Propel (no-op in standalone — Capsule handles this).
     */
    public static function initialize(): void
    {
        self::$initialized = true;
    }

    /**
     * Set Propel configuration (no-op in standalone).
     */
    public static function setConfiguration($config): void
    {
        self::$initialized = true;
    }

    /**
     * Set default database name (no-op in standalone).
     */
    public static function setDefaultDB(string $name): void
    {
        // no-op
    }

    /**
     * Configure Propel (no-op in standalone).
     */
    public static function configure(string $configFile = null): void
    {
        // no-op
    }

    /**
     * Close all connections (no-op in standalone — Capsule manages lifecycle).
     */
    public static function close(): void
    {
        // no-op
    }

    /**
     * Get the database map (stub).
     *
     * Only used by CsvImportCommand for schema introspection.
     * Returns a minimal stub that provides getTable().
     */
    public static function getDatabaseMap($name = null)
    {
        return new class {
            public function getTable($tableName)
            {
                return new class($tableName) {
                    private string $name;
                    private array $columns = [];

                    public function __construct(string $name)
                    {
                        $this->name = $name;
                        $this->loadColumns();
                    }

                    public function getName(): string
                    {
                        return $this->name;
                    }

                    public function hasColumn(string $col): bool
                    {
                        return isset($this->columns[strtolower($col)]);
                    }

                    public function getColumn(string $col)
                    {
                        return $this->columns[strtolower($col)] ?? null;
                    }

                    public function getColumns(): array
                    {
                        return $this->columns;
                    }

                    private function loadColumns(): void
                    {
                        if (!class_exists(\Illuminate\Database\Capsule\Manager::class, false)) {
                            return;
                        }

                        try {
                            $rows = \Illuminate\Database\Capsule\Manager::select(
                                'SHOW COLUMNS FROM ' . $this->name
                            );
                            foreach ($rows as $row) {
                                $field = $row->Field ?? $row->field ?? '';
                                $this->columns[strtolower($field)] = new class($field) {
                                    private string $name;

                                    public function __construct(string $name)
                                    {
                                        $this->name = $name;
                                    }

                                    public function getName(): string
                                    {
                                        return $this->name;
                                    }

                                    public function getPhpName(): string
                                    {
                                        return $this->name;
                                    }

                                    public function getFullyQualifiedName(): string
                                    {
                                        return $this->name;
                                    }
                                };
                            }
                        } catch (\Throwable $e) {
                            // Table may not exist — return empty columns
                        }
                    }
                };
            }
        };
    }

    /**
     * Mark as initialized (used by PropelBridge when it boots real Propel).
     */
    public static function markInitialized(): void
    {
        self::$initialized = true;
    }
}
