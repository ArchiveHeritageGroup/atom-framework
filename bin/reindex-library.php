#!/usr/bin/env php
<?php
/**
 * Reindex Library Items in Elasticsearch
 * 
 * Usage: php reindex-library.php [id1] [id2] ...
 * If no IDs provided, reindexes all library items.
 * 
 * PARKED ISSUE: PHP 8.3 compatibility - search:populate fails due to null values
 * in count() and in_array() calls. This script is a workaround.
 */

require_once '/usr/share/nginx/archive/config/ProjectConfiguration.class.php';
$configuration = ProjectConfiguration::getApplicationConfiguration('qubit', 'cli', false);
sfContext::createInstance($configuration);

// Required configs for ES indexing (normally set by populate())
$taxonomies = [QubitTaxonomy::SUBJECT_ID, QubitTaxonomy::PLACE_ID, QubitTaxonomy::GENRE_ID];
sfConfig::set('term_parent_list', QubitTerm::loadTermParentList($taxonomies));

if (empty(sfConfig::get('app_i18n_languages'))) {
    sfConfig::set('app_i18n_languages', ['en', 'af']);
}

// Get IDs from arguments or fetch all library items
$ids = array_slice($argv, 1);

if (empty($ids)) {
    // Get all library item IDs
    require_once '/usr/share/nginx/archive/atom-framework/bootstrap.php';
    $db = \Illuminate\Database\Capsule\Manager::connection();
    $ids = $db->table('library_item')->pluck('information_object_id')->toArray();
    echo "Found " . count($ids) . " library items to reindex\n";
}

$success = 0;
$failed = 0;

foreach ($ids as $id) {
    $io = QubitInformationObject::getById((int)$id);
    if ($io) {
        echo "Reindexing: " . $io->getTitle() . " (ID: $id)... ";
        try {
            QubitSearch::getInstance()->update($io);
            echo "OK\n";
            $success++;
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            $failed++;
        }
    } else {
        echo "Not found: $id\n";
        $failed++;
    }
}

echo "\nComplete: $success succeeded, $failed failed\n";
