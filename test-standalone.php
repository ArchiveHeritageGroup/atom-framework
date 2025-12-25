<?php

declare(strict_types=1);

require_once __DIR__.'/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;
use AtomExtensions\Repositories\InformationObjectRepository;
use AtomExtensions\Repositories\ActorRepository;

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "  AtoM Framework v2 - Standalone Test\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Test 1: Database Connection
echo "[1/3] Testing database connection...\n";

try {
    $capsule = new DB();
    $capsule->addConnection([
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'atom292',
        'username' => 'root',
        'password' => 'Merlot@123',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => false,
    ]);
    
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    
    $pdo = $capsule->getConnection()->getPdo();
    echo "      ✓ Database connected successfully\n";
    echo "      Database: atom292 on localhost:3306\n";
} catch (Exception $e) {
    echo "      ✗ Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Query Builder
echo "\n[2/3] Testing Laravel Query Builder...\n";

try {
    $count = DB::table('information_object')->count();
    echo "      ✓ Query builder working\n";
    echo "      Found {$count} information objects\n";
} catch (Exception $e) {
    echo "      ✗ Query error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Repositories
echo "\n[3/3] Testing Repositories...\n";

try {
    $ioRepo = new InformationObjectRepository();
    $ioCount = $ioRepo->count();
    echo "      ✓ InformationObjectRepository working\n";
    echo "      Count: {$ioCount} information objects\n";
    
    $actorRepo = new ActorRepository();
    $actorCount = $actorRepo->count();
    echo "      ✓ ActorRepository working\n";
    echo "      Count: {$actorCount} actors\n";
} catch (Exception $e) {
    echo "      ✗ Repository error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "  ✓✓✓ ALL TESTS PASSED ✓✓✓\n";
echo "═══════════════════════════════════════════════════════════\n\n";
echo "Framework is ready to use!\n\n";
echo "Next: Integrate with AtoM (see INSTALLATION.md)\n\n";
