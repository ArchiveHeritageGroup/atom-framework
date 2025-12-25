<?php
// install-extensions.php

require __DIR__ . '/bootstrap.php';

use AtomFramework\Extensions\Spectrum\SpectrumAdapter;
use AtomFramework\Extensions\Grap\GrapAdapter;
use AtomFramework\Core\Database\DatabaseManager;

// Get the capsule from bootstrap
global $capsule;

$db = new DatabaseManager($capsule);

echo "=== Installing Spectrum Extension ===\n";
$spectrum = new SpectrumAdapter();
$results = $spectrum->migrate($db);
print_r($results);

echo "\n=== Installing GRAP Extension ===\n";
$grap = new GrapAdapter();
$results = $grap->migrate($db);
print_r($results);

echo "\nDone!\n";
