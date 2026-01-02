<?php

declare(strict_types=1);

namespace AtomFramework\Services;

use AtomFramework\Repositories\IsbnLookupRepository;
use Monolog\Logger;

/**
 * WorldCat ISBN lookup service.
 *
 * Provides metadata lookup from multiple sources:
 * - Open Library (free, no API key required)
 * - Google Books (free, optional API key)
 * - WorldCat (requires OCLC API key)
 */
class WorldCatService
{
    private IsbnLookupRepository $repository;
    private ?Logger $logger;
    private array $config;

    public function __construct(
        IsbnLookupRepository $repository,
        ?Logger $logger = null,
        array $config = []
    ) {
        $this->repository = $repository;
        $this->logger = $logger;
        $this->config = array_merge([
            'timeout' => 10,
            'use_cache' => true,
            'preferred_source' => null, // null = try all by priority
        ], $config);
    }

    /**
     * Lookup ISBN metadata.
     *
     * @return array{success: bool, data?: array, source?: string, error?: string, cached?: bool}
     */
    public function lookup(string $isbn, ?int $userId = null, ?int $objectId = null): array
    {
        $startTime = microtime(true);
        $isbn = $this->normalizeIsbn($isbn);

        if (!$this->validateIsbn($isbn)) {
            return [
                'success' => false,
                'error' => 'Invalid ISBN format',
            ];
        }

        // Check cache first
        if ($this->config['use_cache']) {
            $cached = $this->repository->getCached($isbn);
            if ($cached) {
                $this->logLookup($isbn, $userId, $objectId, true, $cached['source'], null, $startTime, true);

                return [
                    'success' => true,
                    'data' => $cached['metadata'],
                    'source' => $cached['source'],
                    'cached' => true,
                ];
            }
        }

        // Get providers
        $providers = $this->repository->getProviders();

        if ($this->config['preferred_source']) {
            $providers = $providers->filter(
                fn ($p) => $p->slug === $this->config['preferred_source']
            );
        }

        // Try each provider
        foreach ($providers as $provider) {
            try {
                $result = $this->lookupFromProvider($isbn, $provider);

                if ($result['success']) {
                    // Cache the result
                    $this->repository->cache($isbn, $result['data'], $provider->slug);

                    $this->logLookup($isbn, $userId, $objectId, true, $provider->slug, null, $startTime, false);

                    return [
                        'success' => true,
                        'data' => $result['data'],
                        'source' => $provider->slug,
                        'cached' => false,
                    ];
                }
            } catch (\Exception $e) {
                $this->log('warning', "Provider {$provider->slug} failed for ISBN {$isbn}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logLookup($isbn, $userId, $objectId, false, 'none', 'No provider returned results', $startTime, false);

        return [
            'success' => false,
            'error' => 'ISBN not found in any source',
        ];
    }

    /**
     * Lookup from a specific provider.
     */
    private function lookupFromProvider(string $isbn, object $provider): array
    {
        return match ($provider->slug) {
            'openlibrary' => $this->lookupOpenLibrary($isbn),
            'googlebooks' => $this->lookupGoogleBooks($isbn),
            'worldcat' => $this->lookupWorldCat($isbn, $provider),
            default => ['success' => false, 'error' => 'Unknown provider'],
        };
    }

    /**
     * Lookup from Open Library API.
     */
    private function lookupOpenLibrary(string $isbn): array
    {
        $url = "https://openlibrary.org/api/books?bibkeys=ISBN:{$isbn}&format=json&jscmd=data";

        $response = $this->httpGet($url);
        if (!$response) {
            return ['success' => false, 'error' => 'HTTP request failed'];
        }

        $data = json_decode($response, true);
        $key = "ISBN:{$isbn}";

        if (empty($data[$key])) {
            return ['success' => false, 'error' => 'ISBN not found'];
        }

        $book = $data[$key];

        return [
            'success' => true,
            'data' => $this->mapOpenLibraryData($book, $isbn),
        ];
    }

    /**
     * Lookup from Google Books API.
     */
    private function lookupGoogleBooks(string $isbn): array
    {
        $url = "https://www.googleapis.com/books/v1/volumes?q=isbn:{$isbn}";

        $response = $this->httpGet($url);
        if (!$response) {
            return ['success' => false, 'error' => 'HTTP request failed'];
        }

        $data = json_decode($response, true);

        if (empty($data['items'][0])) {
            return ['success' => false, 'error' => 'ISBN not found'];
        }

        $book = $data['items'][0]['volumeInfo'];

        return [
            'success' => true,
            'data' => $this->mapGoogleBooksData($book, $isbn),
        ];
    }

    /**
     * Lookup from WorldCat (requires API key).
     */
    private function lookupWorldCat(string $isbn, object $provider): array
    {
        // Get API key from settings
        $apiKey = $this->getApiKey($provider->api_key_setting);

        if (!$apiKey) {
            return ['success' => false, 'error' => 'WorldCat API key not configured'];
        }

        $url = "{$provider->api_endpoint}{$isbn}?wskey={$apiKey}";

        $response = $this->httpGet($url);
        if (!$response) {
            return ['success' => false, 'error' => 'HTTP request failed'];
        }

        // Parse MARC XML
        return $this->parseMarcXml($response, $isbn);
    }

    /**
     * Map Open Library response to standard format.
     */
    private function mapOpenLibraryData(array $book, string $isbn): array
    {
        $authors = [];
        if (!empty($book['authors'])) {
            foreach ($book['authors'] as $author) {
                $authors[] = ['name' => $author['name'], 'url' => $author['url'] ?? '']; 
            }
        }

        $subjects = [];
        if (!empty($book['subjects'])) {
            foreach ($book['subjects'] as $subject) {
                $subjects[] = ['name' => $subject['name'], 'url' => $subject['url'] ?? '']; 
            }
        }

        $publishers = [];
        if (!empty($book['publishers'])) {
            foreach ($book['publishers'] as $pub) {
                $publishers[] = $pub['name'];
            }
        }

        return [
            'title' => $book['title'] ?? null,
            'subtitle' => $book['subtitle'] ?? null,
            'authors' => $authors,
            'publishers' => $publishers,
            'publish_date' => $book['publish_date'] ?? null,
            'publish_places' => !empty($book['publish_places'])
                ? array_column($book['publish_places'], 'name')
                : [],
            'number_of_pages' => $book['number_of_pages'] ?? null,
            'subjects' => $subjects,
            'isbn_10' => $book['identifiers']['isbn_10'][0] ?? null,
            'isbn_13' => $book['identifiers']['isbn_13'][0] ?? null,
            'oclc_number' => $book['identifiers']['oclc'][0] ?? null,
            'lccn' => $book['identifiers']['lccn'][0] ?? null,
            'cover_url' => $book['cover']['medium'] ?? $book['cover']['small'] ?? null,
            'notes' => $book['notes'] ?? null,
            'table_of_contents' => $book['table_of_contents'] ?? null,
            'url' => $book['url'] ?? null,
            'edition_name' => $book['edition_name'] ?? null,
            'languages' => !empty($book['languages'])
                ? array_column($book['languages'], 'key')
                : [],
        ];
    }

    /**
     * Map Google Books response to standard format.
     */
    private function mapGoogleBooksData(array $book, string $isbn): array
    {
        $isbn10 = null;
        $isbn13 = null;

        if (!empty($book['industryIdentifiers'])) {
            foreach ($book['industryIdentifiers'] as $id) {
                if ('ISBN_10' === $id['type']) {
                    $isbn10 = $id['identifier'];
                } elseif ('ISBN_13' === $id['type']) {
                    $isbn13 = $id['identifier'];
                }
            }
        }

        return [
            'title' => $book['title'] ?? null,
            'subtitle' => $book['subtitle'] ?? null,
            'authors' => $book['authors'] ?? [],
            'publishers' => !empty($book['publisher']) ? [$book['publisher']] : [],
            'publish_date' => $book['publishedDate'] ?? null,
            'publish_places' => [],
            'number_of_pages' => $book['pageCount'] ?? null,
            'subjects' => $book['categories'] ?? [],
            'isbn_10' => $isbn10,
            'isbn_13' => $isbn13,
            'description' => $book['description'] ?? null,
            'cover_url' => $book['imageLinks']['thumbnail'] ?? null,
            'language' => $book['language'] ?? null,
            'preview_link' => $book['previewLink'] ?? null,
            'info_link' => $book['infoLink'] ?? null,
        ];
    }

    /**
     * Parse MARC XML response from WorldCat.
     */
    private function parseMarcXml(string $xml, string $isbn): array
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);

        if (!$doc) {
            return ['success' => false, 'error' => 'Invalid MARC XML'];
        }

        // Register namespaces
        $doc->registerXPathNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $getField = function ($tag, $subfield = 'a') use ($doc) {
            $result = $doc->xpath("//marc:datafield[@tag='{$tag}']/marc:subfield[@code='{$subfield}']");

            return !empty($result) ? (string) $result[0] : null;
        };

        $getAllSubfields = function ($tag, $subfield = 'a') use ($doc) {
            $results = $doc->xpath("//marc:datafield[@tag='{$tag}']/marc:subfield[@code='{$subfield}']");
            $values = [];
            foreach ($results as $r) {
                $values[] = (string) $r;
            }

            return $values;
        };

        return [
            'success' => true,
            'data' => [
                'title' => $getField('245', 'a'),
                'subtitle' => $getField('245', 'b'),
                'authors' => $getAllSubfields('100', 'a'),
                'additional_authors' => $getAllSubfields('700', 'a'),
                'publishers' => [$getField('260', 'b') ?? $getField('264', 'b')],
                'publish_date' => $getField('260', 'c') ?? $getField('264', 'c'),
                'publish_places' => [$getField('260', 'a') ?? $getField('264', 'a')],
                'physical_description' => $getField('300', 'a'),
                'subjects' => $getAllSubfields('650', 'a'),
                'isbn_10' => $this->extractIsbn($getAllSubfields('020', 'a'), 10),
                'isbn_13' => $this->extractIsbn($getAllSubfields('020', 'a'), 13),
                'oclc_number' => $getField('001'),
                'lccn' => $getField('010', 'a'),
                'edition' => $getField('250', 'a'),
                'series' => $getField('490', 'a'),
                'notes' => $getAllSubfields('500', 'a'),
                'language' => $getField('008') ? substr($getField('008'), 35, 3) : null,
            ],
        ];
    }

    /**
     * Extract ISBN of specific length from list.
     */
    private function extractIsbn(array $isbns, int $length): ?string
    {
        foreach ($isbns as $isbn) {
            $clean = preg_replace('/[\s-]/', '', $isbn);
            $clean = preg_replace('/\s*\(.*\)/', '', $clean); // Remove parenthetical notes
            if (strlen($clean) === $length) {
                return $clean;
            }
        }

        return null;
    }

    /**
     * Validate ISBN-10 or ISBN-13.
     */
    public function validateIsbn(string $isbn): bool
    {
        $isbn = $this->normalizeIsbn($isbn);

        if (10 === strlen($isbn)) {
            return $this->validateIsbn10($isbn);
        }

        if (13 === strlen($isbn)) {
            return $this->validateIsbn13($isbn);
        }

        return false;
    }

    /**
     * Validate ISBN-10.
     */
    private function validateIsbn10(string $isbn): bool
    {
        if (!preg_match('/^[0-9]{9}[0-9X]$/', $isbn)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; ++$i) {
            $sum += (int) $isbn[$i] * (10 - $i);
        }

        $last = 'X' === $isbn[9] ? 10 : (int) $isbn[9];
        $sum += $last;

        return 0 === $sum % 11;
    }

    /**
     * Validate ISBN-13.
     */
    private function validateIsbn13(string $isbn): bool
    {
        if (!preg_match('/^[0-9]{13}$/', $isbn)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 12; ++$i) {
            $sum += (int) $isbn[$i] * (0 === $i % 2 ? 1 : 3);
        }

        $check = (10 - ($sum % 10)) % 10;

        return $check === (int) $isbn[12];
    }

    /**
     * Normalize ISBN.
     */
    private function normalizeIsbn(string $isbn): string
    {
        return strtoupper(preg_replace('/[\s-]/', '', trim($isbn)));
    }

    /**
     * Get API key from settings.
     */
    private function getApiKey(?string $settingName): ?string
    {
        if (!$settingName) {
            return null;
        }

        // Use AtoM's setting system
        return \QubitSetting::getByName($settingName)?->getValue(['sourceCulture' => true]);
    }

    /**
     * HTTP GET request.
     */
    private function httpGet(string $url): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'AtoM/2.10 ISBN Lookup',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (200 !== $httpCode) {
            $this->log('warning', "HTTP {$httpCode} for URL: {$url}");

            return null;
        }

        return $response ?: null;
    }

    /**
     * Log lookup to audit trail.
     */
    private function logLookup(
        string $isbn,
        ?int $userId,
        ?int $objectId,
        bool $success,
        string $source,
        ?string $error,
        float $startTime,
        bool $cached
    ): void {
        $timeMs = (int) ((microtime(true) - $startTime) * 1000);

        $this->repository->audit([
            'isbn' => $isbn,
            'user_id' => $userId,
            'information_object_id' => $objectId,
            'source' => $cached ? "{$source} (cached)" : $source,
            'success' => $success,
            'error_message' => $error,
            'lookup_time_ms' => $timeMs,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    /**
     * Log message.
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $this->logger?->{$level}($message, $context);
    }
}
