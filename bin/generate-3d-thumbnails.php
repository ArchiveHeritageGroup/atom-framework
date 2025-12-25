#!/usr/bin/env php
<?php
/**
 * CLI script to generate thumbnails for 3D digital objects
 *
 * Usage:
 *   php generate-3d-thumbnails.php                    # Process all without thumbnails
 *   php generate-3d-thumbnails.php --id=123           # Process specific digital object
 *   php generate-3d-thumbnails.php --force            # Regenerate all
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use AtomExtensions\Services\ThreeDThumbnailService;

$service = new ThreeDThumbnailService();

// Parse arguments
$options = getopt('', ['id:', 'force', 'help']);

if (isset($options['help'])) {
    echo "Usage: php generate-3d-thumbnails.php [options]\n";
    echo "  --id=N     Process specific digital object ID\n";
    echo "  --force    Regenerate all thumbnails\n";
    echo "  --help     Show this help\n";
    exit(0);
}

if (isset($options['id'])) {
    $id = (int) $options['id'];
    echo "Processing digital object ID: {$id}\n";
    $result = $service->createDerivatives($id);
    echo $result ? "Success!\n" : "Failed.\n";
    exit($result ? 0 : 1);
}

echo "Batch processing 3D objects...\n";
$results = $service->batchProcessExisting();

echo "Results:\n";
echo "  Processed: {$results['processed']}\n";
echo "  Success:   {$results['success']}\n";
echo "  Failed:    {$results['failed']}\n";

exit($results['failed'] > 0 ? 1 : 0);
