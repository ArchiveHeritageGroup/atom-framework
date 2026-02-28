<?php

namespace AtomFramework\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Service to download and store library book covers as AtoM digital objects
 */
class LibraryCoverService
{
    /**
     * Download cover from URL and save as AtoM digital object
     *
     * @param int $informationObjectId
     * @param string $coverUrl External URL of the cover image
     * @return bool True on success, false on failure
     */
    public function downloadAndSaveAsDigitalObject(int $informationObjectId, string $coverUrl): bool
    {
        if (empty($coverUrl) || empty($informationObjectId)) {
            return false;
        }

        try {
            // Check if digital object already exists
            $existing = DB::table('digital_object')
                ->where('object_id', $informationObjectId)
                ->first();

            if ($existing) {
                // Digital object already exists
                return true;
            }

            // Download the image
            $imageData = $this->downloadImage($coverUrl);
            if (!$imageData) {
                error_log("LibraryCoverService: Failed to download image from $coverUrl");
                return false;
            }

            // Get mime type
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($imageData);

            // Determine extension
            $extension = $this->getExtensionFromMime($mimeType);
            
            // Generate filename
            $filename = 'cover-' . $informationObjectId . '-' . time() . '.' . $extension;

            // Get information object for slug
            $io = DB::table('information_object')
                ->where('id', $informationObjectId)
                ->first();

            if (!$io) {
                error_log("LibraryCoverService: Information object not found: $informationObjectId");
                return false;
            }

            // Phase 5: Use StandaloneDigitalObjectWriteService (Laravel QB, no Propel)
            $doService = \AtomFramework\Services\Write\WriteServiceFactory::digitalObject();
            $doService->create($informationObjectId, $filename, $imageData);

            error_log("LibraryCoverService: Created digital object for IO $informationObjectId from $coverUrl");

            return true;

        } catch (\Exception $e) {
            error_log("LibraryCoverService Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Open Library cover URL for an ISBN
     *
     * @param string $isbn
     * @param string $size S, M, or L
     * @return string|null
     */
    public function getOpenLibraryCoverUrl(string $isbn, string $size = 'L'): ?string
    {
        $cleanIsbn = preg_replace('/[^0-9X]/', '', strtoupper($isbn));
        if (empty($cleanIsbn)) {
            return null;
        }

        return "https://covers.openlibrary.org/b/isbn/{$cleanIsbn}-{$size}.jpg";
    }

    /**
     * Check if Open Library has a cover for this ISBN
     *
     * @param string $isbn
     * @return bool
     */
    public function hasOpenLibraryCover(string $isbn): bool
    {
        $url = $this->getOpenLibraryCoverUrl($isbn, 'S');
        if (!$url) {
            return false;
        }

        $response = HttpClientService::get($url, [], ['timeout' => 10]);

        // Open Library returns a 1x1 pixel for missing covers
        return $response['status'] === 200 && strlen($response['body']) > 1000;
    }

    /**
     * Download image from URL using HttpClientService for SSRF protection.
     */
    private function downloadImage(string $url): ?string
    {
        $response = HttpClientService::get($url, [], ['timeout' => 30]);

        if ($response['status'] !== 200 || empty($response['body']) || strlen($response['body']) < 1000) {
            return null;
        }

        return $response['body'];
    }

    /**
     * Get extension from mime type
     */
    private function getExtensionFromMime(string $mimeType): string
    {
        return match($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg'
        };
    }

    /**
     * Legacy method - kept for compatibility but now saves to digital_object
     */
    public function downloadAndSaveCover(int $informationObjectId, string $coverUrl): ?string
    {
        $result = $this->downloadAndSaveAsDigitalObject($informationObjectId, $coverUrl);
        return $result ? 'digital_object' : null;
    }
}
