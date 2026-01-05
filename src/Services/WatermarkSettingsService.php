<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * WatermarkSettingsService - Manages watermark configuration.
 *
 * All watermark data now stored in framework tables:
 * - object_watermark_setting (per-object settings)
 * - watermark_type (system watermark types)
 * - watermark_setting (global settings)
 * - custom_watermark (uploaded custom watermarks)
 */
class WatermarkSettingsService
{
    protected static string $watermarkPath = '/images/watermarks/';
    protected static string $cacheFile = '/tmp/cantaloupe_classifications.json';

    /**
     * Get a global setting value.
     */
    public static function getSetting(string $key, ?string $default = null): ?string
    {
        $value = DB::table('watermark_setting')
            ->where('setting_key', $key)
            ->value('setting_value');

        return $value ?? $default;
    }

    /**
     * Set a global setting value.
     */
    public static function setSetting(string $key, string $value): bool
    {
        $exists = DB::table('watermark_setting')->where('setting_key', $key)->exists();

        if ($exists) {
            return DB::table('watermark_setting')
                ->where('setting_key', $key)
                ->update([
                    'setting_value' => $value,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]) >= 0;
        }

        return DB::table('watermark_setting')->insert([
            'setting_key' => $key,
            'setting_value' => $value,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get all global settings.
     */
    public static function getAllSettings(): array
    {
        return DB::table('watermark_setting')
            ->pluck('setting_value', 'setting_key')
            ->toArray();
    }

    /**
     * Get watermark type by ID.
     */
    public static function getWatermarkType(int $id): ?object
    {
        return DB::table('watermark_type')
            ->where('id', $id)
            ->where('active', 1)
            ->first();
    }

    /**
     * Get watermark type by code.
     */
    public static function getWatermarkTypeByCode(string $code): ?object
    {
        return DB::table('watermark_type')
            ->where('code', $code)
            ->where('active', 1)
            ->first();
    }

    /**
     * Get all active watermark types.
     */
    public static function getWatermarkTypes(): array
    {
        return DB::table('watermark_type')
            ->where('active', 1)
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    /**
     * Get watermark configuration for an object.
     *
     * Priority:
     * 1. Security classification watermark (highest)
     * 2. Object-specific watermark (object_watermark_setting)
     * 3. Default watermark (global setting)
     */
    public static function getWatermarkConfig(int $objectId): ?array
    {
        // 1. Check security classification (highest priority)
        $security = DB::table('object_security_classification as osc')
            ->join('security_classification as sc', 'osc.classification_id', '=', 'sc.id')
            ->where('osc.object_id', $objectId)
            ->where('sc.watermark_required', 1)
            ->whereNotNull('sc.watermark_image')
            ->where('sc.watermark_image', '!=', '')
            ->select('sc.*')
            ->first();

        if ($security) {
            return [
                'type' => 'security',
                'code' => 'SECURITY',
                'image' => $security->watermark_image,
                'position' => 'repeat',
                'opacity' => 0.5,
                'source' => 'object_security_classification',
            ];
        }

        // 2. Check object_watermark_setting table
        $watermarkSetting = DB::table('object_watermark_setting as ows')
            ->leftJoin('watermark_type as wt', 'ows.watermark_type_id', '=', 'wt.id')
            ->leftJoin('custom_watermark as cw', 'ows.custom_watermark_id', '=', 'cw.id')
            ->where('ows.object_id', $objectId)
            ->select([
                'ows.watermark_enabled',
                'ows.watermark_type_id',
                'ows.custom_watermark_id',
                'ows.position',
                'ows.opacity',
                'wt.code as type_code',
                'wt.image_file as type_image',
                'wt.position as type_position',
                'wt.opacity as type_opacity',
                'cw.file_path as custom_path',
                'cw.name as custom_name',
                'cw.position as custom_position',
                'cw.opacity as custom_opacity',
            ])
            ->first();

        if ($watermarkSetting) {
            // If watermark explicitly disabled
            if (!$watermarkSetting->watermark_enabled) {
                return null;
            }

            // Custom watermark takes priority
            if ($watermarkSetting->custom_watermark_id && $watermarkSetting->custom_path) {
                return [
                    'type' => 'custom',
                    'code' => 'CUSTOM',
                    'image' => $watermarkSetting->custom_path,
                    'position' => $watermarkSetting->position ?? $watermarkSetting->custom_position ?? 'center',
                    'opacity' => (float) ($watermarkSetting->opacity ?? $watermarkSetting->custom_opacity ?? 0.40),
                    'source' => 'custom_watermark',
                    'name' => $watermarkSetting->custom_name,
                ];
            }

            // System watermark type
            if ($watermarkSetting->watermark_type_id && $watermarkSetting->type_code !== 'NONE' && $watermarkSetting->type_image) {
                return [
                    'type' => 'selected',
                    'code' => $watermarkSetting->type_code,
                    'image' => self::$watermarkPath . $watermarkSetting->type_image,
                    'position' => $watermarkSetting->position ?? $watermarkSetting->type_position ?? 'center',
                    'opacity' => (float) ($watermarkSetting->opacity ?? $watermarkSetting->type_opacity ?? 0.40),
                    'source' => 'object_watermark_setting',
                ];
            }
        }

        // 3. Check default watermark
        if (self::getSetting('default_watermark_enabled', '1') === '1') {
            $defaultCode = self::getSetting('default_watermark_type', 'COPYRIGHT');

            if ($defaultCode && $defaultCode !== 'NONE') {
                $wtype = self::getWatermarkTypeByCode($defaultCode);

                if ($wtype && $wtype->image_file) {
                    return [
                        'type' => 'default',
                        'code' => $wtype->code,
                        'image' => self::$watermarkPath . $wtype->image_file,
                        'position' => $wtype->position ?? 'center',
                        'opacity' => (float) ($wtype->opacity ?? 0.40),
                        'source' => 'default_setting',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Save watermark settings for an object.
     */
    public static function saveObjectWatermark(int $objectId, ?int $watermarkTypeId, bool $enabled = true): bool
    {
        $existing = DB::table('object_watermark_setting')
            ->where('object_id', $objectId)
            ->first();

        $data = [
            'watermark_type_id' => $watermarkTypeId,
            'watermark_enabled' => $enabled ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            return DB::table('object_watermark_setting')
                ->where('object_id', $objectId)
                ->update($data) >= 0;
        }

        $data['object_id'] = $objectId;
        $data['created_at'] = date('Y-m-d H:i:s');

        return DB::table('object_watermark_setting')->insert($data);
    }

    /**
     * Get all objects with custom watermarks for Cantaloupe cache.
     */
    public static function getObjectsWithWatermarks(): array
    {
        return DB::table('object_watermark_setting as ows')
            ->join('watermark_type as wt', 'ows.watermark_type_id', '=', 'wt.id')
            ->where('ows.watermark_enabled', 1)
            ->where('wt.active', 1)
            ->where('wt.code', '!=', 'NONE')
            ->select([
                'ows.object_id',
                'wt.code',
                'wt.image_file',
                'ows.position',
                'ows.opacity',
            ])
            ->get()
            ->toArray();
    }

    /**
     * Update Cantaloupe cache file with all watermark configurations.
     */
    public static function updateCantaloupeCache(): int
    {
        $cache = [];

        // Get security classifications
        $securityObjects = DB::table('object_security_classification as osc')
            ->join('security_classification as sc', 'osc.classification_id', '=', 'sc.id')
            ->where('sc.watermark_required', 1)
            ->whereNotNull('sc.watermark_image')
            ->where('sc.watermark_image', '!=', '')
            ->select('osc.object_id', 'sc.watermark_image')
            ->get();

        foreach ($securityObjects as $sec) {
            $cache[$sec->object_id] = [
                'type' => 'security',
                'image' => $sec->watermark_image,
                'position' => 'repeat',
                'opacity' => 0.5,
            ];
        }

        // Get object-specific watermarks
        $objectWatermarks = DB::table('object_watermark_setting as ows')
            ->leftJoin('watermark_type as wt', 'ows.watermark_type_id', '=', 'wt.id')
            ->leftJoin('custom_watermark as cw', 'ows.custom_watermark_id', '=', 'cw.id')
            ->where('ows.watermark_enabled', 1)
            ->select([
                'ows.object_id',
                'ows.position',
                'ows.opacity',
                'wt.code as type_code',
                'wt.image_file as type_image',
                'cw.file_path as custom_path',
            ])
            ->get();

        foreach ($objectWatermarks as $ow) {
            // Skip if security already set (higher priority)
            if (isset($cache[$ow->object_id])) {
                continue;
            }

            if ($ow->custom_path) {
                $cache[$ow->object_id] = [
                    'type' => 'custom',
                    'image' => $ow->custom_path,
                    'position' => $ow->position ?? 'center',
                    'opacity' => (float) ($ow->opacity ?? 0.40),
                ];
            } elseif ($ow->type_code && $ow->type_code !== 'NONE' && $ow->type_image) {
                $cache[$ow->object_id] = [
                    'type' => 'selected',
                    'code' => $ow->type_code,
                    'image' => self::$watermarkPath . $ow->type_image,
                    'position' => $ow->position ?? 'center',
                    'opacity' => (float) ($ow->opacity ?? 0.40),
                ];
            }
        }

        // Add default settings
        $defaultEnabled = self::getSetting('default_watermark_enabled', '1') === '1';
        $defaultCode = self::getSetting('default_watermark_type', 'COPYRIGHT');

        $cache['_default'] = [
            'enabled' => $defaultEnabled,
            'code' => $defaultCode,
        ];

        if ($defaultEnabled && $defaultCode && $defaultCode !== 'NONE') {
            $wtype = self::getWatermarkTypeByCode($defaultCode);
            if ($wtype && $wtype->image_file) {
                $cache['_default']['image'] = self::$watermarkPath . $wtype->image_file;
                $cache['_default']['position'] = $wtype->position ?? 'center';
                $cache['_default']['opacity'] = (float) ($wtype->opacity ?? 0.40);
            }
        }

        file_put_contents(self::$cacheFile, json_encode($cache, JSON_PRETTY_PRINT));

        return count($cache) - 1; // Exclude _default from count
    }
}
