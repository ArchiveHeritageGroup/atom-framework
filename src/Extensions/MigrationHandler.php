<?php

namespace AtomFramework\Extensions;

use Illuminate\Database\Capsule\Manager as DB;

class MigrationHandler
{
    protected string $pluginsPath;

    public function __construct(string $pluginsPath = '/usr/share/nginx/atom/plugins')
    {
        $this->pluginsPath = $pluginsPath;
    }

    /**
     * Check if extension has migrations
     */
    public function hasMigrations(string $machineName): bool
    {
        $migrationsPath = "{$this->pluginsPath}/{$machineName}/database/migrations";
        
        if (!is_dir($migrationsPath)) {
            return false;
        }

        $files = glob("{$migrationsPath}/*.php");
        return !empty($files);
    }

    /**
     * Check if extension has SQL file
     */
    public function hasSqlFile(string $machineName): bool
    {
        $sqlPath = "{$this->pluginsPath}/{$machineName}/database/install.sql";
        return file_exists($sqlPath);
    }

    /**
     * Get migration files for extension
     */
    public function getMigrationFiles(string $machineName): array
    {
        $migrationsPath = "{$this->pluginsPath}/{$machineName}/database/migrations";
        
        if (!is_dir($migrationsPath)) {
            return [];
        }

        $files = glob("{$migrationsPath}/*.php");
        sort($files);
        return $files;
    }

    /**
     * Run migrations for extension
     */
    public function runMigrations(string $machineName): array
    {
        $results = ['success' => [], 'failed' => [], 'skipped' => []];
        $files = $this->getMigrationFiles($machineName);

        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file);
            
            if (!$className) {
                $results['skipped'][] = basename($file) . ' (no class found)';
                continue;
            }

            require_once $file;

            if (!class_exists($className)) {
                $results['skipped'][] = basename($file) . " (class {$className} not found)";
                continue;
            }

            try {
                $migration = new $className();
                
                if (method_exists($migration, 'up')) {
                    $migration->up();
                    $results['success'][] = basename($file);
                }
            } catch (\Exception $e) {
                // Check if it's a "table already exists" error
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    $results['skipped'][] = basename($file) . ' (tables exist)';
                } else {
                    $results['failed'][] = basename($file) . ': ' . $e->getMessage();
                }
            }
        }

        return $results;
    }

    /**
     * Run SQL file for extension
     */
    public function runSqlFile(string $machineName): bool
    {
        $sqlPath = "{$this->pluginsPath}/{$machineName}/database/install.sql";
        
        if (!file_exists($sqlPath)) {
            return false;
        }

        $sql = file_get_contents($sqlPath);
        
        try {
            DB::unprepared($sql);
            return true;
        } catch (\Exception $e) {
            throw new \RuntimeException("SQL execution failed: " . $e->getMessage());
        }
    }

    /**
     * Get SQL file path for manual installation
     */
    public function getSqlFilePath(string $machineName): ?string
    {
        $sqlPath = "{$this->pluginsPath}/{$machineName}/database/install.sql";
        return file_exists($sqlPath) ? $sqlPath : null;
    }

    /**
     * Rollback migrations
     */
    public function rollbackMigrations(string $machineName): array
    {
        $results = ['success' => [], 'failed' => []];
        $files = array_reverse($this->getMigrationFiles($machineName));

        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file);
            
            if (!$className) {
                continue;
            }

            require_once $file;

            if (!class_exists($className)) {
                continue;
            }

            try {
                $migration = new $className();
                
                if (method_exists($migration, 'down')) {
                    $migration->down();
                    $results['success'][] = basename($file);
                }
            } catch (\Exception $e) {
                $results['failed'][] = basename($file) . ': ' . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Extract class name from migration file
     */
    protected function getClassNameFromFile(string $file): ?string
    {
        $content = file_get_contents($file);
        
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Check if required tables exist
     */
    public function tablesExist(array $tables): bool
    {
        foreach ($tables as $table) {
            try {
                $exists = DB::select("SHOW TABLES LIKE ?", [$table]);
                if (empty($exists)) {
                    return false;
                }
            } catch (\Exception $e) {
                return false;
            }
        }
        return true;
    }
}
