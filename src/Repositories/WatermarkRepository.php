<?php

declare(strict_types=1);

namespace AtomExtensions\Repositories;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * WatermarkRepository - Central repository for all watermark operations.
 *
 * Replaces watermark columns previously in information_object and digital_object.
 * All watermark data now stored in framework tables:
 * - object_watermark_setting (per-object settings)
 * - custom_watermark (uploaded custom watermarks)
 * - watermark_type (system watermark types)
 * - watermark_setting (global settings)
 */
class WatermarkRepository
{
    protected static string $systemWatermarkPath = '/images/watermarks/';

    /**
     * Get watermark settings for an object.
     */
    public static function getObjectSettings(int $objectId): ?object
    {
        return DB::table('object_watermark_setting')
            ->where('object_id', $objectId)
            ->first();
    }

    /**
     * Get watermark settings with type details.
     */
    public static function getObjectSettingsWithType(int $objectId): ?object
    {
        return DB::table('object_watermark_setting as ows')
            ->leftJoin('watermark_type as wt', 'ows.watermark_type_id', '=', 'wt.id')
            ->leftJoin('custom_watermark as cw', 'ows.custom_watermark_id', '=', 'cw.id')
            ->where('ows.object_id', $objectId)
            ->select([
                'ows.*',
                'wt.code as type_code',
                'wt.name as type_name',
                'wt.image_file as type_image',
                'cw.name as custom_name',
                'cw.file_path as custom_path',
            ])
            ->first();
    }

    /**
     * Check if watermark is enabled for an object.
     */
    public static function isEnabled(int $objectId): bool
    {
        $setting = self::getObjectSettings($objectId);

        if ($setting) {
            return (bool) $setting->watermark_enabled;
        }

        // Fall back to global default
        return self::getGlobalSetting('default_watermark_enabled', '1') === '1';
    }

    /**
     * Save watermark settings for an object.
     */
    public static function saveObjectSettings(int $objectId, array $data): bool
    {
        $existing = self::getObjectSettings($objectId);

        $saveData = [
            'watermark_enabled' => $data['watermark_enabled'] ?? 1,
            'watermark_type_id' => $data['watermark_type_id'] ?? null,
            'custom_watermark_id' => $data['custom_watermark_id'] ?? null,
            'position' => $data['position'] ?? 'center',
            'opacity' => $data['opacity'] ?? 0.40,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            return DB::table('object_watermark_setting')
                ->where('object_id', $objectId)
                ->update($saveData) >= 0;
        }

        $saveData['object_id'] = $objectId;
        $saveData['created_at'] = date('Y-m-d H:i:s');

        return DB::table('object_watermark_setting')->insert($saveData);
    }

    /**
     * Delete watermark settings for an object.
     */
    public static function deleteObjectSettings(int $objectId): bool
    {
        return DB::table('object_watermark_setting')
            ->where('object_id', $objectId)
            ->delete() >= 0;
    }

    /**
     * Get effective watermark configuration for an object.
     * Checks: security classification > custom > type > global default.
     */
    public static function getEffectiveWatermark(int $objectId): ?array
    {
        // 1. Check security classification override
        if (self::getGlobalSetting('security_watermark_override', '1') === '1') {
            $security = DB::table('security_clearance')
                ->where('object_id', $objectId)
                ->where('watermark_image', '!=', '')
                ->whereNotNull('watermark_image')
                ->first();

            if ($security && $security->watermark_image) {
                return [
                    'type' => 'security',
                    'path' => $security->watermark_image,
                    'position' => 'repeat',
                    'opacity' => 0.5,
                ];
            }
        }

        // 2. Check object_watermark_setting
        $setting = self::getObjectSettingsWithType($objectId);

        if ($setting && $setting->watermark_enabled) {
            // 2a. Custom watermark
            if ($setting->custom_watermark_id && $setting->custom_path) {
                return [
                    'type' => 'custom',
                    'name' => $setting->custom_name,
                    'path' => $setting->custom_path,
                    'position' => $setting->position ?? 'center',
                    'opacity' => (float) ($setting->opacity ?? 0.40),
                ];
            }

            // 2b. System watermark type
            if ($setting->watermark_type_id && $setting->type_image) {
                return [
                    'type' => 'selected',
                    'code' => $setting->type_code,
                    'path' => self::$systemWatermarkPath . $setting->type_image,
                    'position' => $setting->position ?? 'center',
                    'opacity' => (float) ($setting->opacity ?? 0.40),
                ];
            }
        }

        // 3. Global default
        $defaultEnabled = self::getGlobalSetting('default_watermark_enabled', '1');
        if ($defaultEnabled === '1') {
            $defaultCode = self::getGlobalSetting('default_watermark_type', 'COPYRIGHT');

            if ($defaultCode && $defaultCode !== 'NONE') {
                $defaultType = DB::table('watermark_type')
                    ->where('code', $defaultCode)
                    ->where('active', 1)
                    ->first();

                if ($defaultType && $defaultType->image_file) {
                    return [
                        'type' => 'default',
                        'code' => $defaultType->code,
                        'path' => self::$systemWatermarkPath . $defaultType->image_file,
                        'position' => $defaultType->position ?? 'center',
                        'opacity' => (float) ($defaultType->opacity ?? 0.40),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Get all watermark types.
     */
    public static function getTypes(bool $activeOnly = true): Collection
    {
        $query = DB::table('watermark_type')->orderBy('sort_order');

        if ($activeOnly) {
            $query->where('active', 1);
        }

        return $query->get();
    }

    /**
     * Get all custom watermarks.
     */
    public static function getCustomWatermarks(?int $objectId = null, bool $includeGlobal = true): Collection
    {
        $query = DB::table('custom_watermark')->where('active', 1);

        if ($objectId !== null) {
            if ($includeGlobal) {
                $query->where(function ($q) use ($objectId) {
                    $q->whereNull('object_id')->orWhere('object_id', $objectId);
                });
            } else {
                $query->where('object_id', $objectId);
            }
        } elseif ($includeGlobal) {
            $query->whereNull('object_id');
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get a global watermark setting.
     */
    public static function getGlobalSetting(string $key, ?string $default = null): ?string
    {
        $value = DB::table('watermark_setting')
            ->where('setting_key', $key)
            ->value('setting_value');

        return $value ?? $default;
    }

    /**
     * Set a global watermark setting.
     */
    public static function setGlobalSetting(string $key, string $value, ?string $description = null): bool
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
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get all global settings.
     */
    public static function getAllGlobalSettings(): Collection
    {
        return DB::table('watermark_setting')->get()->keyBy('setting_key');
    }

    /**
     * Upload and save a custom watermark.
     */
    public static function saveCustomWatermark(
        array $file,
        string $name,
        ?int $objectId = null,
        string $position = 'center',
        float $opacity = 0.40,
        ?int $createdBy = null
    ): ?int {
        $uploadDir = sfConfig::get('sf_upload_dir') . '/watermarks/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'watermark_' . uniqid() . '.' . $ext;
        $filepath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return null;
        }

        $id = DB::table('custom_watermark')->insertGetId([
            'name' => $name,
            'file_path' => '/uploads/watermarks/' . $filename,
            'file_name' => $filename,
            'mime_type' => $file['type'] ?? 'image/png',
            'object_id' => $objectId,
            'position' => $position,
            'opacity' => $opacity,
            'active' => 1,
            'created_by' => $createdBy,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $id ?: null;
    }

    /**
     * Delete a custom watermark.
     */
    public static function deleteCustomWatermark(int $id): bool
    {
        $watermark = DB::table('custom_watermark')->where('id', $id)->first();

        if (!$watermark) {
            return false;
        }

        // Remove file
        $filepath = sfConfig::get('sf_web_dir') . $watermark->file_path;
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        // Remove references
        DB::table('object_watermark_setting')
            ->where('custom_watermark_id', $id)
            ->update(['custom_watermark_id' => null]);

        return DB::table('custom_watermark')->where('id', $id)->delete() > 0;
    }

    /**
     * Bulk update watermark settings for multiple objects.
     */
    public static function bulkUpdate(array $objectIds, array $settings): int
    {
        $updated = 0;

        foreach ($objectIds as $objectId) {
            if (self::saveObjectSettings((int) $objectId, $settings)) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Get objects with specific watermark type.
     */
    public static function getObjectsByType(int $typeId): Collection
    {
        return DB::table('object_watermark_setting')
            ->where('watermark_type_id', $typeId)
            ->pluck('object_id');
    }

    /**
     * Get objects with custom watermark.
     */
    public static function getObjectsByCustomWatermark(int $customId): Collection
    {
        return DB::table('object_watermark_setting')
            ->where('custom_watermark_id', $customId)
            ->pluck('object_id');
    }
}
