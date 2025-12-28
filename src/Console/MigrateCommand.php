<?php

namespace AtomFramework\Console;

use AtomFramework\Database\MigrationRunner;

class MigrateCommand
{
    protected MigrationRunner $runner;
    
    public function __construct()
    {
        $this->runner = new MigrationRunner();
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
    
    protected function migrate(): int
    {
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
