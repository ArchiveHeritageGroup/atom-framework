<?php

declare(strict_types=1);

namespace AtoM\Framework\Services;

use AtomExtensions\Helpers\CultureHelper;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * Service for managing condition report photo annotations.
 * Follows Spectrum 5.0 condition checking and technical assessment procedures.
 */
class ConditionAnnotationService
{
    private Logger $logger;

    public function __construct()
    {
        $this->logger = new Logger('condition');
        $logDir = '/var/log/atom';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        if (is_writable($logDir)) {
            $this->logger->pushHandler(
                new RotatingFileHandler($logDir . '/condition.log', 30, Logger::DEBUG)
            );
        }
    }

    /**
     * Get a condition photo by ID.
     */
    public function getPhoto(int $photoId): ?object
    {
        return DB::table('spectrum_condition_photo')
            ->where('id', $photoId)
            ->first();
    }

    /**
     * Get all photos for a condition check.
     */
    public function getPhotosForCheck(int $conditionCheckId): array
    {
        return DB::table('spectrum_condition_photo')
            ->where('condition_check_id', $conditionCheckId)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    /**
     * Get condition check details.
     */
    public function getConditionCheck(int $checkId): ?object
    {
        return DB::table('spectrum_condition_check')
            ->where('id', $checkId)
            ->first();
    }

    /**
     * Get condition check with object info.
     */
    public function getConditionCheckWithObject(int $checkId): ?object
    {
        $check = DB::table('spectrum_condition_check as c')
            ->leftJoin('information_object as io', 'c.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->select([
                'c.*',
                'io.identifier',
                's.slug',
                'ioi.title as object_title',
            ])
            ->where('c.id', $checkId)
            ->first();

        return $check;
    }

    /**
     * Get annotations for a photo.
     */
    public function getAnnotations(int $photoId): array
    {
        $photo = $this->getPhoto($photoId);
        if (!$photo || !$photo->annotations) {
            return [];
        }

        $annotations = json_decode($photo->annotations, true);
        return is_array($annotations) ? $annotations : [];
    }

    /**
     * Save annotations for a photo.
     */
    public function saveAnnotations(int $photoId, array $annotations, ?int $userId = null): bool
    {
        try {
            DB::table('spectrum_condition_photo')
                ->where('id', $photoId)
                ->update([
                    'annotations' => json_encode($annotations),
                    'updated_by' => $userId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $this->logger->info('Annotations saved', [
                'photo_id' => $photoId,
                'count' => count($annotations),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to save annotations', [
                'photo_id' => $photoId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get annotation statistics for a condition check.
     */
    public function getAnnotationStats(int $conditionCheckId): array
    {
        $photos = $this->getPhotosForCheck($conditionCheckId);
        $stats = [
            'total_photos' => count($photos),
            'annotated_photos' => 0,
            'total_annotations' => 0,
            'by_category' => [],
        ];

        foreach ($photos as $photo) {
            if ($photo->annotations) {
                $annotations = json_decode($photo->annotations, true);
                if (is_array($annotations) && count($annotations) > 0) {
                    $stats['annotated_photos']++;
                    $stats['total_annotations'] += count($annotations);

                    foreach ($annotations as $ann) {
                        $category = $ann['category'] ?? 'other';
                        if (!isset($stats['by_category'][$category])) {
                            $stats['by_category'][$category] = 0;
                        }
                        $stats['by_category'][$category]++;
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * Upload a condition photo.
     * Valid photo_type values: before, after, detail, damage, overall, other
     */
    public function uploadPhoto(
        int $conditionCheckId,
        array $file,
        string $photoType = 'detail',
        ?string $caption = null,
        ?int $userId = null
    ): ?int {
        try {
            // Validate file
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                $this->logger->error('Invalid upload - not an uploaded file');
                throw new \Exception('Invalid upload');
            }

            // Validate photo_type - must match enum values
            $validTypes = ['before', 'after', 'detail', 'damage', 'overall', 'other'];
            if (!in_array($photoType, $validTypes)) {
                $photoType = 'detail'; // Default to detail
            }

            // Generate filenames
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = sprintf('condition_%d_%s.%s', $conditionCheckId, uniqid(), $ext);
            $uploadDir = $this->getUploadPath();

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filepath = $uploadDir . '/' . $filename;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                $this->logger->error('Failed to move uploaded file', ['filepath' => $filepath]);
                throw new \Exception('Failed to move uploaded file');
            }

            // Get image dimensions
            $imageInfo = @getimagesize($filepath);
            $width = $imageInfo[0] ?? null;
            $height = $imageInfo[1] ?? null;

            // Create thumbnail
            $thumbnailFilename = $this->createThumbnail($filepath, $uploadDir);

            // Get sort order
            $maxSort = DB::table('spectrum_condition_photo')
                ->where('condition_check_id', $conditionCheckId)
                ->max('sort_order') ?? 0;

            // Insert record with correct column names
            $photoId = DB::table('spectrum_condition_photo')->insertGetId([
                'condition_check_id' => $conditionCheckId,
                'filename' => $filename,
                'original_filename' => $file['name'],
                'file_path' => '/uploads/condition_photos/' . $filename,
                'mime_type' => $file['type'] ?? mime_content_type($filepath),
                'file_size' => filesize($filepath),
                'width' => $width,
                'height' => $height,
                'caption' => $caption,
                'photo_type' => $photoType,
                'sort_order' => $maxSort + 1,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $userId,
            ]);

            // Update photo count on condition check
            DB::table('spectrum_condition_check')
                ->where('id', $conditionCheckId)
                ->increment('photo_count');

            $this->logger->info('Photo uploaded', [
                'photo_id' => $photoId,
                'condition_check_id' => $conditionCheckId,
                'filename' => $filename,
            ]);

            return $photoId;
        } catch (\Exception $e) {
            $this->logger->error('Photo upload failed', [
                'condition_check_id' => $conditionCheckId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create thumbnail for an image.
     */
    protected function createThumbnail(string $filepath, string $uploadDir, int $maxSize = 200): ?string
    {
        try {
            $imageInfo = getimagesize($filepath);
            if (!$imageInfo) {
                return null;
            }

            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $type = $imageInfo[2];

            // Calculate new dimensions
            if ($width > $height) {
                $newWidth = $maxSize;
                $newHeight = (int) ($height * ($maxSize / $width));
            } else {
                $newHeight = $maxSize;
                $newWidth = (int) ($width * ($maxSize / $height));
            }

            // Create source image
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $source = imagecreatefromjpeg($filepath);
                    break;
                case IMAGETYPE_PNG:
                    $source = imagecreatefrompng($filepath);
                    break;
                case IMAGETYPE_GIF:
                    $source = imagecreatefromgif($filepath);
                    break;
                case IMAGETYPE_WEBP:
                    $source = imagecreatefromwebp($filepath);
                    break;
                default:
                    return null;
            }

            if (!$source) {
                return null;
            }

            // Create thumbnail
            $thumb = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG/GIF
            if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
                imagefilledrectangle($thumb, 0, 0, $newWidth, $newHeight, $transparent);
            }

            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            // Save thumbnail
            $thumbFilename = 'thumb_' . basename($filepath);
            $thumbPath = $uploadDir . '/' . $thumbFilename;

            switch ($type) {
                case IMAGETYPE_JPEG:
                    imagejpeg($thumb, $thumbPath, 85);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($thumb, $thumbPath, 8);
                    break;
                case IMAGETYPE_GIF:
                    imagegif($thumb, $thumbPath);
                    break;
                case IMAGETYPE_WEBP:
                    imagewebp($thumb, $thumbPath, 85);
                    break;
            }

            imagedestroy($source);
            imagedestroy($thumb);

            return $thumbFilename;
        } catch (\Exception $e) {
            $this->logger->error('Thumbnail creation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Delete a photo.
     */
    public function deletePhoto(int $photoId, ?int $userId = null): bool
    {
        try {
            $photo = $this->getPhoto($photoId);
            if (!$photo) {
                return false;
            }

            // Delete files
            $uploadDir = $this->getUploadPath();
            if ($photo->filename) {
                $filepath = $uploadDir . '/' . $photo->filename;
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                // Delete thumbnail
                $thumbPath = $uploadDir . '/thumb_' . $photo->filename;
                if (file_exists($thumbPath)) {
                    unlink($thumbPath);
                }
            }

            // Delete record
            DB::table('spectrum_condition_photo')
                ->where('id', $photoId)
                ->delete();

            // Update photo count
            if ($photo->condition_check_id) {
                DB::table('spectrum_condition_check')
                    ->where('id', $photo->condition_check_id)
                    ->decrement('photo_count');
            }

            $this->logger->info('Photo deleted', ['photo_id' => $photoId]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Photo deletion failed', [
                'photo_id' => $photoId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Update photo metadata.
     */
    public function updatePhotoMeta(int $photoId, ?string $caption, string $photoType): bool
    {
        try {
            // Validate photo_type
            $validTypes = ['before', 'after', 'detail', 'damage', 'overall', 'other'];
            if (!in_array($photoType, $validTypes)) {
                $photoType = 'detail';
            }

            DB::table('spectrum_condition_photo')
                ->where('id', $photoId)
                ->update([
                    'caption' => $caption,
                    'photo_type' => $photoType,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Photo meta update failed', [
                'photo_id' => $photoId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get upload path from settings or default
     */
    protected function getUploadPath(): string
    {
        try {
            $result = \Illuminate\Database\Capsule\Manager::table('ahg_settings')
                ->where('setting_key', 'photo_upload_path')
                ->value('setting_value');
            
            if ($result && !empty(trim($result))) {
                return rtrim($result, '/');
            }
        } catch (\Exception $e) {
            // Fall back to default
        }
        
        return \sfConfig::get('sf_root_dir') . '/uploads/condition_photos';
    }
}
