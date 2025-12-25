<?php

/**
 * Direct ISBN API Test (no Symfony, no auth)
 *
 * Tests the raw API endpoints directly.
 */

echo "\n=== Direct ISBN API Tests ===\n\n";

// Test Open Library
$isbn = '9780134685991';
echo "1. Open Library API for ISBN {$isbn}\n";

$url = "https://openlibrary.org/api/books?bibkeys=ISBN:{$isbn}&format=json&jscmd=data";
$response = file_get_contents($url);
$data = json_decode($response, true);

if (!empty($data["ISBN:{$isbn}"])) {
    $book = $data["ISBN:{$isbn}"];
    echo "   ✓ Title: {$book['title']}\n";
    echo "   ✓ Authors: " . implode(', ', array_column($book['authors'] ?? [], 'name')) . "\n";
} else {
    echo "   ✗ No data returned\n";
}

echo "\n";

// Test Google Books
echo "2. Google Books API for ISBN {$isbn}\n";

$url = "https://www.googleapis.com/books/v1/volumes?q=isbn:{$isbn}";
$response = file_get_contents($url);
$data = json_decode($response, true);

if (!empty($data['items'][0])) {
    $book = $data['items'][0]['volumeInfo'];
    echo "   ✓ Title: {$book['title']}\n";
    echo "   ✓ Authors: " . implode(', ', $book['authors'] ?? []) . "\n";
} else {
    echo "   ✗ No data returned\n";
}

echo "\n";

// Test Cover Image
echo "3. Open Library Cover for ISBN {$isbn}\n";

$coverUrl = "https://covers.openlibrary.org/b/isbn/{$isbn}-M.jpg";
$headers = get_headers($coverUrl, true);
$status = $headers[0] ?? '';
$contentLength = $headers['Content-Length'] ?? $headers['content-length'] ?? 0;

echo "   URL: {$coverUrl}\n";
echo "   Status: {$status}\n";
echo "   Size: {$contentLength} bytes\n";

if (strpos($status, '200') !== false || strpos($status, '302') !== false) {
    echo "   ✓ Cover available\n";
} else {
    echo "   ✗ Cover not available\n";
}

echo "\n";

// Test Invalid ISBN Cover
echo "4. Cover for Invalid ISBN (fallback test)\n";

$coverUrl = "https://covers.openlibrary.org/b/isbn/0000000000-M.jpg";
$headers = get_headers($coverUrl, true);
$contentLength = $headers['Content-Length'] ?? $headers['content-length'] ?? 0;

echo "   Size: {$contentLength} bytes\n";
if ($contentLength < 1000) {
    echo "   ✓ Returns placeholder (small file)\n";
} else {
    echo "   ? Unexpected response\n";
}

echo "\n=== Tests Complete ===\n\n";
