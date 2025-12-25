<?php

/**
 * Derivative Watermark Service.
 *
 * Applies watermarks to derivative images (reference/thumbnail) during creation
 * Master files are never modified.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */

namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

class DerivativeWatermarkService
{
    private static ?string $systemWatermarkPath = null;
    private static ?string $customWatermarkPath = null;
    private static ?string $rootDir = null;

    // AtoM usage IDs
    const USAGE_MASTER = 140;
    const USAGE_REFERENCE = 141;
    const USAGE_THUMBNAIL = 142;

    /**
     * Get system watermark path.
     */
    private static function getSystemWatermarkPath(): string
    {
        if (self::$systemWatermarkPath === null) {
            $rootDir = class_exists('sfConfig')
                ? \sfConfig::get('sf_root_dir')
                : \sfConfig::get('sf_root_dir', dirname(__DIR__, 3));
            self::$systemWatermarkPath = $rootDir . '/images/watermarks/';
        }
        return self::$systemWatermarkPath;
    }

    /**
     * Get custom watermark path.
     */
    private static function getCustomWatermarkPath(): string
    {
        if (self::$customWatermarkPath === null) {
            $uploadDir = class_exists('sfConfig')
                ? \sfConfig::get('sf_upload_dir')
                : \sfConfig::get('sf_upload_dir', dirname(__DIR__, 3) . '/uploads');
            self::$customWatermarkPath = $uploadDir . '/watermarks/';
        }
        return self::$customWatermarkPath;
    }

    /**
     * Get root directory.
     */
    private static function getRootDir(): string
    {
        if (self::$rootDir === null) {
            self::$rootDir = class_exists('sfConfig')
                ? \sfConfig::get('sf_root_dir')
                : \sfConfig::get('sf_root_dir', dirname(__DIR__, 3));
        }
        return self::$rootDir;
    }

    /**
     * Apply watermark to a derivative image file.
     */
    public static function applyWatermark(string $imagePath, int $objectId): bool
    {
        if (!file_exists($imagePath)) {
            error_log("DerivativeWatermark: Image not found: $imagePath");
            return false;
        }

        $watermarkConfig = self::getWatermarkConfig($objectId);

        if (!$watermarkConfig) {
            error_log("DerivativeWatermark: No watermark config for object $objectId");
            return true;
        }

        if (!file_exists($watermarkConfig['path'])) {
            error_log("DerivativeWatermark: Watermark file not found: {$watermarkConfig['path']}");
            return false;
        }

        return self::compositeWatermark($imagePath, $watermarkConfig);
    }

    /**
     * Get watermark configuration for an object.
     * Priority: Security > Custom > Selected > Default
     */
    public static function getWatermarkConfig(int $objectId): ?array
    {
        // 1. Check security classification (highest priority)
        $security = DB::table('object_security_classification as osc')
            ->join('security_classification as sc', 'osc.classification_id', '=', 'sc.id')
            ->where('osc.object_id', $objectId)
            ->where('osc.active', 1)
            ->where('sc.watermark_required', 1)
            ->select('sc.code', 'sc.watermark_image', 'sc.level')
            ->first();

        if ($security && $security->watermark_image) {
            return [
                'type' => 'security',
                'code' => $security->code,
                'path' => self::getSystemWatermarkPath() . $security->watermark_image,
                'position' => 'repeat',
                'opacity' => 0.5,  // Higher opacity for security
            ];
        }

        // 2. Check object_watermark_setting table (separate from AtoM)
        $watermarkSetting = DB::table('object_watermark_setting')
            ->where('object_id', $objectId)
            ->first();

        if ($watermarkSetting && $watermarkSetting->watermark_enabled) {
            // 2a. Custom watermark selected?
            if ($watermarkSetting->custom_watermark_id) {
                $custom = DB::table('custom_watermark')
                    ->where('id', $watermarkSetting->custom_watermark_id)
                    ->where('active', 1)
                    ->first();

                if ($custom) {
                    return [
                        'type' => 'custom',
                        'name' => $custom->name,
                        'path' => $custom->file_path,
                        'position' => $watermarkSetting->position ?? $custom->position,
                        'opacity' => (float) ($watermarkSetting->opacity ?? $custom->opacity),
                    ];
                }
            }

            // 2b. System watermark type selected?
            if ($watermarkSetting->watermark_type_id) {
                $watermarkType = DB::table('watermark_type')
                    ->where('id', $watermarkSetting->watermark_type_id)
                    ->where('active', 1)
                    ->where('code', '!=', 'NONE')
                    ->first();

                if ($watermarkType && $watermarkType->image_file) {
                    return [
                        'type' => 'selected',
                        'code' => $watermarkType->code,
                        'path' => self::getSystemWatermarkPath() . $watermarkType->image_file,
                        'position' => $watermarkSetting->position ?? $watermarkType->position,
                        'opacity' => (float) ($watermarkSetting->opacity ?? $watermarkType->opacity),
                    ];
                }
            }
        }
        // 3. Check watermark_type on digital_object
        $doWatermark = DB::table('digital_object as do')
            ->join('watermark_type as wt', 'do.watermark_type_id', '=', 'wt.id')
            ->where('do.object_id', $objectId)
            ->where('do.watermark_enabled', 1)
            ->where('wt.active', 1)
            ->where('wt.code', '!=', 'NONE')
            ->select('wt.code', 'wt.image_file', 'wt.position', 'wt.opacity')
            ->first();

        if ($doWatermark && $doWatermark->image_file) {
            return [
                'type' => 'selected',
                'code' => $doWatermark->code,
                'path' => self::getSystemWatermarkPath() . $doWatermark->image_file,
                'position' => $doWatermark->position,
                'opacity' => (float) $doWatermark->opacity,
            ];
        }

        // 4. Check default watermark setting
        $defaultEnabled = DB::table('watermark_setting')
            ->where('setting_key', 'default_watermark_enabled')
            ->value('setting_value');

        if ($defaultEnabled === '1') {
            $defaultCode = DB::table('watermark_setting')
                ->where('setting_key', 'default_watermark_type')
                ->value('setting_value');

            if ($defaultCode && $defaultCode !== 'NONE') {
                $defaultType = DB::table('watermark_type')
                    ->where('code', $defaultCode)
                    ->where('active', 1)
                    ->first();

                if ($defaultType && $defaultType->image_file) {
                    return [
                        'type' => 'default',
                        'code' => $defaultType->code,
                        'path' => self::getSystemWatermarkPath() . $defaultType->image_file,
                        'position' => $defaultType->position,
                        'opacity' => (float) $defaultType->opacity,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Apply watermark using ImageMagick composite.
     */
    private static function compositeWatermark(string $imagePath, array $config): bool
    {
        // Use higher base opacity for visibility
        $opacity = isset($config['opacity']) ? max(30, (int) ($config['opacity'] * 100)) : 50;
        $position = $config['position'] ?? 'center';

        // Map position to ImageMagick gravity
        $gravityMap = [
            'top left' => 'NorthWest',
            'top center' => 'North',
            'top right' => 'NorthEast',
            'left center' => 'West',
            'center' => 'Center',
            'right center' => 'East',
            'bottom left' => 'SouthWest',
            'bottom center' => 'South',
            'bottom right' => 'SouthEast',
            'repeat' => 'Center',
        ];
        $gravity = $gravityMap[$position] ?? 'Center';

        // Create temp file
        $tempFile = $imagePath . '.tmp';

        if ($position === 'repeat') {
            // Tile the watermark across the entire image
            $cmd = sprintf(
                'composite -dissolve %d -tile %s %s %s 2>&1',
                $opacity,
                escapeshellarg($config['path']),
                escapeshellarg($imagePath),
                escapeshellarg($tempFile)
            );
        } else {
            $cmd = sprintf(
                'composite -dissolve %d -gravity %s %s %s %s 2>&1',
                $opacity,
                $gravity,
                escapeshellarg($config['path']),
                escapeshellarg($imagePath),
                escapeshellarg($tempFile)
            );
        }

        error_log("DerivativeWatermark: Running: $cmd");
        exec($cmd, $output, $returnCode);

        if ($returnCode === 0 && file_exists($tempFile)) {
            // Replace original with watermarked version
            rename($tempFile, $imagePath);
            error_log("DerivativeWatermark: Applied {$config['type']} watermark to $imagePath");
            return true;
        }

        error_log('DerivativeWatermark: Failed - ' . implode("\n", $output));
        @unlink($tempFile);
        return false;
    }

    /**
     * Regenerate watermarked derivatives for an object.
     * This recreates reference/thumbnail from master with new watermark.
     */
    public static function regenerateDerivatives(int $objectId): bool
    {
        error_log("DerivativeWatermark: regenerateDerivatives called for object $objectId");

        // Get master digital object - try multiple approaches
        $master = DB::table('digital_object')
            ->where('object_id', $objectId)
            ->where('usage_id', self::USAGE_MASTER)
            ->first();

        // If no master with usage_id 140, try finding any parent digital object
        if (!$master) {
            $master = DB::table('digital_object')
                ->where('object_id', $objectId)
                ->whereNull('parent_id')
                ->first();
        }

        if (!$master) {
            error_log("DerivativeWatermark: No master found for object $objectId");
            return false;
        }

        $rootDir = self::getRootDir();
        $basePath = $rootDir . $master->path;
        $masterFile = $basePath . $master->name;

        error_log("DerivativeWatermark: Master file: $masterFile");

        if (!file_exists($masterFile)) {
            error_log("DerivativeWatermark: Master file not found: $masterFile");
            return false;
        }

        // Get derivatives by parent_id
        $derivatives = DB::table('digital_object')
            ->where('parent_id', $master->id)
            ->whereIn('usage_id', [self::USAGE_REFERENCE, self::USAGE_THUMBNAIL])
            ->get();

        error_log("DerivativeWatermark: Found " . count($derivatives) . " derivatives");

        foreach ($derivatives as $derivative) {
            $derivativePath = $rootDir . $derivative->path . $derivative->name;
            error_log("DerivativeWatermark: Processing derivative: $derivativePath");

            // Determine size based on usage
            if ($derivative->usage_id == self::USAGE_REFERENCE) {
                $size = '480';
            } else {
                $size = '150';
            }

            // Regenerate from master
            $cmd = sprintf(
                'convert %s -resize %sx%s -quality 85 %s 2>&1',
                escapeshellarg($masterFile),
                $size,
                $size,
                escapeshellarg($derivativePath)
            );

            error_log("DerivativeWatermark: Regenerating: $cmd");
            exec($cmd, $output, $returnCode);

            if ($returnCode === 0) {
                // Apply watermark to the regenerated derivative
                self::applyWatermark($derivativePath, $objectId);
            } else {
                error_log("DerivativeWatermark: Failed to regenerate: " . implode("\n", $output));
            }
        }

        return true;
    }

    /**
     * Upload a custom watermark.
     */
    public static function uploadCustomWatermark(
        array $file,
        string $name,
        ?int $objectId = null,
        string $position = 'center',
        float $opacity = 0.4,
        int $userId = 0
    ): ?int {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            error_log("DerivativeWatermark: Invalid upload file");
            return null;
        }

        // Validate file type (PNG or GIF for transparency)
        $allowedTypes = ['image/png', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            error_log("DerivativeWatermark: Invalid file type: $mimeType");
            return null;
        }

        // Generate unique filename
        $ext = ($mimeType === 'image/gif') ? 'gif' : 'png';
        $filename = 'watermark_' . uniqid() . '.' . $ext;
        $customPath = self::getCustomWatermarkPath();
        $destPath = $customPath . $filename;

        // Ensure directory exists
        if (!is_dir($customPath)) {
            mkdir($customPath, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            error_log("DerivativeWatermark: Failed to move uploaded file to $destPath");
            return null;
        }

        chmod($destPath, 0644);

        // Insert into database
        $id = DB::table('custom_watermark')->insertGetId([
            'object_id' => $objectId,
            'name' => $name,
            'filename' => $filename,
            'file_path' => $destPath,
            'position' => $position,
            'opacity' => $opacity,
            'created_by' => $userId,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        error_log("DerivativeWatermark: Uploaded custom watermark ID $id");
        return $id;
    }

    /**
     * Get custom watermarks (global and for specific object).
     */
    public static function getCustomWatermarks(?int $objectId = null): array
    {
        $query = DB::table('custom_watermark')
            ->where('active', 1)
            ->where(function ($q) use ($objectId) {
                $q->whereNull('object_id');
                if ($objectId) {
                    $q->orWhere('object_id', $objectId);
                }
            })
            ->orderBy('object_id', 'desc')
            ->orderBy('name');

        return $query->get()->toArray();
    }
}