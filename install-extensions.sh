<?php
// install-extensions.php

require __DIR__ . '/bootstrap.php';

use AtomFramework\Extensions\Spectrum\SpectrumAdapter;
use AtomFramework\Extensions\Grap\GrapAdapter;
use Illuminate\Database\Capsule\Manager as Capsule;

// Get the capsule that bootstrap.php already initialized
global $capsule;

echo "=== Installing Spectrum Extension ===\n";
$spectrum = new SpectrumAdapter();
$results = $spectrum->migrate($capsule);
print_r($results);

echo "\n=== Installing GRAP Extension ===\n";
$grap = new GrapAdapter();
$results = $grap->migrate($capsule);
print_r($results);

echo "\nDone!\n";