<?php

namespace AtomFramework\Database;

use Illuminate\Database\Capsule\Manager as DB;

class MigrationRunner
{
    protected string $migrationsPath;
    protected string $migrationsTable = 'atom_framework_migrations';
    
    public function __construct()
    {
        $this->migrationsPath = dirname(__DIR__, 2) . '/database/migrations';
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
                $this->recordMigration($migration);
                $results[] = ['migration' => $migration, 'status' => 'success'];
            } catch (\Exception $e) {
                $results[] = ['migration' => $migration, 'status' => 'failed', 'error' => $e->getMessage()];
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
                'migration' => $migration,
                'executed' => in_array($migration, $executed),
                'executed_at' => $this->getExecutedAt($migration)
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
     * Get all available migration files
     */
    protected function getAllMigrations(): array
    {
        $files = glob($this->migrationsPath . '/*.sql');
        $migrations = [];
        
        foreach ($files as $file) {
            $migrations[] = pathinfo($file, PATHINFO_FILENAME);
        }
        
        sort($migrations);
        return $migrations;
    }
    
    /**
     * Get pending migrations
     */
    protected function getPendingMigrations(array $executed): array
    {
        $all = $this->getAllMigrations();
        return array_diff($all, $executed);
    }
    
    /**
     * Run a single migration
     */
    protected function runMigration(string $migration): void
    {
        $file = $this->migrationsPath . '/' . $migration . '.sql';
        
        if (!file_exists($file)) {
            throw new \Exception("Migration file not found: {$file}");
        }
        
        $sql = file_get_contents($file);
        
        // Split by semicolon and run each statement
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($s) => !empty($s) && !str_starts_with($s, '--')
        );
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                DB::statement($statement);
            }
        }
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
