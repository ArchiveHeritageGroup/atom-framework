<?php

namespace AtomFramework\Console;

use AtomFramework\Extensions\ExtensionManager;
use AtomFramework\Extensions\PluginFetcher;
use AtomFramework\Extensions\MigrationHandler;
use AtomFramework\Extensions\ExtensionProtection;

class ExtensionCommand
{
    protected array $argv;
    protected ExtensionManager $manager;
    protected PluginFetcher $fetcher;
    protected MigrationHandler $migrationHandler;
    protected bool $interactive = true;

    protected array $commands = [
        'list' => 'List all extensions',
        'info' => 'Show extension details',
        'install' => 'Install an extension (auto-enables)',
        'uninstall' => 'Uninstall an extension',
        'enable' => 'Enable an extension',
        'disable' => 'Disable an extension',
        'restore' => 'Restore pending deletion',
        'cleanup' => 'Process pending deletions',
        'discover' => 'Discover available extensions',
        'audit' => 'Show audit log',
    ];

    public function __construct(array $argv)
    {
        $this->argv = $argv;
        $this->manager = new ExtensionManager();
        $pluginsPath = $this->manager->getSetting('extensions_path', null, '/usr/share/nginx/atom/plugins');
        $this->fetcher = new PluginFetcher($pluginsPath);
        $this->migrationHandler = new MigrationHandler($pluginsPath);
        $this->interactive = !in_array('--no-interaction', $argv) && !in_array('-n', $argv);
    }

    public function run(): int
    {
        $command = $this->argv[1] ?? 'help';
        $args = array_slice($this->argv, 2);

        try {
            return match($command) {
                'list' => $this->listExtensions($args),
                'info' => $this->showInfo($args),
                'install' => $this->install($args),
                'uninstall' => $this->uninstall($args),
                'enable' => $this->enable($args),
                'disable' => $this->disable($args),
                'restore' => $this->restore($args),
                'cleanup' => $this->cleanup($args),
                'discover' => $this->discover($args),
                'audit' => $this->audit($args),
                'help', '--help', '-h' => $this->showHelp(),
                default => $this->unknownCommand($command),
            };
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }

    protected function listExtensions(array $args): int
    {
        $status = $this->getOption($args, 'status');

        $this->line('');
        $this->info('AHG Extension Manager v1.0.0');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('');

        if ($status) {
            $extensions = $this->manager->getByStatus($status);
        } else {
            $extensions = $this->manager->all();
        }

        $this->line('INSTALLED EXTENSIONS:');
        $this->line('───────────────────────────────────────────────────────────');
        $this->line(sprintf('  %-3s %-35s %-10s %-15s', '#', 'Name', 'Version', 'Status'));
        $this->line('───────────────────────────────────────────────────────────');

        if ($extensions->isEmpty()) {
            $this->line('  (No extensions installed)');
        } else {
            $i = 1;
            foreach ($extensions as $ext) {
                $this->line(sprintf('  %-3d %-35s %-10s %s',
                    $i++,
                    $this->truncate($ext->display_name, 35),
                    $ext->version,
                    $this->formatStatus($ext->status)
                ));
            }
        }

        $this->line('');
        $this->line('Commands: php bin/atom extension:discover | extension:install <name>');
        $this->line('');

        return 0;
    }

    protected function showInfo(array $args): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            $this->error('Usage: php bin/atom extension:info <machine_name>');
            return 1;
        }

        $extension = $this->manager->find($name);

        if (!$extension) {
            $discovered = $this->manager->discover();
            $found = $discovered->first(fn($e) => ($e['machine_name'] ?? '') === $name);

            if ($found) {
                return $this->displayManifest($found);
            }

            $this->error("Extension '{$name}' not found.");
            return 1;
        }

        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("  {$extension['display_name']}");
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('');

        $this->line("  Machine Name:  {$extension['machine_name']}");
        $this->line("  Version:       {$extension['version']}");
        $this->line("  Status:        {$this->formatStatus($extension['status'])}");
        $this->line("  Author:        " . ($extension['author'] ?? 'Unknown'));
        $this->line("  License:       " . ($extension['license'] ?? 'GPL-3.0'));

        if (!empty($extension['description'])) {
            $this->line('');
            $this->line("  Description:");
            $this->line("  " . wordwrap($extension['description'], 60, "\n  "));
        }

        if (!empty($extension['tables_created'])) {
            $this->line('');
            $this->line("  Database Tables:");
            foreach ($extension['tables_created'] as $table) {
                $this->line("    • {$table}");
            }
        }

        $this->line('');
        $this->line("  Installed:     " . ($extension['installed_at'] ?? 'N/A'));
        $this->line("  Enabled:       " . ($extension['enabled_at'] ?? 'N/A'));
        $this->line('');

        return 0;
    }

    protected function install(array $args): int
    {
        $name = $args[0] ?? null;
        $autoTables = in_array('--with-tables', $args) || in_array('-t', $args);
        $skipTables = in_array('--skip-tables', $args) || in_array('-s', $args);
        $noEnable = in_array('--no-enable', $args);

        if (!$name) {
            $this->error('Usage: php bin/atom extension:install <machine_name> [--with-tables|-t] [--skip-tables|-s] [--no-enable]');
            return 1;
        }

        $this->line('');

        // Check if plugin exists locally
        $pluginsPath = $this->manager->getSetting('extensions_path', null, '/usr/share/nginx/atom/plugins');
        $pluginPath = "{$pluginsPath}/{$name}";

        if (!is_dir($pluginPath)) {
            $this->info("→ Plugin not found locally. Fetching from GitHub...");

            if ($this->fetcher->fetch($name)) {
                $this->success("Downloaded {$name}");
            } else {
                $this->error("Plugin '{$name}' not found in AHG repository.");
                $this->line("  Run 'php bin/atom extension:discover' to see available plugins.");
                return 1;
            }
        }

        // Check for migrations/tables
        $hasMigrations = $this->migrationHandler->hasMigrations($name);
        $hasSql = $this->migrationHandler->hasSqlFile($name);

        if ($hasMigrations || $hasSql) {
            $this->line('');
            $this->warning("⚠ This extension requires database tables.");

            // Check if tables already exist
            $manifestPath = "{$pluginPath}/extension.json";
            $tables = [];
            if (file_exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true);
                $tables = $manifest['tables'] ?? [];
            }

            $tablesExist = !empty($tables) && $this->migrationHandler->tablesExist($tables);

            if ($tablesExist) {
                $this->line("  Tables already exist. Skipping creation.");
                $createTables = false;
            } elseif ($autoTables) {
                $createTables = true;
            } elseif ($skipTables) {
                $createTables = false;
            } elseif ($this->interactive) {
                $this->line('');
                $this->line("  Options:");
                $this->line("    [Y] Create tables automatically");
                $this->line("    [N] Skip - I will create tables manually");
                $this->line('');

                if ($hasSql) {
                    $sqlPath = $this->migrationHandler->getSqlFilePath($name);
                    $this->line("  SQL file location: {$sqlPath}");
                    $this->line('');
                }

                echo "  Create tables automatically? [Y/n]: ";
                $answer = strtolower(trim(fgets(STDIN)));
                $createTables = ($answer === '' || $answer === 'y' || $answer === 'yes');
            } else {
                $createTables = false;
                $this->line("  Run with --with-tables to create automatically.");
            }

            if ($createTables) {
                $this->line('');
                $this->info("→ Creating database tables...");

                if ($hasMigrations) {
                    $results = $this->migrationHandler->runMigrations($name);

                    foreach ($results['success'] as $file) {
                        $this->success("  {$file}");
                    }
                    foreach ($results['skipped'] as $file) {
                        $this->line("  ○ {$file}");
                    }
                    foreach ($results['failed'] as $error) {
                        $this->error("  {$error}");
                    }
                } elseif ($hasSql) {
                    try {
                        $this->migrationHandler->runSqlFile($name);
                        $this->success("  Tables created from SQL file");
                    } catch (\Exception $e) {
                        $this->error("  " . $e->getMessage());
                        return 1;
                    }
                }
            } else {
                $this->line('');
                $this->warning("  Skipping table creation.");
                if ($hasSql) {
                    $sqlPath = $this->migrationHandler->getSqlFilePath($name);
                    $this->line("  To create tables manually, run:");
                    $this->line("    mysql -u root -p archive < {$sqlPath}");
                }
            }
        }

        $this->line('');
        $this->info("→ Installing {$name}...");

        $this->manager->install($name);

        // Auto-enable unless --no-enable flag
        if (!$noEnable) {
            $this->manager->enable($name);
            $this->success("Extension '{$name}' installed and enabled.");
        } else {
            $this->success("Extension '{$name}' installed.");
            $this->line("Run 'php bin/atom extension:enable {$name}' to enable it.");
        }

        $this->line('');

        return 0;
    }

    protected function uninstall(array $args): int
    {
        $name = $args[0] ?? null;
        $noBackup = in_array('--no-backup', $args);
        $dropTables = in_array('--drop-tables', $args);

        if (!$name) {
            $this->error('Usage: php bin/atom extension:uninstall <machine_name> [--no-backup] [--drop-tables]');
            return 1;
        }


        // Check protection level
        $check = ExtensionProtection::canUninstall($name);
        if (!$check['allowed']) {
            $this->error($check['reason']);
            return 1;
        }
        $this->line('');

        // Check for migrations
        $hasMigrations = $this->migrationHandler->hasMigrations($name);

        if ($hasMigrations && !$dropTables && $this->interactive) {
            $this->warning("⚠ This extension has database tables.");
            $this->line('');
            $this->line("  Options:");
            $this->line("    [Y] Keep tables (data preserved)");
            $this->line("    [N] Drop tables (data deleted)");
            $this->line('');

            echo "  Keep database tables? [Y/n]: ";
            $answer = strtolower(trim(fgets(STDIN)));
            $dropTables = ($answer === 'n' || $answer === 'no');
        }

        $this->warning("Uninstalling {$name}...");

        if (!$noBackup) {
            $this->line("Creating backup...");
        }

        // Drop tables if requested
        if ($dropTables && $hasMigrations) {
            $this->info("→ Dropping database tables...");
            $results = $this->migrationHandler->rollbackMigrations($name);

            foreach ($results['success'] as $file) {
                $this->success("  Rolled back: {$file}");
            }
            foreach ($results['failed'] as $error) {
                $this->error("  {$error}");
            }
        }

        $this->manager->uninstall($name, !$noBackup);

        $this->success("Extension '{$name}' uninstalled.");

        if (!$dropTables && $hasMigrations) {
            $this->line("Database tables were preserved.");
        }

        $this->line("Run 'php bin/atom extension:restore {$name}' to undo if needed.");
        $this->line('');

        return 0;
    }

    protected function enable(array $args): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            $this->error('Usage: php bin/atom extension:enable <machine_name>');
            return 1;
        }

        $this->manager->enable($name);

        $this->line('');
        $this->success("Extension '{$name}' enabled.");
        $this->line('');

        return 0;
    }

    protected function disable(array $args): int
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->error('Usage: php bin/atom extension:disable <machine_name>');
            return 1;
        }

        // Check protection level
        $check = ExtensionProtection::canDisable($name);
        if (!$check['allowed']) {
            $this->error($check['reason']);
            return 1;
        }

        $this->manager->disable($name);
        $this->line('');
        $this->success("Extension '{$name}' disabled.");
        $this->line('');
        return 0;
    }

    protected function restore(array $args): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            $this->error('Usage: php bin/atom extension:restore <machine_name>');
            return 1;
        }

        $this->manager->restore($name);

        $this->line('');
        $this->success("Extension '{$name}' restored.");
        $this->line('');

        return 0;
    }

    protected function cleanup(array $args): int
    {
        $this->line('');
        $this->info('Processing pending deletions...');

        $results = $this->manager->processPendingDeletions();

        $this->line("Processed: {$results['processed']}");
        $this->line("Failed: {$results['failed']}");

        if (!empty($results['errors'])) {
            $this->line('');
            $this->warning('Errors:');
            foreach ($results['errors'] as $error) {
                $this->line("  • {$error}");
            }
        }

        $this->line('');

        return $results['failed'] > 0 ? 1 : 0;
    }

    protected function discover(array $args): int
    {
        $this->line('');
        $this->info('Discovering extensions...');

        // Get local plugins
        $local = $this->manager->discover();

        // Get remote plugins
        $this->line('  Checking GitHub for available plugins...');
        $remote = $this->fetcher->getRemotePlugins();

        // Merge lists
        $all = [];

        foreach ($local as $plugin) {
            $name = $plugin['machine_name'] ?? '';
            $all[$name] = $plugin;
            $all[$name]['source'] = 'local';
        }

        foreach ($remote as $plugin) {
            $name = $plugin['machine_name'] ?? '';
            if (!isset($all[$name])) {
                $all[$name] = $plugin;
                $all[$name]['source'] = 'remote';
            }
        }

        if (empty($all)) {
            $this->line('');
            $this->line('  No extensions found.');
            $this->line('');
            return 0;
        }

        $this->line('');
        $this->line(sprintf('  %-28s %-8s %-12s %-10s %s', 'Name', 'Version', 'Source', 'Status', 'Machine Name'));
        $this->line('─────────────────────────────────────────────────────────────────────────────────────────────────');

        foreach ($all as $machineName => $ext) {
            $name = $ext['name'] ?? $ext['machine_name'] ?? 'Unknown';
            $version = $ext['version'] ?? '?';
            $source = $ext['source'] ?? 'local';
            $status = ($ext['is_registered'] ?? false) ? 'Installed' : 'Available';

            $sourceDisplay = $source === 'remote' ? "\033[36m(GitHub)\033[0m" : '(Local)';

            $this->line(sprintf('  %-28s %-8s %-20s %-10s %s',
                $this->truncate($name, 28),
                $version,
                $sourceDisplay,
                $status,
                $machineName
            ));
        }

        $this->line('');
        $this->line('  Install: php bin/atom extension:install <machine_name>');
        $this->line('');

        return 0;
    }

    protected function audit(array $args): int
    {
        $name = $args[0] ?? null;
        $limit = (int)($this->getOption($args, 'limit') ?? 20);

        $this->line('');
        $this->info('Extension Audit Log');
        $this->line('───────────────────────────────────────────────────────────');

        $logs = $this->manager->getAuditLog($name, $limit);

        if ($logs->isEmpty()) {
            $this->line('No audit entries found.');
        } else {
            foreach ($logs as $log) {
                $date = date('Y-m-d H:i', strtotime($log->created_at));
                $this->line(sprintf('  [%s] %-25s %s',
                    $date,
                    $log->extension_name,
                    $log->action
                ));
            }
        }

        $this->line('');

        return 0;
    }

    protected function displayManifest(array $manifest): int
    {
        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->warning("  {$manifest['name']} (Not Installed)");
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('');

        $this->line("  Machine Name:  {$manifest['machine_name']}");
        $this->line("  Version:       " . ($manifest['version'] ?? 'Unknown'));
        $this->line("  Author:        " . ($manifest['author'] ?? 'Unknown'));

        if (!empty($manifest['description'])) {
            $this->line('');
            $this->line("  Description:");
            $this->line("  " . wordwrap($manifest['description'], 60, "\n  "));
        }

        $this->line('');
        $this->info("  Install: php bin/atom extension:install {$manifest['machine_name']}");
        $this->line('');

        return 0;
    }

    protected function showHelp(): int
    {
        $this->line('');
        $this->info('AHG Extension Manager v1.0.0');
        $this->line('');
        $this->line('Usage: php bin/atom extension:<command> [arguments] [options]');
        $this->line('');
        $this->line('Commands:');

        foreach ($this->commands as $cmd => $desc) {
            $this->line(sprintf('  %-15s %s', $cmd, $desc));
        }

        $this->line('');
        $this->line('Install Options:');
        $this->line('  --with-tables, -t    Create database tables automatically');
        $this->line('  --skip-tables, -s    Skip table creation');
        $this->line('  --no-enable          Install without enabling');
        $this->line('  --no-interaction, -n Non-interactive mode');
        $this->line('');
        $this->line('Uninstall Options:');
        $this->line('  --no-backup          Skip backup creation');
        $this->line('  --drop-tables        Drop database tables');
        $this->line('');
        $this->line('Examples:');
        $this->line('  php bin/atom extension:discover');
        $this->line('  php bin/atom extension:install arSecurityClearancePlugin');
        $this->line('  php bin/atom extension:disable arSecurityClearancePlugin');
        $this->line('  php bin/atom extension:enable arSecurityClearancePlugin');
        $this->line('');

        return 0;
    }

    protected function unknownCommand(string $command): int
    {
        $this->error("Unknown command: {$command}");
        $this->line("Run 'php bin/atom extension:help' for available commands.");
        return 1;
    }

    // Output helpers
    protected function line(string $text): void { echo $text . PHP_EOL; }
    protected function info(string $text): void { echo "\033[36m{$text}\033[0m" . PHP_EOL; }
    protected function success(string $text): void { echo "\033[32m✓ {$text}\033[0m" . PHP_EOL; }
    protected function warning(string $text): void { echo "\033[33m{$text}\033[0m" . PHP_EOL; }
    protected function error(string $text): void { echo "\033[31m✗ {$text}\033[0m" . PHP_EOL; }

    protected function formatStatus(string $status): string
    {
        return match($status) {
            'enabled' => "\033[32m● Enabled\033[0m",
            'disabled' => "\033[33m○ Disabled\033[0m",
            'installed' => "\033[36m◐ Installed\033[0m",
            'pending_removal' => "\033[31m✕ Pending Removal\033[0m",
            default => $status,
        };
    }

    protected function truncate(string $text, int $length): string
    {
        return strlen($text) <= $length ? $text : substr($text, 0, $length - 3) . '...';
    }

    protected function getOption(array $args, string $name): ?string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "--{$name}=")) {
                return substr($arg, strlen("--{$name}="));
            }
        }
        return null;
    }
}
