<?php
declare(strict_types=1);

namespace AtoM\Framework\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Service for managing IIIF Viewer settings and rendering.
 */
class IiifViewerService
{
    private array $settings = [];
    private bool $loaded = false;

    /**
     * Load all settings from database.
     */
    public function loadSettings(): array
    {
        if ($this->loaded) {
            return $this->settings;
        }

        $rows = DB::table('iiif_viewer_settings')->get();
        foreach ($rows as $row) {
            $this->settings[$row->setting_key] = $row->setting_value;
        }
        $this->loaded = true;

        return $this->settings;
    }

    /**
     * Get a single setting.
     */
    public function getSetting(string $key, $default = null)
    {
        $this->loadSettings();
        return $this->settings[$key] ?? $default;
    }

    /**
     * Update a setting.
     */
    public function updateSetting(string $key, string $value): bool
    {
        $exists = DB::table('iiif_viewer_settings')->where('setting_key', $key)->exists();
        
        if ($exists) {
            DB::table('iiif_viewer_settings')
                ->where('setting_key', $key)
                ->update(['setting_value' => $value]);
        } else {
            DB::table('iiif_viewer_settings')->insert([
                'setting_key' => $key,
                'setting_value' => $value,
            ]);
        }
        
        $this->settings[$key] = $value;
        return true;
    }

    /**
     * Update multiple settings.
     */
    public function updateSettings(array $settings): bool
    {
        foreach ($settings as $key => $value) {
            $this->updateSetting($key, $value);
        }
        return true;
    }

    /**
     * Get all settings as array.
     */
    public function getAllSettings(): array
    {
        return $this->loadSettings();
    }

    /**
     * Get digital objects for an information object.
     */
    public function getDigitalObjects(int $objectId): array
    {
        return DB::table('digital_object')
            ->where('object_id', $objectId)
            ->select('id', 'name', 'path', 'mime_type', 'byte_size')
            ->get()
            ->all();
    }

    /**
     * Build IIIF image URL for Cantaloupe.
     */
    public function buildImageUrl(object $digitalObject, string $size = 'full'): string
    {
        $baseUrl = \sfConfig::get('app_siteBaseUrl', '');
        $imagePath = ltrim($digitalObject->path, '/');
        $cantaloupeId = str_replace('/', '_SL_', $imagePath) . $digitalObject->name;
        
        return "{$baseUrl}/iiif/2/{$cantaloupeId}/full/{$size}/0/default.jpg";
    }

    /**
     * Build thumbnail URL.
     */
    public function buildThumbnailUrl(object $digitalObject, int $width = 200): string
    {
        $baseUrl = \sfConfig::get('app_siteBaseUrl', '');
        $imagePath = ltrim($digitalObject->path, '/');
        $cantaloupeId = str_replace('/', '_SL_', $imagePath) . $digitalObject->name;
        
        return "{$baseUrl}/iiif/2/{$cantaloupeId}/full/{$width},/0/default.jpg";
    }

    /**
     * Get IIIF manifest URL for an object.
     */
    public function getManifestUrl(string $slug): string
    {
        $baseUrl = \sfConfig::get('app_siteBaseUrl', '');
        return "{$baseUrl}/iiif-manifest.php?slug={$slug}";
    }
}
