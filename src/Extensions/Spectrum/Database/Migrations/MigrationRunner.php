<?php

namespace AtomFramework\Extensions\Spectrum\Database\Migrations;

use AtomFramework\Core\Database\DatabaseManager;
use Psr\Log\LoggerInterface;

/**
 * Spectrum Migration Runner
 * 
 * Manages database migrations for the Spectrum extension with rollback support
 * 
 * @package AtomFramework\Extensions\Spectrum
 * @author Johan Pieterse - The Archive and Heritage Group
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

    /**
     * Register all available migrations.
     */
    private function registerMigrations(): void
    {
        $this->migrations = [
            'create_spectrum_tables' => CreateSpectrumTables::class,
        ];
    }

    /**
     * Run all pending migrations.
     */
    public function runAll(): array
    {
        $this->logger->info('Running all Spectrum module migrations');
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
            ]);
            return false;
        }
    }

    /**
     * Rollback a specific migration.
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
            ]);
            return false;
        }
    }

    /**
     * Rollback all migrations (in reverse order).
     */
    public function rollbackAll(): array
    {
        $this->logger->info('Rolling back all Spectrum module migrations');
        $results = [];

        $migrations = array_reverse($this->migrations, true);

        foreach ($migrations as $name => $class) {
            try {
                $migration = new $class($this->db, $this->logger);

                if (!$migration->hasRun()) {
                    $this->logger->info("Migration {$name} not run, skipping rollback");
                    $results[$name] = 'skipped';
                    continue;
                }

                $this->logger->info("Rolling back migration: {$name}");
                $migration->down();
                $results[$name] = 'rolled_back';
            } catch (\Exception $e) {
                $this->logger->error("Rollback of {$name} failed", [
                    'error' => $e->getMessage(),
                ]);
                $results[$name] = 'failed: '.$e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Get status of all migrations.
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
