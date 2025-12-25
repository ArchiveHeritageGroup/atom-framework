<?php

declare(strict_types=1);

namespace AtomFramework\Services;

/**
 * ISBN Lookup Service
 *
 * Scan-to-lookup functionality for library items.
 * Uses Open Library API (free) and Google Books as fallback.
 */
class IsbnLookupService
{
    private const OPEN_LIBRARY_API = 'https://openlibrary.org/api/books';
    private const GOOGLE_BOOKS_API = 'https://www.googleapis.com/books/v1/volumes';

    private GlamIdentifierService $identifierService;
    private ?string $googleApiKey;

    public function __construct(?string $googleApiKey = null)
    {
        $this->identifierService = new GlamIdentifierService();
        $this->googleApiKey = $googleApiKey;
    }

    public function lookupByIsbn(string $isbn): ?array
    {
        $type = $this->identifierService->detectIdentifierType($isbn);

        if (!in_array($type, [GlamIdentifierService::TYPE_ISBN10, GlamIdentifierService::TYPE_ISBN13])) {
            throw new \InvalidArgumentException('Invalid ISBN format');
        }

        $validation = $type === GlamIdentifierService::TYPE_ISBN13
            ? $this->identifierService->validateIsbn13($isbn)
            : $this->identifierService->validateIsbn10($isbn);

        if (!$validation['valid']) {
            throw new \InvalidArgumentException($validation['message']);
        }

        $normalizedIsbn = $validation['normalized'];
        $isbn13 = $type === GlamIdentifierService::TYPE_ISBN10
            ? $this->identifierService->convertIsbn10ToIsbn13($normalizedIsbn)
            : $normalizedIsbn;

        $result = $this->lookupOpenLibrary($isbn13 ?? $normalizedIsbn);

        if (!$result) {
            $result = $this->lookupGoogleBooks($isbn13 ?? $normalizedIsbn);
        }

        if ($result) {
            $result['source_isbn'] = $isbn;
            $result['normalized_isbn'] = $normalizedIsbn;
            $result['isbn13'] = $isbn13;
        }

        return $result;
    }

    private function lookupOpenLibrary(string $isbn): ?array
    {
        $url = self::OPEN_LIBRARY_API . '?' . http_build_query([
            'bibkeys' => 'ISBN:' . $isbn,
            'format' => 'json',
            'jscmd' => 'data',
        ]);

        $response = $this->httpGet($url);
        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        $key = 'ISBN:' . $isbn;

        if (empty($data[$key])) {
            return null;
        }

        $book = $data[$key];

        return [
            'source' => 'openlibrary',
            'title' => $book['title'] ?? null,
            'subtitle' => $book['subtitle'] ?? null,
            'authors' => array_map(fn($a) => $a['name'] ?? $a, $book['authors'] ?? []),
            'publishers' => array_map(
                fn($p) => is_array($p) ? ($p['name'] ?? '') : $p,
                $book['publishers'] ?? []
            ),
            'publish_date' => $book['publish_date'] ?? null,
            'publish_places' => array_map(
                fn($p) => is_array($p) ? ($p['name'] ?? '') : $p,
                $book['publish_places'] ?? []
            ),
            'number_of_pages' => $book['number_of_pages'] ?? null,
            'subjects' => array_map(
                fn($s) => is_array($s) ? ($s['name'] ?? '') : $s,
                $book['subjects'] ?? []
            ),
            'cover_url' => $book['cover']['medium'] ?? $book['cover']['small'] ?? null,
            'identifiers' => [
                'isbn_10' => $book['identifiers']['isbn_10'] ?? [],
                'isbn_13' => $book['identifiers']['isbn_13'] ?? [],
                'lccn' => $book['identifiers']['lccn'] ?? [],
                'oclc' => $book['identifiers']['oclc'] ?? [],
            ],
            'url' => $book['url'] ?? null,
        ];
    }

    private function lookupGoogleBooks(string $isbn): ?array
    {
        $params = ['q' => 'isbn:' . $isbn];
        if ($this->googleApiKey) {
            $params['key'] = $this->googleApiKey;
        }

        $url = self::GOOGLE_BOOKS_API . '?' . http_build_query($params);
        $response = $this->httpGet($url);

        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        if (empty($data['items'][0])) {
            return null;
        }

        $book = $data['items'][0]['volumeInfo'];
        $identifiers = [];

        foreach ($book['industryIdentifiers'] ?? [] as $id) {
            $type = strtolower(str_replace('_', '', $id['type']));
            $identifiers[$type][] = $id['identifier'];
        }

        return [
            'source' => 'googlebooks',
            'title' => $book['title'] ?? null,
            'subtitle' => $book['subtitle'] ?? null,
            'authors' => $book['authors'] ?? [],
            'publishers' => isset($book['publisher']) ? [$book['publisher']] : [],
            'publish_date' => $book['publishedDate'] ?? null,
            'publish_places' => [],
            'number_of_pages' => $book['pageCount'] ?? null,
            'subjects' => $book['categories'] ?? [],
            'cover_url' => $book['imageLinks']['thumbnail'] ?? null,
            'identifiers' => $identifiers,
            'language' => $book['language'] ?? null,
            'description' => $book['description'] ?? null,
            'url' => $book['infoLink'] ?? null,
        ];
    }

    public function lookupByIssn(string $issn): ?array
    {
        $validation = $this->identifierService->validateIssn($issn);
        if (!$validation['valid']) {
            throw new \InvalidArgumentException($validation['message']);
        }

        $url = 'https://openlibrary.org/search.json?' . http_build_query([
            'q' => 'issn:' . $validation['normalized'],
        ]);

        $response = $this->httpGet($url);
        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        if (empty($data['docs'][0])) {
            return null;
        }

        $doc = $data['docs'][0];

        return [
            'source' => 'openlibrary',
            'type' => 'periodical',
            'title' => $doc['title'] ?? null,
            'publishers' => $doc['publisher'] ?? [],
            'first_publish_year' => $doc['first_publish_year'] ?? null,
            'subjects' => $doc['subject'] ?? [],
            'issn' => $validation['normalized'],
        ];
    }

    private function httpGet(string $url): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'AtoM-AHG-Framework/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 200 && $response !== false) ? $response : null;
    }

    public function mapToLibraryFields(array $lookupResult): array
    {
        return [
            'title' => $lookupResult['title'] ?? '',
            'subtitle' => $lookupResult['subtitle'] ?? '',
            'creator' => implode('; ', $lookupResult['authors'] ?? []),
            'publisher' => implode('; ', $lookupResult['publishers'] ?? []),
            'date_of_publication' => $lookupResult['publish_date'] ?? '',
            'place_of_publication' => implode('; ', $lookupResult['publish_places'] ?? []),
            'extent' => $lookupResult['number_of_pages']
                ? $lookupResult['number_of_pages'] . ' pages'
                : '',
            'isbn' => $lookupResult['isbn13'] ?? $lookupResult['normalized_isbn'] ?? '',
            'lccn' => $lookupResult['identifiers']['lccn'][0] ?? '',
            'oclc_number' => $lookupResult['identifiers']['oclc'][0] ?? '',
            'subjects' => $lookupResult['subjects'] ?? [],
            'language' => $lookupResult['language'] ?? '',
            'scope_and_content' => $lookupResult['description'] ?? '',
            'external_url' => $lookupResult['url'] ?? '',
            'cover_url' => $lookupResult['cover_url'] ?? '',
        ];
    }
}
