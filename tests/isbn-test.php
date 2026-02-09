<?php

declare(strict_types=1);

$frameworkPath = dirname(__DIR__);

require_once $frameworkPath . '/vendor/autoload.php';

$capsule = new \Illuminate\Database\Capsule\Manager();
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'database' => 'archive',
    'username' => 'root',
    'password' => 'Merlot@123',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

require_once $frameworkPath . '/src/Repositories/IsbnLookupRepository.php';
require_once $frameworkPath . '/src/Services/LanguageService.php';
require_once $frameworkPath . '/src/Services/WorldCatService.php';
require_once $frameworkPath . '/src/Services/BookCoverService.php';
require_once $frameworkPath . '/src/Services/IsbnMetadataMapper.php';

use AtomFramework\Repositories\IsbnLookupRepository;
use AtomFramework\Services\WorldCatService;
use AtomFramework\Services\BookCoverService;
use AtomFramework\Services\IsbnMetadataMapper;
use AtomFramework\Services\LanguageService;

echo "\n";
echo "================================================================\n";
echo "              ISBN LOOKUP CLI TEST                              \n";
echo "================================================================\n\n";

$passed = 0;
$failed = 0;

function test(bool $condition, string $message): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [OK] {$message}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$message}\n";
        $failed++;
    }
}

echo ">> Testing ISBN Validation\n";
echo str_repeat('-', 50) . "\n";

$repository = new IsbnLookupRepository();
$service = new WorldCatService($repository);

test($service->validateIsbn('0134685997'), 'Valid ISBN-10: 0134685997');
test($service->validateIsbn('9780134685991'), 'Valid ISBN-13: 9780134685991');
test($service->validateIsbn('978-0-13-468599-1'), 'ISBN with hyphens');
test(!$service->validateIsbn('1234567890'), 'Invalid ISBN rejected');
test(!$service->validateIsbn('12345'), 'Short ISBN rejected');

echo "\n>> Testing Language Service (ISO Conversion)\n";
echo str_repeat('-', 50) . "\n";

// Test 3-letter to 2-letter conversion
test(LanguageService::iso639_2to1('eng') === 'en', "ISO 639-2 'eng' -> 'en'");
test(LanguageService::iso639_2to1('afr') === 'af', "ISO 639-2 'afr' -> 'af'");
test(LanguageService::iso639_2to1('zul') === 'zu', "ISO 639-2 'zul' -> 'zu'");
test(LanguageService::iso639_2to1('fra') === 'fr', "ISO 639-2 'fra' -> 'fr'");
test(LanguageService::iso639_2to1('deu') === 'de', "ISO 639-2 'deu' -> 'de'");

// Test 2-letter to 3-letter conversion
test(LanguageService::iso639_1to2('en') === 'eng', "ISO 639-1 'en' -> 'eng'");
test(LanguageService::iso639_1to2('af') === 'afr', "ISO 639-1 'af' -> 'afr'");

// Test name lookup from any code
$engName = LanguageService::getNameFromCode('eng');
test($engName === 'English', "3-letter 'eng' -> English");

$engName2 = LanguageService::getNameFromCode('en');
test($engName2 === 'English', "2-letter 'en' -> English");

$afrName = LanguageService::getNameFromCode('afr');
test($afrName === 'Afrikaans', "3-letter 'afr' -> Afrikaans");

$afrName2 = LanguageService::getNameFromCode('af');
test($afrName2 === 'Afrikaans', "2-letter 'af' -> Afrikaans");

// Test code from name
test(LanguageService::getCodeFromName('English') === 'en', "Name 'English' -> 'en'");
test(LanguageService::getCodeFromName('Afrikaans') === 'af', "Name 'Afrikaans' -> 'af'");

echo "\n>> Testing Book Cover Service\n";
echo str_repeat('-', 50) . "\n";

$isbn = '9780134685991';
$url = BookCoverService::getOpenLibraryUrl($isbn, 'M');
$expected = "https://covers.openlibrary.org/b/isbn/{$isbn}-M.jpg";

test($url === $expected, "URL generated correctly");

$sizes = BookCoverService::getAllSizes($isbn);
test(isset($sizes['small']) && isset($sizes['medium']) && isset($sizes['large']), 'All sizes generated');

echo "\n>> Testing Open Library Lookup\n";
echo str_repeat('-', 50) . "\n";

$service = new WorldCatService($repository, null, [
    'use_cache' => false,
    'preferred_source' => 'openlibrary',
]);

$isbn = '9780596517748';
echo "  Looking up {$isbn}...\n";

$start = microtime(true);
$result = $service->lookup($isbn);
$elapsed = round((microtime(true) - $start) * 1000);

test($result['success'], "Lookup success ({$elapsed}ms)");

if ($result['success']) {
    $data = $result['data'];
    test(!empty($data['title']), "Title: " . ($data['title'] ?? 'N/A'));
    test(!empty($data['authors']), "Authors: " . implode(', ', $data['authors'] ?? []));
    test($result['source'] === 'openlibrary', "Source: {$result['source']}");
}

echo "\n>> Testing Google Books Lookup\n";
echo str_repeat('-', 50) . "\n";

$service = new WorldCatService($repository, null, [
    'use_cache' => false,
    'preferred_source' => 'googlebooks',
]);

$isbn = '9781491950357';
echo "  Looking up {$isbn}...\n";

$start = microtime(true);
$result = $service->lookup($isbn);
$elapsed = round((microtime(true) - $start) * 1000);

test($result['success'], "Lookup success ({$elapsed}ms)");

if ($result['success']) {
    test(!empty($result['data']['title']), "Title: " . ($result['data']['title'] ?? 'N/A'));
    test($result['source'] === 'googlebooks', "Source: {$result['source']}");
}

echo "\n>> Testing Metadata Mapper with Language\n";
echo str_repeat('-', 50) . "\n";

$mapper = new IsbnMetadataMapper();

// Test with 3-letter code (as returned by APIs)
$testData = [
    'title' => 'Test Book',
    'authors' => ['John Doe'],
    'publishers' => ['Test Publisher'],
    'isbn_13' => '9780134685991',
    'language' => 'eng',  // 3-letter from API
];

$mapped = $mapper->mapToAtom($testData);
test(isset($mapped['fields']), 'Fields mapped');
test(count($mapped['relations']) === 2, 'Relations: ' . count($mapped['relations']));
test(count($mapped['terms']) === 1, 'Language term mapped');

$preview = $mapper->getPreviewData($testData);
test($preview['title'] === 'Test Book', 'Preview title');
test(isset($preview['cover_url']), 'Preview has cover URL');
test($preview['language'] === 'English', 'Language converted: ' . ($preview['language'] ?? 'N/A'));

// Test with 2-letter code
$testData2 = [
    'title' => 'Afrikaans Book',
    'language' => 'af',  // 2-letter
];
$preview2 = $mapper->getPreviewData($testData2);
test($preview2['language'] === 'Afrikaans', 'AF language: ' . ($preview2['language'] ?? 'N/A'));

echo "\n>> Testing Cache\n";
echo str_repeat('-', 50) . "\n";

$testMeta = ['title' => 'Cache Test ' . time(), 'isbn_13' => '9999999999999'];
$cacheId = $repository->cache('9999999999999', $testMeta, 'test');
test($cacheId > 0, "Cached ID: {$cacheId}");

$cached = $repository->getCached('9999999999999');
test($cached !== null, 'Cache retrieved');

echo "\n>> Testing Audit\n";
echo str_repeat('-', 50) . "\n";

$auditId = $repository->audit([
    'isbn' => '9780134685991',
    'source' => 'cli-test',
    'success' => true,
    'lookup_time_ms' => 100,
]);
test($auditId > 0, "Audit ID: {$auditId}");

$stats = $repository->getStatistics(1);
test($stats['total_lookups'] > 0, "Total lookups: {$stats['total_lookups']}");

echo "\n================================================================\n";
echo "                      SUMMARY                                   \n";
echo "================================================================\n";
$total = $passed + $failed;
$percent = $total > 0 ? round(($passed / $total) * 100) : 0;
echo "  Passed: {$passed}  Failed: {$failed}  Total: {$total}  Success: {$percent}%\n";
echo "================================================================\n\n";

exit($failed > 0 ? 1 : 0);
