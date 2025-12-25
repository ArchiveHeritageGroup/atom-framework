#!/usr/bin/env php
<?php
/**
 * Sync library items to display_object_config
 * Run via cron: */5 * * * * php /usr/share/nginx/archive/atom-framework/bin/sync-library-display.php
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Illuminate\Database\Capsule\Manager as DB;

$db = DB::connection();

$inserted = $db->statement("
    INSERT INTO display_object_config (object_id, object_type, created_at, updated_at)
    SELECT io.id, 'library', NOW(), NOW()
    FROM information_object io
    LEFT JOIN display_object_config doc ON io.id = doc.object_id
    WHERE io.source_standard = 'library' AND doc.id IS NULL
");

echo "Sync complete\n";
