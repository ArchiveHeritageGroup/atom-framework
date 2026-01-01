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

            // Use QubitDigitalObject to create proper digital object with derivatives
            // Try to get from Propel identity map first (for new saves in same request)
            $informationObject = \QubitInformationObject::getById($informationObjectId);
            if (!$informationObject) {
                // Force refresh from database
                \Propel::getConnection()->commit();
                $informationObject = \QubitInformationObject::getById($informationObjectId);
            }
            if (!$informationObject) {
                error_log("LibraryCoverService: Could not load QubitInformationObject: $informationObjectId");
                return false;
            }

            // Create digital object using AtoM's native method
            $digitalObject = new \QubitDigitalObject();
            $digitalObject->assets[] = new \QubitAsset($filename, $imageData);
            $digitalObject->usageId = \QubitTerm::MASTER_ID;
            
            // Link to information object
            $informationObject->digitalObjectsRelatedByobjectId[] = $digitalObject;
            $informationObject->save();

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

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_NOBODY => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        curl_close($ch);

        // Open Library returns a 1x1 pixel for missing covers
        return $httpCode === 200 && $contentLength > 1000;
    }

    /**
     * Download image from URL
     */
    private function downloadImage(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'AtoM/2.10 (Archive Management System)',
        ]);

        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentLength = strlen($data);
        curl_close($ch);

        // Open Library returns a tiny 1x1 pixel for missing covers
        if ($httpCode !== 200 || empty($data) || $contentLength < 1000) {
            return null;
        }

        return $data;
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
