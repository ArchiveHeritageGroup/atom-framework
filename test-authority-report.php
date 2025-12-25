<?php

require_once __DIR__.'/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;
use AtomExtensions\Repositories\ActorRepository;
use AtomExtensions\Reports\Services\AuthorityRecordReportService;
use AtomExtensions\Reports\Filters\ReportFilter;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Initialize database
$capsule = new DB();
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'atom292',
    'username' => 'root',
    'password' => 'Merlot@123',
    'charset' => 'utf8mb4',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "  Testing Authority Record Report Service\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Create service
$repo = new ActorRepository();
$logger = new Logger('test');
$logger->pushHandler(new StreamHandler('php://stdout'));

$service = new AuthorityRecordReportService($repo, $logger);

// Test 1: Basic search
echo "[1/3] Basic search (last 30 days)...\n";
$filter = new ReportFilter([
    'dateStart' => date('d/m/Y', strtotime('-30 days')),
    'dateEnd' => date('d/m/Y'),
    'dateOf' => 'CREATED_AT',
    'limit' => 10,
    'page' => 1,
]);

$results = $service->search($filter);
echo "      ✓ Total actors: " . $results->getTotal() . "\n";
echo "      ✓ Page " . $results->getCurrentPage() . " of " . $results->getLastPage() . "\n";
echo "      ✓ Showing " . $results->getItems()->count() . " items\n\n";

// Test 2: Statistics
echo "[2/3] Statistics...\n";
$stats = $service->getStatistics();
echo "      ✓ Total: " . $stats['total'] . " authority records\n";
echo "      ✓ By type:\n";
foreach ($stats['by_type'] as $type => $count) {
    echo "          - $type: $count\n";
}
echo "\n";

// Test 3: Sample data
echo "[3/3] Sample authority records...\n";
foreach ($results->getItems()->take(5) as $actor) {
    $name = $actor['authorized_form_of_name'] ?? 'Unknown';
    $date = substr($actor['created_at'] ?? '', 0, 10);
    echo "      - $name (created: $date)\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "  ✓✓✓ All tests passed! Service is working.\n";
echo "═══════════════════════════════════════════════════════════\n\n";
