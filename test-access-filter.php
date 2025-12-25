<?php

/**
 * AccessFilterService Test Script
 */

require_once __DIR__ . '/bootstrap.php';

use AtomExtensions\Services\Access\AccessFilterService;
use Illuminate\Database\Capsule\Manager as DB;

echo "==============================================\n";
echo "AccessFilterService Test\n";
echo "==============================================\n\n";

$service = AccessFilterService::getInstance();

// Test objects
$testObjects = [
    553 => 'Title of object (TOP_SECRET)',
    1375 => 'AVI Object (INTERNAL)',
    900325 => 'Dog with children (Donor)',
    1706 => 'Book about Mushrooms (Donor)',
];

// Real users from the database
$testUsers = [
    ['id' => null, 'desc' => 'Anonymous (no clearance)'],
    ['id' => 701, 'desc' => 'pam - Editor (INTERNAL)'],
    ['id' => 900147, 'desc' => 'louise - Admin (TOP_SECRET)'],
    ['id' => 900148, 'desc' => 'johanpiet - Admin (TOP_SECRET)'],
];

echo "1. USER CONTEXT CHECK\n";
echo "---------------------\n";
foreach ($testUsers as $user) {
    $context = $service->getUserContext($user['id']);
    echo sprintf(
        "%-40s | Clearance: %-12s (Level %d) | Admin: %s\n",
        $user['desc'],
        $context['clearance_code'],
        $context['clearance_level'],
        $context['is_administrator'] ? 'YES' : 'NO'
    );
}
echo "\n";

echo "2. OBJECT ACCESS CHECK\n";
echo "----------------------\n";
echo str_repeat("-", 105) . "\n";
printf("%-30s | %-30s | %-10s | %-12s | %s\n", "User", "Object", "Granted", "Level", "Reasons");
echo str_repeat("-", 105) . "\n";

foreach ($testUsers as $user) {
    foreach ($testObjects as $objectId => $objectDesc) {
        $access = $service->checkAccess($objectId, $user['id']);
        $reasons = implode(', ', $access['reasons']) ?: '-';
        
        printf(
            "%-30s | %-30s | %-10s | %-12s | %s\n",
            substr($user['desc'], 0, 30),
            substr($objectDesc, 0, 30),
            $access['granted'] ? '✓ YES' : '✗ NO',
            $access['level'],
            $reasons
        );
    }
    echo str_repeat("-", 105) . "\n";
}

echo "\n3. QUERY FILTER TEST\n";
echo "--------------------\n";
$total = DB::table('information_object')->count();
foreach ($testUsers as $user) {
    $query = DB::table('information_object');
    $query = $service->applyAccessFilters($query, $user['id']);
    $count = $query->count();
    
    printf("%-40s | Can see: %d / %d objects\n", $user['desc'], $count, $total);
}

echo "\n4. RESTRICTED OBJECTS REPORT\n";
echo "----------------------------\n";
$restricted = $service->getRestrictedObjects();
if ($restricted->isEmpty()) {
    echo "No restricted objects found.\n";
} else {
    printf("%-10s | %-30s | %-15s | %s\n", "ID", "Title", "Classification", "Donor");
    echo str_repeat("-", 80) . "\n";
    foreach ($restricted as $obj) {
        printf(
            "%-10d | %-30s | %-15s | %s\n",
            $obj->id,
            substr($obj->title ?? '-', 0, 30),
            $obj->classification_name ?? '-',
            $obj->donor_name ?? '-'
        );
    }
}

echo "\n==============================================\n";
echo "Test Complete!\n";
echo "==============================================\n";
