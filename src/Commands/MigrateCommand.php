<?php

namespace AtomFramework\Commands;

use Illuminate\Database\Capsule\Manager as DB;

class MigrateCommand
{
    private string $frameworkPath;
    private string $pluginsPath;

    public function __construct()
    {
        $this->frameworkPath = sfConfig::get('sf_root_dir') . '/atom-framework';
        $this->pluginsPath = sfConfig::get('sf_root_dir') . '/atom-ahg-plugins';
    }

    public function run(array $options = []): void
    {
        echo "Running migrations...\n";

        // Get current batch
        $batch = (int) DB::table('atom_migration')->max('batch') + 1;

        // Run framework migrations
        $this->runMigrationsFrom($this->frameworkPath . '/database/migrations', 'framework', $batch);

        // Run plugin migrations
        $plugins = glob($this->pluginsPath . '/ahg*Plugin', GLOB_ONLYDIR);
        foreach ($plugins as $pluginPath) {
            $pluginName = basename($pluginPath);
            $migrationsPath = $pluginPath . '/data/migrations';
            if (is_dir($migrationsPath)) {
                $this->runMigrationsFrom($migrationsPath, $pluginName, $batch);
            }
        }

        echo "✓ Migrations complete\n";
    }

    private function runMigrationsFrom(string $path, string $source, int $batch): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = glob($path . '/*.sql');
        sort($files);

        foreach ($files as $file) {
            $migration = $source . ':' . basename($file);

            // Check if already run
            $exists = DB::table('atom_migration')
                ->where('migration', $migration)
                ->exists();

            if ($exists) {
                continue;
            }

            echo "  → Running: {$migration}\n";

            try {
                $sql = file_get_contents($file);
                
                // Split by semicolon for multiple statements
                $statements = array_filter(
                    array_map('trim', explode(';', $sql)),
                    fn($s) => !empty($s) && !preg_match('/^--/', $s)
                );

                foreach ($statements as $statement) {
                    if (!empty($statement)) {
                        DB::statement($statement);
                    }
                }

                // Record migration
                DB::table('atom_migration')->insert([
                    'migration' => $migration,
                    'batch' => $batch,
                ]);

                echo "    ✓ Done\n";
            } catch (\Exception $e) {
                echo "    ✗ Error: " . $e->getMessage() . "\n";
            }
        }
    }

    public function status(): void
    {
        echo "Migration Status\n";
        echo str_repeat('─', 60) . "\n";

        $migrations = DB::table('atom_migration')
            ->orderBy('executed_at')
            ->get();

        if ($migrations->isEmpty()) {
            echo "No migrations have been run.\n";
            return;
        }

        foreach ($migrations as $m) {
            echo sprintf("  ✓ %s (batch %d, %s)\n", 
                $m->migration, 
                $m->batch, 
                $m->executed_at
            );
        }
    }
}
