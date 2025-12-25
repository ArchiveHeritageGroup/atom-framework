<?php
/**
 * Updated methods for WorldCatService.php
 * 
 * Replace the existing mapOpenLibraryData and mapGoogleBooksData methods
 * with these versions that integrate BookCoverService.
 */

    /**
     * Map Open Library response to standard format.
     * 
     * Uses BookCoverService for cover URLs instead of API response.
     */
    private function mapOpenLibraryData(array $book, string $isbn): array
    {
        $authors = [];
        if (!empty($book['authors'])) {
            foreach ($book['authors'] as $author) {
                $authors[] = $author['name'];
            }
        }

        $subjects = [];
        if (!empty($book['subjects'])) {
            foreach ($book['subjects'] as $subject) {
                $subjects[] = $subject['name'];
            }
        }

        $publishers = [];
        if (!empty($book['publishers'])) {
            foreach ($book['publishers'] as $pub) {
                $publishers[] = $pub['name'];
            }
        }

        // Get cover URLs from BookCoverService (faster, more reliable)
        $covers = BookCoverService::getAllSizes($isbn);

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
            // Use BookCoverService URLs
            'cover_url' => $covers['medium'],
            'cover_url_small' => $covers['small'],
            'cover_url_large' => $covers['large'],
            'cover_source' => 'openlibrary',
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
     * 
     * Tries Open Library covers first, falls back to Google Books thumbnails.
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

        // Try Open Library covers first (better quality, no API key needed)
        $covers = BookCoverService::getAllSizes($isbn);
        
        // Keep Google Books cover as fallback
        $googleCover = null;
        if (!empty($book['imageLinks']['thumbnail'])) {
            $googleCover = str_replace('http://', 'https://', $book['imageLinks']['thumbnail']);
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
            // Primary: Open Library covers
            'cover_url' => $covers['medium'],
            'cover_url_small' => $covers['small'],
            'cover_url_large' => $covers['large'],
            'cover_source' => 'openlibrary',
            // Fallback: Google Books cover
            'cover_url_fallback' => $googleCover,
            'language' => $book['language'] ?? null,
            'preview_link' => $book['previewLink'] ?? null,
            'info_link' => $book['infoLink'] ?? null,
        ];
    }
