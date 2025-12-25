<?php

namespace AtomFramework\Console;

use AtomFramework\Extensions\ExtensionManager;
use AtomFramework\Extensions\PluginFetcher;

class ExtensionCommand
{
    protected array $argv;
    protected ExtensionManager $manager;
    protected PluginFetcher $fetcher;
    
    protected array $commands = [
        'list' => 'List all extensions',
        'info' => 'Show extension details',
        'install' => 'Install an extension',
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
        $this->fetcher = new PluginFetcher($this->manager->getSetting('extensions_path', null, '/usr/share/nginx/atom/plugins'));
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
        $this->line('Commands: info <name> | install <name> | enable <name> | disable <name>');
        $this->line('');

        return 0;
    }

    protected function showInfo(array $args): int
    {
        $name = $args[0] ?? null;
        
        if (!$name) {
            $this->error('Usage: extension info <machine_name>');
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

        if (!empty($extension['dependencies'])) {
            $this->line('');
            $this->line("  Dependencies:");
            foreach ($extension['dependencies'] as $dep) {
                $this->line("    • {$dep}");
            }
        }

        if (!empty($extension['theme_support'])) {
            $this->line('');
            $this->line("  Theme Support:");
            foreach ($extension['theme_support'] as $theme) {
                $this->line("    • {$theme}");
            }
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
        
        if (!$name) {
            $this->error('Usage: extension install <machine_name>');
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
                $this->line("  Run 'extension discover' to see available plugins.");
                return 1;
            }
        }
        
        $this->info("Installing {$name}...");
        
        $this->manager->install($name);
        
        $this->success("Extension '{$name}' installed successfully.");
        $this->line("Run 'extension enable {$name}' to enable it.");
        $this->line('');

        return 0;
    }

    protected function uninstall(array $args): int
    {
        $name = $args[0] ?? null;
        $noBackup = in_array('--no-backup', $args);
        
        if (!$name) {
            $this->error('Usage: extension uninstall <machine_name> [--no-backup]');
            return 1;
        }

        $this->line('');
        $this->warning("Uninstalling {$name}...");
        
        if (!$noBackup) {
            $this->line("Creating backup...");
        }
        
        $this->manager->uninstall($name, !$noBackup);
        
        $gracePeriod = $this->manager->getSetting('grace_period_days', null, 30);
        
        $this->success("Extension '{$name}' uninstalled.");
        $this->line("Data will be permanently deleted in {$gracePeriod} days.");
        $this->line("Run 'extension restore {$name}' to cancel deletion.");
        $this->line('');

        return 0;
    }

    protected function enable(array $args): int
    {
        $name = $args[0] ?? null;
        
        if (!$name) {
            $this->error('Usage: extension enable <machine_name>');
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
            $this->error('Usage: extension disable <machine_name>');
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
            $this->error('Usage: extension restore <machine_name>');
            return 1;
        }

        $this->manager->restore($name);
        
        $this->line('');
        $this->success("Extension '{$name}' restored. Pending deletion cancelled.");
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
        $this->line('');
        
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
            $this->line('  No extensions found.');
            $this->line('');
            return 0;
        }
        
        $this->line('');
        $this->line(sprintf('  %-35s %-10s %-12s %-10s', 'Name', 'Version', 'Source', 'Status'));
        $this->line('───────────────────────────────────────────────────────────────────');
        
        foreach ($all as $ext) {
            $name = $ext['name'] ?? $ext['machine_name'] ?? 'Unknown';
            $version = $ext['version'] ?? '?';
            $source = $ext['source'] ?? 'local';
            $status = ($ext['is_registered'] ?? false) ? 'Installed' : 'Available';
            
            $sourceDisplay = $source === 'remote' ? "\033[36m(GitHub)\033[0m" : '(Local)';
            
            $this->line(sprintf('  %-35s %-10s %-20s %-10s',
                $this->truncate($name, 35),
                $version,
                $sourceDisplay,
                $status
            ));
        }
        
        $this->line('');
        $this->line('  Install with: php bin/extension install <machine_name>');
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
        $this->line("  Path:          " . ($manifest['path'] ?? 'Remote'));
        
        if (!empty($manifest['description'])) {
            $this->line('');
            $this->line("  Description:");
            $this->line("  " . wordwrap($manifest['description'], 60, "\n  "));
        }

        $this->line('');
        $this->info("  Run: extension install {$manifest['machine_name']}");
        $this->line('');

        return 0;
    }

    protected function showHelp(): int
    {
        $this->line('');
        $this->info('AHG Extension Manager v1.0.0');
        $this->line('');
        $this->line('Usage: php bin/extension <command> [arguments] [options]');
        $this->line('');
        $this->line('Commands:');
        
        foreach ($this->commands as $cmd => $desc) {
            $this->line(sprintf('  %-15s %s', $cmd, $desc));
        }
        
        $this->line('');
        $this->line('Examples:');
        $this->line('  php bin/extension list');
        $this->line('  php bin/extension discover');
        $this->line('  php bin/extension install arSecurityClearancePlugin');
        $this->line('  php bin/extension enable arSecurityClearancePlugin');
        $this->line('');

        return 0;
    }

    protected function unknownCommand(string $command): int
    {
        $this->error("Unknown command: {$command}");
        $this->line("Run 'extension help' for available commands.");
        return 1;
    }

    // Output helpers
    protected function line(string $text): void
    {
        echo $text . PHP_EOL;
    }

    protected function info(string $text): void
    {
        echo "\033[36m{$text}\033[0m" . PHP_EOL;
    }

    protected function success(string $text): void
    {
        echo "\033[32m✓ {$text}\033[0m" . PHP_EOL;
    }

    protected function warning(string $text): void
    {
        echo "\033[33m{$text}\033[0m" . PHP_EOL;
    }

    protected function error(string $text): void
    {
        echo "\033[31m✗ {$text}\033[0m" . PHP_EOL;
    }

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
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length - 3) . '...';
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
