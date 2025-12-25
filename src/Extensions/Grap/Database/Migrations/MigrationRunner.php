<?php

namespace AtomFramework\Extensions\Grap\Database\Migrations;

use AtomFramework\Core\Database\DatabaseManager;
use Psr\Log\LoggerInterface;

/**
 * GRAP Migration Runner
 */
class MigrationRunner
{
    private DatabaseManager $db;
    private LoggerInterface $logger;
    private array $migrations = [];

    public function __construct(DatabaseManager $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->registerMigrations();
    }

    private function registerMigrations(): void
    {
        $this->migrations = [
            'create_grap_tables' => CreateGrapTables::class,
        ];
    }

    public function runAll(): array
    {
        $this->logger->info('Running all GRAP module migrations');
        $results = [];

        foreach ($this->migrations as $name => $class) {
            try {
                $migration = new $class($this->db, $this->logger);

                if ($migration->hasRun()) {
                    $results[$name] = 'skipped';
                    continue;
                }

                $migration->up();
                $results[$name] = 'success';
            } catch (\Exception $e) {
                $this->logger->error("Migration {$name} failed", ['error' => $e->getMessage()]);
                $results[$name] = 'failed: '.$e->getMessage();
            }
        }

        return $results;
    }

    public function rollbackAll(): array
    {
        $this->logger->info('Rolling back all GRAP module migrations');
        $results = [];

        $migrations = array_reverse($this->migrations, true);

        foreach ($migrations as $name => $class) {
            try {
                $migration = new $class($this->db, $this->logger);

                if (!$migration->hasRun()) {
                    $results[$name] = 'skipped';
                    continue;
                }

                $migration->down();
                $results[$name] = 'rolled_back';
            } catch (\Exception $e) {
                $this->logger->error("Rollback of {$name} failed", ['error' => $e->getMessage()]);
                $results[$name] = 'failed: '.$e->getMessage();
            }
        }

        return $results;
    }

    public function getStatus(): array
    {
        $status = [];

        foreach ($this->migrations as $name => $class) {
            try {
                $migration = new $class($this->db, $this->logger);
                $status[$name] = $migration->hasRun() ? 'run' : 'pending';
            } catch (\Exception $e) {
                $status[$name] = 'error: '.$e->getMessage();
            }
        }

        return $status;
    }
}
