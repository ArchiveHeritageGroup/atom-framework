<?php

namespace AtomFramework\Database;

use Illuminate\Database\Capsule\Manager as DB;

class MigrationRunner
{
    protected string $frameworkMigrationsPath;
    protected string $pluginsPath;
    protected string $migrationsTable = 'atom_framework_migrations';

    public function __construct()
    {
        $this->frameworkMigrationsPath = dirname(__DIR__, 2) . '/database/migrations';
        
        // Detect plugins path based on environment
        $atomRoot = dirname(__DIR__, 3);
        if (is_dir($atomRoot . '/atom-ahg-plugins')) {
            $this->pluginsPath = $atomRoot . '/atom-ahg-plugins';
        } else {
            $this->pluginsPath = '';
        }
    }

    /**
     * Run all pending migrations
     */
    public function migrate(): array
    {
        $this->ensureMigrationsTable();

        $executed = $this->getExecutedMigrations();
        $pending = $this->getPendingMigrations($executed);
        $results = [];

        foreach ($pending as $migration) {
            try {
                $this->runMigration($migration);
                $this->recordMigration($migration['name']);
                $results[] = ['migration' => $migration['name'], 'status' => 'success'];
            } catch (\Exception $e) {
                $results[] = ['migration' => $migration['name'], 'status' => 'failed', 'error' => $e->getMessage()];
                break; // Stop on first failure
            }
        }

        return $results;
    }

    /**
     * Get migration status
     */
    public function status(): array
    {
        $this->ensureMigrationsTable();

        $executed = $this->getExecutedMigrations();
        $all = $this->getAllMigrations();
        $status = [];

        foreach ($all as $migration) {
            $status[] = [
                'migration' => $migration['name'],
                'source' => $migration['source'],
                'executed' => in_array($migration['name'], $executed),
                'executed_at' => $this->getExecutedAt($migration['name'])
            ];
        }

        return $status;
    }

    /**
     * Ensure migrations tracking table exists
     */
    protected function ensureMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        DB::statement($sql);
    }

    /**
     * Get list of already executed migrations
     */
    protected function getExecutedMigrations(): array
    {
        return DB::table($this->migrationsTable)
            ->pluck('migration')
            ->toArray();
    }

    /**
     * Get all available migration files from framework and plugins
     */
    protected function getAllMigrations(): array
    {
        $migrations = [];

        // Framework migrations
        $frameworkFiles = glob($this->frameworkMigrationsPath . '/*.sql');
        foreach ($frameworkFiles as $file) {
            $migrations[] = [
                'name' => pathinfo($file, PATHINFO_FILENAME),
                'path' => $file,
                'source' => 'framework'
            ];
        }

        // Plugin migrations
        if (!empty($this->pluginsPath) && is_dir($this->pluginsPath)) {
            $pluginDirs = glob($this->pluginsPath . '/ahg*Plugin', GLOB_ONLYDIR);
            
            foreach ($pluginDirs as $pluginDir) {
                $pluginName = basename($pluginDir);
                $migrationsDir = $pluginDir . '/data/migrations';
                
                if (is_dir($migrationsDir)) {
                    $pluginFiles = glob($migrationsDir . '/*.sql');
                    
                    foreach ($pluginFiles as $file) {
                        $baseName = pathinfo($file, PATHINFO_FILENAME);
                        // Prefix with plugin name to avoid conflicts
                        $migrations[] = [
                            'name' => $pluginName . ':' . $baseName,
                            'path' => $file,
                            'source' => $pluginName
                        ];
                    }
                }
            }
        }

        // Sort by name
        usort($migrations, fn($a, $b) => strcmp($a['name'], $b['name']));
        
        return $migrations;
    }

    /**
     * Get pending migrations
     */
    protected function getPendingMigrations(array $executed): array
    {
        $all = $this->getAllMigrations();
        return array_filter($all, fn($m) => !in_array($m['name'], $executed));
    }

    /**
     * Validate migration is safe (no destructive operations)
     */
    protected function validateMigration(string $sql, string $name): void
    {
        $dangerous = [
            'DROP TABLE',
            'DROP DATABASE',
            'TRUNCATE TABLE',
            'DELETE FROM' => false, // Allow DELETE with WHERE
        ];
        
        $upperSql = strtoupper($sql);
        
        // Check for DROP statements
        if (preg_match('/DROP\s+(TABLE|DATABASE|INDEX)/i', $sql)) {
            throw new \Exception(
                "Migration '{$name}' contains DROP statement. " .
                "Migrations must be non-destructive. Use CREATE TABLE IF NOT EXISTS, " .
                "ALTER TABLE, INSERT IGNORE, or UPDATE instead."
            );
        }
        
        // Check for TRUNCATE
        if (preg_match('/TRUNCATE\s+TABLE/i', $sql)) {
            throw new \Exception(
                "Migration '{$name}' contains TRUNCATE statement. " .
                "Migrations must preserve existing data."
            );
        }
        
        // DELETE without WHERE is dangerous
        if (preg_match('/DELETE\s+FROM\s+\w+\s*;/i', $sql)) {
            throw new \Exception(
                "Migration '{$name}' contains DELETE without WHERE clause. " .
                "Always specify conditions for DELETE statements."
            );
        }
    }

    /**
     * Run a single migration
     */
    protected function runMigration(array $migration): void
    {
        $file = $migration['path'];

        if (!file_exists($file)) {
            throw new \Exception("Migration file not found: {$file}");
        }

        $sql = file_get_contents($file);
        
        // Validate migration is safe
        $this->validateMigration($sql, $migration['name']);

        // Parse and run statements
        $statements = $this->parseSqlStatements($sql);

        foreach ($statements as $statement) {
            if (!empty(trim($statement))) {
                DB::unprepared($statement);
            }
        }
    }

    /**
     * Parse SQL file into individual statements
     * Handles SET variables, INSERT, and complex statements
     */
    protected function parseSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $lines = explode("\n", $sql);
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            // Skip empty lines and comments
            if (empty($trimmed) || str_starts_with($trimmed, '--')) {
                continue;
            }
            
            $current .= $line . "\n";
            
            // Check if statement is complete (ends with ;)
            if (str_ends_with($trimmed, ';')) {
                $statements[] = trim($current);
                $current = '';
            }
        }
        
        // Add any remaining content
        if (!empty(trim($current))) {
            $statements[] = trim($current);
        }
        
        return $statements;
    }

    /**
     * Record migration as executed
     */
    protected function recordMigration(string $migration): void
    {
        DB::table($this->migrationsTable)->insert([
            'migration' => $migration,
            'executed_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get when a migration was executed
     */
    protected function getExecutedAt(string $migration): ?string
    {
        $record = DB::table($this->migrationsTable)
            ->where('migration', $migration)
            ->first();

        return $record->executed_at ?? null;
    }
}
