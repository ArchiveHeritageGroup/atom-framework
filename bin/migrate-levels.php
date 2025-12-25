#!/usr/bin/env php
<?php
/**
 * CLI script to migrate Extended Levels of Description.
 * 
 * Usage:
 *   php migrate-levels.php up      - Add new levels
 *   php migrate-levels.php down    - Remove new levels
 *   php migrate-levels.php status  - Show current status
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use AtomExtensions\Services\LevelOfDescriptionService;
use AtomExtensions\Migrations\ExtendedLevelsOfDescription;
use Illuminate\Database\Capsule\Manager as DB;

$command = $argv[1] ?? 'status';

echo "Extended Levels of Description Migration\n";
echo "========================================\n\n";

switch ($command) {
    case 'up':
        echo "Running migration...\n\n";
        $results = LevelOfDescriptionService::migrate();
        
        if (!empty($results['created'])) {
            echo "✓ Created:\n";
            foreach ($results['created'] as $name) {
                echo "  - $name\n";
            }
        }
        
        if (!empty($results['skipped'])) {
            echo "\n⏭ Skipped (already exist):\n";
            foreach ($results['skipped'] as $name) {
                echo "  - $name\n";
            }
        }
        
        if (!empty($results['errors'])) {
            echo "\n✗ Errors:\n";
            foreach ($results['errors'] as $error) {
                echo "  - $error\n";
            }
        }
        
        echo "\nMigration complete.\n";
        break;
        
    case 'down':
        echo "Rolling back migration...\n\n";
        $results = LevelOfDescriptionService::rollback();
        
        if (!empty($results['removed'])) {
            echo "✓ Removed:\n";
            foreach ($results['removed'] as $name) {
                echo "  - $name\n";
            }
        }
        
        if (!empty($results['errors'])) {
            echo "\n✗ Errors:\n";
            foreach ($results['errors'] as $error) {
                echo "  - $error\n";
            }
        }
        
        echo "\nRollback complete.\n";
        break;
        
    case 'status':
    default:
        echo "Current levels by sector:\n\n";
        
        $grouped = LevelOfDescriptionService::getGroupedBySector();
        
        foreach ($grouped as $sector => $levels) {
            echo strtoupper($sector) . ":\n";
            foreach ($levels as $level) {
                echo "  [{$level->id}] {$level->name}\n";
            }
            echo "\n";
        }
        
        // Count
        $total = DB::table('term')
            ->where('taxonomy_id', 34)
            ->count();
        echo "Total levels: $total\n";
        break;
}
