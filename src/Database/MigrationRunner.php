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
                break;
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
                'type' => $migration['type'] ?? 'sql',
                'executed' => in_array($migration['name'], $executed),
                'executed_at' => $this->getExecutedAt($migration['name'])
            ];
        }
        return $status;
    }

    protected function ensureMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        DB::statement($sql);
    }

    protected function getExecutedMigrations(): array
    {
        return DB::table($this->migrationsTable)->pluck('migration')->toArray();
    }

    protected function getExecutedAt(string $name): ?string
    {
        $record = DB::table($this->migrationsTable)->where('migration', $name)->first();
        return $record->executed_at ?? null;
    }

    protected function recordMigration(string $name): void
    {
        DB::table($this->migrationsTable)->insert([
            'migration' => $name,
            'executed_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get all available migration files from framework and plugins
     */
    protected function getAllMigrations(): array
    {
        $migrations = [];

        // Framework migrations - SQL files
        foreach (glob($this->frameworkMigrationsPath . '/*.sql') as $file) {
            $migrations[] = [
                'name' => pathinfo($file, PATHINFO_FILENAME),
                'path' => $file,
                'source' => 'framework',
                'type' => 'sql'
            ];
        }

        // Framework migrations - PHP files
        foreach (glob($this->frameworkMigrationsPath . '/*.php') as $file) {
            $migrations[] = [
                'name' => pathinfo($file, PATHINFO_FILENAME),
                'path' => $file,
                'source' => 'framework',
                'type' => 'php'
            ];
        }

        // Plugin migrations
        if (!empty($this->pluginsPath) && is_dir($this->pluginsPath)) {
            foreach (glob($this->pluginsPath . '/ahg*Plugin', GLOB_ONLYDIR) as $pluginDir) {
                $pluginName = basename($pluginDir);
                $migrationsDir = $pluginDir . '/data/migrations';
                
                if (is_dir($migrationsDir)) {
                    foreach (glob($migrationsDir . '/*.sql') as $file) {
                        $migrations[] = [
                            'name' => $pluginName . ':' . pathinfo($file, PATHINFO_FILENAME),
                            'path' => $file,
                            'source' => $pluginName,
                            'type' => 'sql'
                        ];
                    }
                    foreach (glob($migrationsDir . '/*.php') as $file) {
                        $migrations[] = [
                            'name' => $pluginName . ':' . pathinfo($file, PATHINFO_FILENAME),
                            'path' => $file,
                            'source' => $pluginName,
                            'type' => 'php'
                        ];
                    }
                }
            }
        }

        usort($migrations, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $migrations;
    }

    protected function getPendingMigrations(array $executed): array
    {
        $all = $this->getAllMigrations();
        return array_filter($all, fn($m) => !in_array($m['name'], $executed));
    }

    /**
     * Validate SQL migration is safe
     */
    protected function validateSqlMigration(string $sql, string $name): void
    {
        if (preg_match('/DROP\s+(TABLE|DATABASE|INDEX)/i', $sql)) {
            throw new \Exception("Migration '{$name}' contains DROP statement.");
        }
        if (preg_match('/TRUNCATE\s+TABLE/i', $sql)) {
            throw new \Exception("Migration '{$name}' contains TRUNCATE statement.");
        }
        if (preg_match('/DELETE\s+FROM\s+\w+\s*;/i', $sql)) {
            throw new \Exception("Migration '{$name}' contains DELETE without WHERE.");
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

        $type = $migration['type'] ?? 'sql';

        if ($type === 'php') {
            $this->runPhpMigration($file, $migration['name']);
        } else {
            $this->runSqlMigration($file, $migration['name']);
        }
    }

    /**
     * Run SQL migration
     */
    protected function runSqlMigration(string $file, string $name): void
    {
        $sql = file_get_contents($file);
        $this->validateSqlMigration($sql, $name);
        
        $statements = $this->parseSqlStatements($sql);
        foreach ($statements as $statement) {
            if (!empty(trim($statement))) {
                try {
                    DB::statement($statement);
                } catch (\Exception $e) {
                    // Ignore safe errors (column/table already exists, etc.)
                    $safeErrors = [
                        '42S21', // Column already exists
                        '42S01', // Table already exists
                        '42000', // Duplicate key name
                        '1060',  // Duplicate column name
                        '1061',  // Duplicate key name
                        '1050',  // Table already exists
                    ];
                    
                    $isSafe = false;
                    foreach ($safeErrors as $code) {
                        if (strpos($e->getMessage(), $code) !== false || 
                            strpos($e->getMessage(), 'already exists') !== false ||
                            strpos($e->getMessage(), 'Duplicate') !== false) {
                            $isSafe = true;
                            break;
                        }
                    }
                    
                    if (!$isSafe) {
                        throw $e;
                    }
                    // Safe error - continue with next statement
                }
            }
        }
    }

    /**
     * Run PHP migration
     */
    protected function runPhpMigration(string $file, string $name): void
    {
        $content = file_get_contents($file);
        
        // Check if file returns an anonymous class
        if (strpos($content, 'return new class') !== false) {
            $migration = require $file;
            if (is_object($migration) && method_exists($migration, 'up')) {
                $migration->up();
                return;
            }
            throw new \Exception("Anonymous migration class must have an up() method: {$name}");
        }

        // Extract class name from file content
        if (preg_match('/^class\s+(\w+)/m', $content, $matches)) {
            $className = $matches[1];
        } else {
            // Fallback: derive from filename
            $fileName = pathinfo($file, PATHINFO_FILENAME);
            $parts = explode('_', $fileName);
            
            // Remove date prefix
            if (count($parts) > 3 && strlen($parts[0]) === 4 && is_numeric($parts[0]) && (int)$parts[0] > 2000) {
                $parts = array_slice($parts, 3);
                if (count($parts) > 0 && is_numeric($parts[0])) {
                    $parts = array_slice($parts, 1);
                }
            } elseif (count($parts) > 1 && is_numeric($parts[0])) {
                $parts = array_slice($parts, 1);
            }
            
            $className = str_replace(' ', '', ucwords(implode(' ', $parts)));
        }

        require_once $file;

        // Try namespaced class first
        $possibleClasses = [
            "\\AtomExtensions\\Migrations\\{$className}",
            "\\AtomFramework\\Migrations\\{$className}",
            $className
        ];

        $migrationClass = null;
        foreach ($possibleClasses as $class) {
            if (class_exists($class)) {
                $migrationClass = $class;
                break;
            }
        }

        if (!$migrationClass) {
            throw new \Exception("Could not find migration class for: {$name} (tried: " . implode(', ', $possibleClasses) . ")");
        }

        // Check for static up() or instance up()
        if (method_exists($migrationClass, 'up')) {
            $reflection = new \ReflectionMethod($migrationClass, 'up');
            if ($reflection->isStatic()) {
                $migrationClass::up();
            } else {
                $instance = new $migrationClass();
                $instance->up();
            }
        } else {
            throw new \Exception("Migration class {$migrationClass} must have an up() method");
        }
    }
    protected function parseSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $lines = explode("\n", $sql);

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed) || str_starts_with($trimmed, '--')) {
                continue;
            }
            $current .= $line . "\n";
            if (str_ends_with($trimmed, ';')) {
                $statements[] = trim($current);
                $current = '';
            }
        }
        if (!empty(trim($current))) {
            $statements[] = trim($current);
        }
        return $statements;
    }
}
