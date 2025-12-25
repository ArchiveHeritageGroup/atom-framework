<?php

namespace AtomFramework\Museum\Database\Migrations;

use AtomFramework\Core\Database\DatabaseManager;
use Psr\Log\LoggerInterface;

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

    /**
     * Register all available migrations.
     *
     * @return void
     */
    private function registerMigrations(): void
    {
        $this->migrations = [
            'create_museum_object_properties_table' => CreateMuseumObjectPropertiesTable::class,
        ];
    }

    /**
     * Run all pending migrations.
     *
     * @return array Results of each migration
     */
    public function runAll(): array
    {
        $this->logger->info('Running all museum module migrations');
        $results = [];

        foreach ($this->migrations as $name => $class) {
            try {
                $migration = new $class($this->db, $this->logger);

                if ($migration->hasRun()) {
                    $this->logger->info("Migration {$name} already run, skipping");
                    $results[$name] = 'skipped';
                    continue;
                }

                $this->logger->info("Running migration: {$name}");
                $migration->up();
                $results[$name] = 'success';
            } catch (\Exception $e) {
                $this->logger->error("Migration {$name} failed", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $results[$name] = 'failed: '.$e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Run a specific migration.
     *
     * @param string $name Migration name
     *
     * @return bool Success status
     */
    public function run(string $name): bool
    {
        if (!isset($this->migrations[$name])) {
            $this->logger->error("Unknown migration: {$name}");

            return false;
        }

        try {
            $class = $this->migrations[$name];
            $migration = new $class($this->db, $this->logger);

            if ($migration->hasRun()) {
                $this->logger->info("Migration {$name} already run");

                return true;
            }

            $this->logger->info("Running migration: {$name}");
            $migration->up();

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Migration {$name} failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Rollback a specific migration.
     *
     * @param string $name Migration name
     *
     * @return bool Success status
     */
    public function rollback(string $name): bool
    {
        if (!isset($this->migrations[$name])) {
            $this->logger->error("Unknown migration: {$name}");

            return false;
        }

        try {
            $class = $this->migrations[$name];
            $migration = new $class($this->db, $this->logger);

            if (!$migration->hasRun()) {
                $this->logger->info("Migration {$name} not run, nothing to rollback");

                return true;
            }

            $this->logger->info("Rolling back migration: {$name}");
            $migration->down();

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Rollback of {$name} failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Get status of all migrations.
     *
     * @return array Migration statuses
     */
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
