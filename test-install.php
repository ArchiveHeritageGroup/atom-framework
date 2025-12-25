<?php

/**
 * AtoM Framework v2 Installation Test
 *
 * Run this to verify the framework is properly installed.
 * Usage: php test-install.php
 */

declare(strict_types=1);

require_once __DIR__.'/bootstrap.php';

use AtomExtensions\Database\DatabaseBootstrap;
use AtomExtensions\Repositories\InformationObjectRepository;
use AtomExtensions\Repositories\ActorRepository;

$errors = [];
$warnings = [];

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "  AtoM Framework v2 - Installation Test\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Test 1: Database Connection
echo "[1/6] Testing database connection...\n";

try {
    if (DatabaseBootstrap::testConnection()) {
        echo "      ✓ Database connected successfully\n";
        $info = DatabaseBootstrap::getConnectionInfo();
        echo "      Database: {$info['database']} on {$info['host']}:{$info['port']}\n";
    } else {
        $errors[] = 'Database connection test returned false';
        echo "      ✗ Database connection failed\n";
    }
} catch (Exception $e) {
    $errors[] = 'Database connection error: '.$e->getMessage();
    echo "      ✗ Exception: {$e->getMessage()}\n";
}

// Test 2: InformationObjectRepository
echo "\n[2/6] Testing InformationObjectRepository...\n";

try {
    $repo = new InformationObjectRepository();
    $count = $repo->count();
    echo "      ✓ Repository instantiated\n";
    echo "      Found {$count} information objects in database\n";

    if ($count === 0) {
        $warnings[] = 'No information objects found (empty database?)';
    }
} catch (Exception $e) {
    $errors[] = 'InformationObjectRepository error: '.$e->getMessage();
    echo "      ✗ Exception: {$e->getMessage()}\n";
}

// Test 3: Query Builder
echo "\n[3/6] Testing Laravel Query Builder...\n";

try {
    $repo = new InformationObjectRepository();
    $query = $repo->reportQuery();
    $sql = $repo->toSql($query);
    echo "      ✓ Query builder working\n";
    echo "      Sample SQL: ".substr($sql, 0, 100)."...\n";
} catch (Exception $e) {
    $errors[] = 'Query builder error: '.$e->getMessage();
    echo "      ✗ Exception: {$e->getMessage()}\n";
}

// Test 4: ActorRepository
echo "\n[4/6] Testing ActorRepository...\n";

try {
    $actorRepo = new ActorRepository();
    $count = $actorRepo->count();
    echo "      ✓ ActorRepository instantiated\n";
    echo "      Found {$count} actors in database\n";

    if ($count === 0) {
        $warnings[] = 'No actors found (empty database?)';
    }
} catch (Exception $e) {
    $errors[] = 'ActorRepository error: '.$e->getMessage();
    echo "      ✗ Exception: {$e->getMessage()}\n";
}

// Test 5: Collections
echo "\n[5/6] Testing Illuminate Collections...\n";

try {
    $repo = new InformationObjectRepository();
    $results = $repo->findWhere(['parent_id' => null]); // Should fail gracefully
    echo "      ✓ Collections working\n";
    echo "      Result type: ".get_class($results)."\n";
} catch (Exception $e) {
    $errors[] = 'Collections error: '.$e->getMessage();
    echo "      ✗ Exception: {$e->getMessage()}\n";
}

// Test 6: Form Field Factory
echo "\n[6/6] Testing FormFieldFactory...\n";

try {
    if (class_exists('AtomExtensions\\Forms\\FormFieldFactory')) {
        echo "      ✓ FormFieldFactory loaded\n";

        $form = new sfForm([], [], false);
        \AtomExtensions\Forms\FormFieldFactory::addDateFields($form);
        echo "      ✓ Date fields added to form\n";

        \AtomExtensions\Forms\FormFieldFactory::addControlFields($form);
        echo "      ✓ Control fields added to form\n";
    } else {
        $errors[] = 'FormFieldFactory class not found';
        echo "      ✗ FormFieldFactory not loaded\n";
    }
} catch (Exception $e) {
    $errors[] = 'FormFieldFactory error: '.$e->getMessage();
    echo "      ✗ Exception: {$e->getMessage()}\n";
}

// Summary
echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "  Test Summary\n";
echo "═══════════════════════════════════════════════════════════\n\n";

if (empty($errors)) {
    echo "✓✓✓ ALL TESTS PASSED ✓✓✓\n\n";
    echo "Framework is properly installed and ready to use!\n\n";

    if (!empty($warnings)) {
        echo "Warnings:\n";

        foreach ($warnings as $warning) {
            echo "  ⚠ {$warning}\n";
        }

        echo "\n";
    }

    echo "Next steps:\n";
    echo "1. Test a simple query in your code\n";
    echo "2. Migrate Authority Record Report (Phase 2)\n";
    echo "3. Complete Information Object Report (Phase 3)\n\n";

    exit(0);
} else {
    echo "✗✗✗ TESTS FAILED ✗✗✗\n\n";
    echo "Errors found:\n";

    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }

    echo "\nPlease fix these errors before proceeding.\n";
    echo "Check INSTALLATION.md for troubleshooting steps.\n\n";

    exit(1);
}
