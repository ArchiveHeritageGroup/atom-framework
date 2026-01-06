<?php
namespace AtomFramework\Console;
use AtomFramework\Database\MigrationRunner;

class MigrateCommand
{
    protected MigrationRunner $runner;
    protected string $atomRoot;
    
    public function __construct()
    {
        $this->runner = new MigrationRunner();
        $this->atomRoot = dirname(dirname(dirname(__DIR__)));
    }
    
    public function run(array $args): int
    {
        $action = $args[0] ?? 'run';
        switch ($action) {
            case 'run':
                return $this->migrate();
            case 'status':
                return $this->status();
            default:
                echo "Usage: php bin/atom migrate [run|status]\n";
                return 1;
        }
    }
    
    protected function pullUpdates(): void
    {
        $frameworkPath = $this->atomRoot . '/atom-framework';
        $pluginsPath = $this->atomRoot . '/atom-ahg-plugins';
        
        echo "\033[34mPulling latest updates...\033[0m\n";
        
        // Pull framework
        if (is_dir($frameworkPath . '/.git')) {
            echo "  → atom-framework: ";
            $output = [];
            exec("cd {$frameworkPath} && git pull origin main 2>&1", $output, $code);
            if ($code === 0) {
                $lastLine = end($output);
                if (strpos($lastLine, 'Already up to date') !== false) {
                    echo "up to date\n";
                } else {
                    echo "\033[32mupdated\033[0m\n";
                }
            } else {
                echo "\033[33mfailed (continuing anyway)\033[0m\n";
            }
        }
        
        // Pull plugins
        if (is_dir($pluginsPath . '/.git')) {
            echo "  → atom-ahg-plugins: ";
            $output = [];
            exec("cd {$pluginsPath} && git pull origin main 2>&1", $output, $code);
            if ($code === 0) {
                $lastLine = end($output);
                if (strpos($lastLine, 'Already up to date') !== false) {
                    echo "up to date\n";
                } else {
                    echo "\033[32mupdated\033[0m\n";
                }
            } else {
                echo "\033[33mfailed (continuing anyway)\033[0m\n";
            }
        }
        
        echo "\n";
    }
    
    protected function migrate(): int
    {
        // Pull updates first
        $this->pullUpdates();
        
        echo "Running migrations...\n";
        $results = $this->runner->migrate();
        
        if (empty($results)) {
            echo "  Nothing to migrate.\n";
            return 0;
        }
        
        foreach ($results as $result) {
            $status = $result['status'] === 'success' ? '✓' : '✗';
            echo "  {$status} {$result['migration']}";
            if (isset($result['error'])) {
                echo " - {$result['error']}";
            }
            echo "\n";
        }
        
        return 0;
    }
    
    protected function status(): int
    {
        echo "Migration Status\n";
        echo str_repeat('─', 60) . "\n";
        
        $status = $this->runner->status();
        
        foreach ($status as $migration) {
            $icon = $migration['executed'] ? '✓' : '○';
            $date = $migration['executed_at'] ?? 'Pending';
            printf("  %s %-40s %s\n", $icon, $migration['migration'], $date);
        }
        
        return 0;
    }
}
