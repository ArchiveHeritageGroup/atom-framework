<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * WatermarkService - Handles watermark application to images.
 * Uses object_watermark_setting table for all watermark configuration.
 */
class WatermarkService
{
    protected static string $watermarkPath = '/images/watermarks/';

    public static function getWatermarkConfig(int $objectId): ?array
    {
        return WatermarkSettingsService::getWatermarkConfig($objectId);
    }

    public static function hasWatermark(int $objectId): bool
    {
        $hasSecurity = DB::table('security_clearance')
            ->where('object_id', $objectId)
            ->whereNotNull('watermark_image')
            ->where('watermark_image', '!=', '')
            ->exists();
        if ($hasSecurity) {
            return true;
        }

        $setting = DB::table('object_watermark_setting')
            ->where('object_id', $objectId)
            ->first();
        if ($setting) {
            if (!$setting->watermark_enabled) {
                return false;
            }
            if ($setting->custom_watermark_id || $setting->watermark_type_id) {
                return true;
            }
        }
        return WatermarkSettingsService::getSetting('default_watermark_enabled', '1') === '1';
    }

    public static function getWatermarkImage(int $objectId): ?string
    {
        $config = self::getWatermarkConfig($objectId);
        return $config['image'] ?? null;
    }

    public static function applyWatermark(string $imagePath, int $objectId): bool
    {
        $config = self::getWatermarkConfig($objectId);
        if (!$config || !isset($config['image'])) {
            return false;
        }
        $watermarkPath = sfConfig::get('sf_web_dir') . $config['image'];
        if (!file_exists($watermarkPath)) {
            return false;
        }
        $position = $config['position'] ?? 'center';
        $opacity = (int) (($config['opacity'] ?? 0.40) * 100);
        $gravity = self::getGravity($position);

        if ($position === 'repeat') {
            $command = sprintf('composite -dissolve %d -tile %s %s %s',
                $opacity, escapeshellarg($watermarkPath), escapeshellarg($imagePath), escapeshellarg($imagePath));
        } else {
            $command = sprintf('composite -dissolve %d -gravity %s %s %s %s',
                $opacity, $gravity, escapeshellarg($watermarkPath), escapeshellarg($imagePath), escapeshellarg($imagePath));
        }
        exec($command, $output, $returnCode);
        return $returnCode === 0;
    }

    protected static function getGravity(string $position): string
    {
        $map = [
            'top-left'=>'NorthWest', 'top-center'=>'North', 'top-right'=>'NorthEast',
            'center-left'=>'West', 'center'=>'Center', 'center-right'=>'East',
            'bottom-left'=>'SouthWest', 'bottom-center'=>'South', 'bottom-right'=>'SouthEast',
        ];
        return $map[$position] ?? 'Center';
    }
}
