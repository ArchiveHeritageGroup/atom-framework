#!/usr/bin/env php
<?php
/**
 * Process library cover download queue
 * Run via cron every minute
 */

define('ATOM_ROOT', dirname(dirname(__DIR__)));

// Bootstrap AtoM
require_once ATOM_ROOT . '/config/ProjectConfiguration.class.php';
$configuration = ProjectConfiguration::getApplicationConfiguration('qubit', 'cli', false);
$context = sfContext::createInstance($configuration);

// Set culture for ElasticSearch serialization
sfConfig::set('sf_default_culture', 'en');
$context->user->setCulture('en');

// Set allowed languages for ElasticSearch (fix for CLI mode)
sfConfig::set('app_i18n_languages', ['en']);

// Bootstrap framework
require_once ATOM_ROOT . '/atom-framework/bootstrap.php';

use Illuminate\Database\Capsule\Manager as DB;

$db = DB::connection();

// Get pending items (max 10 per run)
$pending = $db->table('atom_library_cover_queue')
    ->where('status', 'pending')
    ->where('attempts', '<', 3)
    ->orderBy('created_at')
    ->limit(10)
    ->get();

if ($pending->isEmpty()) {
    exit(0);
}

require_once ATOM_ROOT . '/atom-framework/src/Services/LibraryCoverService.php';
$coverService = new \AtomFramework\Services\LibraryCoverService();

foreach ($pending as $item) {
    echo date('Y-m-d H:i:s') . " Processing IO {$item->information_object_id} ISBN {$item->isbn}\n";
    
    // Mark as processing
    $db->table('atom_library_cover_queue')
        ->where('id', $item->id)
        ->update([
            'status' => 'processing',
            'attempts' => $item->attempts + 1,
        ]);
    
    try {
        // Check if IO exists
        $io = QubitInformationObject::getById($item->information_object_id);
        if (!$io) {
            throw new Exception("Information object not found");
        }
        
        // Check if already has digital object
        if ($io->getDigitalObject() !== null) {
            echo "  Already has digital object, marking complete\n";
            $db->table('atom_library_cover_queue')
                ->where('id', $item->id)
                ->update([
                    'status' => 'completed',
                    'processed_at' => date('Y-m-d H:i:s'),
                ]);
            continue;
        }
        
        // Get cover URL
        $coverUrl = $coverService->getOpenLibraryCoverUrl($item->isbn, 'L');
        if (!$coverUrl) {
            throw new Exception("No cover available from Open Library");
        }
        
        // Download and save
        $result = $coverService->downloadAndSaveAsDigitalObject($item->information_object_id, $coverUrl);
        
        if ($result) {
            echo "  Success - cover saved\n";
            $db->table('atom_library_cover_queue')
                ->where('id', $item->id)
                ->update([
                    'status' => 'completed',
                    'processed_at' => date('Y-m-d H:i:s'),
                ]);
        } else {
            throw new Exception("Failed to save digital object");
        }
        
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        echo "  Error: {$errorMsg}\n";
        
        $newStatus = ($item->attempts + 1 >= 3) ? 'failed' : 'pending';
        $db->table('atom_library_cover_queue')
            ->where('id', $item->id)
            ->update([
                'status' => $newStatus,
                'error_message' => $errorMsg,
            ]);
    }
    
    // Small delay between items
    usleep(500000);
}

echo date('Y-m-d H:i:s') . " Done\n";
