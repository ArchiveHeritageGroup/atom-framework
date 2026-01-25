<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Admin;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Branding Service.
 *
 * Manages institution branding configuration.
 */
class BrandingService
{
    /**
     * Get branding configuration for institution.
     */
    public function getConfig(?int $institutionId = null): ?object
    {
        $config = DB::table('heritage_branding_config');

        if ($institutionId !== null) {
            $config->where('institution_id', $institutionId);
        } else {
            $config->whereNull('institution_id');
        }

        return $config->first();
    }

    /**
     * Get merged branding config (institution + global defaults).
     */
    public function getMergedConfig(?int $institutionId = null): array
    {
        $defaults = [
            'logo_path' => null,
            'favicon_path' => null,
            'primary_color' => '#0d6efd',
            'secondary_color' => '#6c757d',
            'accent_color' => null,
            'banner_text' => null,
            'footer_text' => 'Powered by AtoM Heritage Platform',
            'custom_css' => null,
            'social_links' => [],
            'contact_info' => [],
        ];

        // Get global config
        $globalConfig = DB::table('heritage_branding_config')
            ->whereNull('institution_id')
            ->first();

        if ($globalConfig) {
            $defaults = $this->mergeConfig($defaults, $globalConfig);
        }

        // Override with institution-specific config
        if ($institutionId !== null) {
            $instConfig = DB::table('heritage_branding_config')
                ->where('institution_id', $institutionId)
                ->first();

            if ($instConfig) {
                $defaults = $this->mergeConfig($defaults, $instConfig);
            }
        }

        return $defaults;
    }

    /**
     * Merge config object into defaults.
     */
    private function mergeConfig(array $defaults, object $config): array
    {
        foreach ($defaults as $key => $value) {
            if (isset($config->$key) && $config->$key !== null) {
                $val = $config->$key;

                // Decode JSON fields
                if (in_array($key, ['social_links', 'contact_info']) && is_string($val)) {
                    $val = json_decode($val, true) ?: [];
                }

                $defaults[$key] = $val;
            }
        }

        return $defaults;
    }

    /**
     * Update branding configuration.
     */
    public function updateConfig(array $data, ?int $institutionId = null): bool
    {
        $existing = DB::table('heritage_branding_config');

        if ($institutionId !== null) {
            $existing->where('institution_id', $institutionId);
        } else {
            $existing->whereNull('institution_id');
        }

        $existing = $existing->first();

        $allowedFields = [
            'logo_path',
            'favicon_path',
            'primary_color',
            'secondary_color',
            'accent_color',
            'banner_text',
            'footer_text',
            'custom_css',
            'social_links',
            'contact_info',
        ];

        $updateData = [
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];

                // Encode JSON fields
                if (in_array($field, ['social_links', 'contact_info']) && is_array($value)) {
                    $value = json_encode($value);
                }

                $updateData[$field] = $value;
            }
        }

        if ($existing) {
            return DB::table('heritage_branding_config')
                ->where('id', $existing->id)
                ->update($updateData) >= 0;
        }

        // Create new
        $updateData['institution_id'] = $institutionId;
        $updateData['created_at'] = date('Y-m-d H:i:s');

        return DB::table('heritage_branding_config')->insert($updateData);
    }

    /**
     * Validate color format.
     */
    public function validateColor(?string $color): bool
    {
        if ($color === null || $color === '') {
            return true;
        }

        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $color);
    }
}
