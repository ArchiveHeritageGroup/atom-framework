#!/usr/bin/env php
<?php
/**
 * Sync display_object_config for Library and DAM items
 * Run via cron every 5 minutes
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Illuminate\Database\Capsule\Manager as DB;

$db = DB::connection();

// Sync Library items (based on source_standard)
$db->statement("
    INSERT INTO display_object_config (object_id, object_type, created_at, updated_at)
    SELECT io.id, 'library', NOW(), NOW()
    FROM information_object io
    LEFT JOIN display_object_config doc ON io.id = doc.object_id
    WHERE io.source_standard = 'library' AND doc.id IS NULL
");

// Sync DAM items (based on source_standard)
$db->statement("
    INSERT INTO display_object_config (object_id, object_type, created_at, updated_at)
    SELECT io.id, 'dam', NOW(), NOW()
    FROM information_object io
    LEFT JOIN display_object_config doc ON io.id = doc.object_id
    WHERE io.source_standard = 'dam' AND doc.id IS NULL
");

// Also sync DAM items based on dam_iptc_metadata (only for valid information_objects)
$db->statement("
    INSERT INTO display_object_config (object_id, object_type, created_at, updated_at)
    SELECT iptc.object_id, 'dam', NOW(), NOW()
    FROM dam_iptc_metadata iptc
    INNER JOIN information_object io ON iptc.object_id = io.id
    LEFT JOIN display_object_config doc ON iptc.object_id = doc.object_id
    WHERE doc.id IS NULL
");

// Update existing display_object_config to 'dam' if they have IPTC metadata but wrong type
$db->statement("
    UPDATE display_object_config doc
    INNER JOIN dam_iptc_metadata iptc ON doc.object_id = iptc.object_id
    SET doc.object_type = 'dam', doc.updated_at = NOW()
    WHERE doc.object_type != 'dam'
");

echo "Sync complete: " . date('Y-m-d H:i:s') . "\n";
