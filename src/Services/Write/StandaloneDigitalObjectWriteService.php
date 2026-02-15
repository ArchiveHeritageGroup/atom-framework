<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Standalone digital object write service using Laravel Query Builder only.
 *
 * Clean implementation without Propel references or class_exists checks.
 * Unlike the Propel version, create() is fully implemented:
 *   1. Save file to uploads/r/{objectId}/
 *   2. Insert object -> digital_object with SHA-256 checksum
 *   3. Detect MIME type
 *   4. For images: generate reference + thumbnail via ImageMagick
 *   5. Insert derivative digital_object rows
 */
class StandaloneDigitalObjectWriteService implements DigitalObjectWriteServiceInterface
{
    use EntityWriteTrait;

    /** Image MIME types that support derivative generation. */
    private const IMAGE_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/tiff',
        'image/bmp', 'image/webp',
    ];

    public function create(int $objectId, string $filename, string $content, ?int $usageId = null): int
    {
        $usageId = $usageId ?? \QubitTerm::MASTER_ID;

        // Determine upload directory
        $uploadDir = \sfConfig::get('sf_upload_dir', '/usr/share/nginx/archive/uploads');
        $objectDir = $uploadDir . '/r/' . $objectId;
        if (!is_dir($objectDir)) {
            mkdir($objectDir, 0755, true);
        }

        // Save master file
        $filePath = $objectDir . '/' . $filename;
        file_put_contents($filePath, $content);

        // Compute checksum and detect MIME type
        $checksum = hash('sha256', $content);
        $mimeType = $this->detectMimeType($filePath, $filename);
        $byteSize = strlen($content);
        $mediaTypeId = $this->resolveMediaTypeId($mimeType);

        return DB::transaction(function () use (
            $objectId, $filename, $filePath, $uploadDir, $usageId,
            $checksum, $mimeType, $byteSize, $mediaTypeId
        ) {
            $now = date('Y-m-d H:i:s');

            $doObjectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitDigitalObject',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Relative path from uploads dir
            $relativePath = str_replace($uploadDir, '', $filePath);

            DB::table('digital_object')->insert([
                'id' => $doObjectId,
                'information_object_id' => $objectId,
                'usage_id' => $usageId,
                'name' => $filename,
                'path' => $relativePath,
                'mime_type' => $mimeType,
                'byte_size' => $byteSize,
                'checksum' => $checksum,
                'checksum_type' => 'sha256',
                'media_type_id' => $mediaTypeId,
                'sequence' => 0,
            ]);

            // Generate derivatives for raster images
            if (in_array($mimeType, self::IMAGE_MIMES, true)) {
                $this->generateDerivatives($doObjectId, $objectId, $filePath, $uploadDir, $mimeType);
            }

            return $doObjectId;
        });
    }

    public function updateMetadata(int $id, array $attributes): void
    {
        if (empty($attributes)) {
            return;
        }

        $attributes['updated_at'] = date('Y-m-d H:i:s');
        DB::table('digital_object')->where('id', $id)->update($attributes);

        DB::table('object')->where('id', $id)->update([
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function saveProperty(int $objectId, string $name, ?string $value, string $culture = 'en'): void
    {
        $property = DB::table('property')
            ->where('object_id', $objectId)
            ->where('name', $name)
            ->first();

        if (null === $value) {
            if ($property) {
                DB::table('property_i18n')->where('id', $property->id)->delete();
                DB::table('property')->where('id', $property->id)->delete();
                DB::table('object')->where('id', $property->id)->delete();
            }

            return;
        }

        if ($property) {
            DB::table('property_i18n')->updateOrInsert(
                ['id' => $property->id, 'culture' => $culture],
                ['value' => $value]
            );
        } else {
            $this->createPropertyRecord($objectId, $name, $value, $culture);
        }
    }

    public function createDerivative(int $parentId, array $attributes): int
    {
        $parentDo = DB::table('digital_object')->where('id', $parentId)->first();
        if (!$parentDo) {
            throw new \InvalidArgumentException("Parent digital object #{$parentId} not found.");
        }

        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitDigitalObject',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $doAttributes = array_merge([
            'id' => $objectId,
            'information_object_id' => $parentDo->information_object_id,
            'parent_id' => $parentId,
        ], $attributes);

        DB::table('digital_object')->insert($doAttributes);

        return $objectId;
    }

    public function delete(int $id): bool
    {
        // Delete derivatives first, then master
        $derivativeIds = DB::table('digital_object')
            ->where('parent_id', $id)
            ->pluck('id')
            ->toArray();

        foreach ($derivativeIds as $derivId) {
            DB::table('digital_object')->where('id', $derivId)->delete();
            DB::table('object')->where('id', $derivId)->delete();
        }

        $exists = DB::table('digital_object')->where('id', $id)->exists();
        if (!$exists) {
            return false;
        }

        DB::table('digital_object')->where('id', $id)->delete();
        DB::table('object')->where('id', $id)->delete();

        return true;
    }

    public function updateFileMetadata(int $id, array $metadata): void
    {
        $allowed = ['byte_size', 'mime_type', 'media_type_id', 'name', 'path', 'sequence', 'checksum', 'checksum_type'];
        $filtered = array_intersect_key($metadata, array_flip($allowed));

        if (!empty($filtered)) {
            $this->updateMetadata($id, $filtered);
        }
    }

    /**
     * Generate reference and thumbnail derivatives via ImageMagick.
     */
    private function generateDerivatives(
        int $masterDoId,
        int $ioId,
        string $masterPath,
        string $uploadDir,
        string $mimeType
    ): void {
        $dir = dirname($masterPath);
        $ext = pathinfo($masterPath, PATHINFO_EXTENSION) ?: 'jpg';

        // Reference image (480px wide)
        $refFilename = pathinfo($masterPath, PATHINFO_FILENAME) . '_reference.' . $ext;
        $refPath = $dir . '/' . $refFilename;
        $refCmd = sprintf(
            'convert %s -resize 480 %s 2>/dev/null',
            escapeshellarg($masterPath),
            escapeshellarg($refPath)
        );
        exec($refCmd, $output, $refResult);

        if (0 === $refResult && file_exists($refPath)) {
            $refUsageId = defined('\\QubitTerm::REFERENCE_ID') ? \QubitTerm::REFERENCE_ID : 141;
            $this->createDerivative($masterDoId, [
                'usage_id' => $refUsageId,
                'name' => $refFilename,
                'path' => str_replace($uploadDir, '', $refPath),
                'mime_type' => $mimeType,
                'byte_size' => filesize($refPath),
                'checksum' => hash_file('sha256', $refPath),
                'checksum_type' => 'sha256',
                'sequence' => 0,
            ]);
        }

        // Thumbnail (150x150 crop)
        $thumbFilename = pathinfo($masterPath, PATHINFO_FILENAME) . '_thumbnail.' . $ext;
        $thumbPath = $dir . '/' . $thumbFilename;
        $thumbCmd = sprintf(
            'convert %s -thumbnail 150x150^ -gravity center -extent 150x150 %s 2>/dev/null',
            escapeshellarg($masterPath),
            escapeshellarg($thumbPath)
        );
        exec($thumbCmd, $output, $thumbResult);

        if (0 === $thumbResult && file_exists($thumbPath)) {
            $thumbUsageId = defined('\\QubitTerm::THUMBNAIL_ID') ? \QubitTerm::THUMBNAIL_ID : 142;
            $this->createDerivative($masterDoId, [
                'usage_id' => $thumbUsageId,
                'name' => $thumbFilename,
                'path' => str_replace($uploadDir, '', $thumbPath),
                'mime_type' => $mimeType,
                'byte_size' => filesize($thumbPath),
                'checksum' => hash_file('sha256', $thumbPath),
                'checksum_type' => 'sha256',
                'sequence' => 0,
            ]);
        }
    }

    /**
     * Detect MIME type of a file.
     */
    private function detectMimeType(string $filePath, string $filename): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            if ($mimeType && 'application/octet-stream' !== $mimeType) {
                return $mimeType;
            }
        }

        // Fallback to extension mapping
        $extMap = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png', 'gif' => 'image/gif',
            'tiff' => 'image/tiff', 'tif' => 'image/tiff',
            'bmp' => 'image/bmp', 'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'mp3' => 'audio/mpeg', 'wav' => 'audio/wav',
            'mp4' => 'video/mp4', 'avi' => 'video/x-msvideo',
            'txt' => 'text/plain', 'xml' => 'application/xml',
            'json' => 'application/json',
        ];

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return $extMap[$ext] ?? 'application/octet-stream';
    }

    /**
     * Resolve AtoM media type term ID from MIME type prefix.
     */
    private function resolveMediaTypeId(string $mimeType): ?int
    {
        $prefix = explode('/', $mimeType)[0] ?? '';

        $mediaTypes = [
            'image' => defined('\\QubitTerm::IMAGE_ID') ? \QubitTerm::IMAGE_ID : 137,
            'audio' => defined('\\QubitTerm::AUDIO_ID') ? \QubitTerm::AUDIO_ID : 138,
            'video' => defined('\\QubitTerm::VIDEO_ID') ? \QubitTerm::VIDEO_ID : 139,
            'text' => defined('\\QubitTerm::TEXT_ID') ? \QubitTerm::TEXT_ID : 134,
        ];

        return $mediaTypes[$prefix] ?? null;
    }
}
