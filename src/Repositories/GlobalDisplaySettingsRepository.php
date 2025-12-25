<?php

declare(strict_types=1);

namespace AtomExtensions\Repositories;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Repository for managing global display mode settings.
 *
 * Admin-configurable defaults that apply to all users unless overridden.
 */
class GlobalDisplaySettingsRepository
{
    /** @var string */
    protected string $table = 'display_mode_global';

    /** @var string */
    protected string $auditTable = 'display_mode_audit';

    /** @var array<string> All possible display modes */
    protected array $allModes = ['tree', 'grid', 'gallery', 'list', 'timeline'];

    /**
     * Get global settings for a module.
     *
     * @param string $module Module name
     *
     * @return array<string, mixed>|null
     */
    public function getGlobalSettings(string $module): ?array
    {
        $settings = DB::table($this->table)
            ->where('module', $module)
            ->where('is_active', 1)
            ->first();

        if (!$settings) {
            return null;
        }

        $result = (array) $settings;
        $result['available_modes'] = json_decode($result['available_modes'] ?? '[]', true);

        return $result;
    }

    /**
     * Get all global settings.
     *
     * @param bool $activeOnly Only return active settings
     *
     * @return Collection
     */
    public function getAllGlobalSettings(bool $activeOnly = true): Collection
    {
        $query = DB::table($this->table);

        if ($activeOnly) {
            $query->where('is_active', 1);
        }

        return $query->orderBy('module')->get()->map(function ($row) {
            $arr = (array) $row;
            $arr['available_modes'] = json_decode($arr['available_modes'] ?? '[]', true);

            return $arr;
        });
    }

    /**
     * Save global settings for a module.
     *
     * @param string               $module    Module name
     * @param array<string, mixed> $settings  Settings data
     * @param int|null             $changedBy User ID making the change
     *
     * @return bool
     */
    public function saveGlobalSettings(string $module, array $settings, ?int $changedBy = null): bool
    {
        $existing = $this->getGlobalSettings($module);

        // Prepare data
        $data = [
            'module' => $module,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Validate and set display mode
        if (isset($settings['display_mode']) && in_array($settings['display_mode'], $this->allModes, true)) {
            $data['display_mode'] = $settings['display_mode'];
        }

        // Set items per page (10-100 range)
        if (isset($settings['items_per_page'])) {
            $data['items_per_page'] = max(10, min(100, (int) $settings['items_per_page']));
        }

        // Set sort options
        if (isset($settings['sort_field'])) {
            $data['sort_field'] = $settings['sort_field'];
        }
        if (isset($settings['sort_direction']) && in_array($settings['sort_direction'], ['asc', 'desc'], true)) {
            $data['sort_direction'] = $settings['sort_direction'];
        }

        // Set display options
        if (isset($settings['show_thumbnails'])) {
            $data['show_thumbnails'] = (int) (bool) $settings['show_thumbnails'];
        }
        if (isset($settings['show_descriptions'])) {
            $data['show_descriptions'] = (int) (bool) $settings['show_descriptions'];
        }
        if (isset($settings['card_size']) && in_array($settings['card_size'], ['small', 'medium', 'large'], true)) {
            $data['card_size'] = $settings['card_size'];
        }

        // Set available modes (JSON array)
        if (isset($settings['available_modes']) && is_array($settings['available_modes'])) {
            $validModes = array_intersect($settings['available_modes'], $this->allModes);
            $data['available_modes'] = json_encode(array_values($validModes));
        }

        // Set override permission
        if (isset($settings['allow_user_override'])) {
            $data['allow_user_override'] = (int) (bool) $settings['allow_user_override'];
        }

        // Set active status
        if (isset($settings['is_active'])) {
            $data['is_active'] = (int) (bool) $settings['is_active'];
        }

        $success = false;

        if ($existing) {
            $success = DB::table($this->table)
                ->where('module', $module)
                ->update($data) >= 0;
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $success = DB::table($this->table)->insert($data);
        }

        // Log audit
        if ($success) {
            $this->logAudit(
                null,
                $module,
                $existing ? 'update' : 'create',
                $existing,
                $data,
                'global',
                $changedBy
            );
        }

        return $success;
    }

    /**
     * Get available modes for a module.
     *
     * @param string $module Module name
     *
     * @return array<string>
     */
    public function getAvailableModes(string $module): array
    {
        $settings = $this->getGlobalSettings($module);

        if ($settings && !empty($settings['available_modes'])) {
            return $settings['available_modes'];
        }

        // Default modes by module type
        $defaults = [
            'informationobject' => ['tree', 'grid', 'list', 'timeline'],
            'actor' => ['grid', 'list'],
            'repository' => ['grid', 'list'],
            'digitalobject' => ['grid', 'gallery', 'list'],
            'library' => ['grid', 'list'],
            'gallery' => ['grid', 'gallery', 'list'],
            'dam' => ['grid', 'gallery', 'list'],
            'search' => ['grid', 'list'],
        ];

        return $defaults[$module] ?? ['grid', 'list'];
    }

    /**
     * Check if user override is allowed for a module.
     *
     * @param string $module Module name
     *
     * @return bool
     */
    public function isUserOverrideAllowed(string $module): bool
    {
        $settings = $this->getGlobalSettings($module);

        return $settings ? (bool) $settings['allow_user_override'] : true;
    }

    /**
     * Reset a module to default settings.
     *
     * @param string   $module    Module name
     * @param int|null $changedBy User ID making the change
     *
     * @return bool
     */
    public function resetToDefaults(string $module, ?int $changedBy = null): bool
    {
        $existing = $this->getGlobalSettings($module);

        $defaults = [
            'informationobject' => ['display_mode' => 'list', 'items_per_page' => 30],
            'actor' => ['display_mode' => 'list', 'items_per_page' => 30],
            'repository' => ['display_mode' => 'grid', 'items_per_page' => 20],
            'digitalobject' => ['display_mode' => 'grid', 'items_per_page' => 24],
            'library' => ['display_mode' => 'list', 'items_per_page' => 30],
            'gallery' => ['display_mode' => 'gallery', 'items_per_page' => 12],
            'dam' => ['display_mode' => 'grid', 'items_per_page' => 24],
            'search' => ['display_mode' => 'list', 'items_per_page' => 30],
        ];

        $default = $defaults[$module] ?? ['display_mode' => 'list', 'items_per_page' => 30];
        $default['allow_user_override'] = 1;
        $default['show_thumbnails'] = 1;
        $default['show_descriptions'] = 1;
        $default['card_size'] = 'medium';

        $success = $this->saveGlobalSettings($module, $default, $changedBy);

        if ($success && $existing) {
            $this->logAudit(null, $module, 'reset', $existing, $default, 'global', $changedBy);
        }

        return $success;
    }

    /**
     * Log audit entry.
     *
     * @param int|null             $userId    User ID (null for global)
     * @param string               $module    Module name
     * @param string               $action    Action type
     * @param array|null           $oldValue  Previous value
     * @param array|null           $newValue  New value
     * @param string               $scope     'global' or 'user'
     * @param int|null             $changedBy User making change
     */
    protected function logAudit(
        ?int $userId,
        string $module,
        string $action,
        ?array $oldValue,
        ?array $newValue,
        string $scope,
        ?int $changedBy
    ): void {
        try {
            DB::table($this->auditTable)->insert([
                'user_id' => $userId,
                'module' => $module,
                'action' => $action,
                'old_value' => $oldValue ? json_encode($oldValue) : null,
                'new_value' => $newValue ? json_encode($newValue) : null,
                'scope' => $scope,
                'changed_by' => $changedBy,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            error_log('Display mode audit log failed: ' . $e->getMessage());
        }
    }

    /**
     * Get audit log entries.
     *
     * @param array<string, mixed> $filters Filter options
     * @param int                  $limit   Max entries
     *
     * @return Collection
     */
    public function getAuditLog(array $filters = [], int $limit = 100): Collection
    {
        $query = DB::table($this->auditTable)
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if (!empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }
        if (!empty($filters['scope'])) {
            $query->where('scope', $filters['scope']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        return $query->get();
    }
}
