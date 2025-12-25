<?php

declare(strict_types=1);

namespace AtomExtensions\Controllers;

use AtomExtensions\Services\DisplayModeService;

/**
 * Admin controller for global display mode settings.
 */
class AdminDisplaySettingsController
{
    protected DisplayModeService $service;

    public function __construct(?DisplayModeService $service = null)
    {
        $this->service = $service ?? new DisplayModeService();
    }

    /**
     * Get all global settings.
     */
    public function index(): array
    {
        $settings = $this->service->getAllGlobalSettings();

        $allModes = [
            'tree' => ['name' => 'Hierarchy', 'icon' => 'bi-diagram-3'],
            'grid' => ['name' => 'Grid', 'icon' => 'bi-grid-3x3-gap'],
            'gallery' => ['name' => 'Gallery', 'icon' => 'bi-images'],
            'list' => ['name' => 'List', 'icon' => 'bi-list-ul'],
            'timeline' => ['name' => 'Timeline', 'icon' => 'bi-clock-history'],
        ];

        return [
            'success' => true,
            'settings' => $settings->toArray(),
            'available_modes' => $allModes,
        ];
    }

    /**
     * Get settings for a specific module.
     */
    public function show(string $module): array
    {
        $settings = $this->service->getDisplaySettings($module);

        return [
            'success' => true,
            'module' => $module,
            'settings' => $settings,
        ];
    }

    /**
     * Update global settings for a module.
     */
    public function update(array $request): array
    {
        $module = $request['module'] ?? '';

        if (empty($module)) {
            return [
                'success' => false,
                'error' => 'Module is required',
            ];
        }

        $settings = [];

        // Extract settings from request
        $fields = [
            'display_mode', 'items_per_page', 'sort_field', 'sort_direction',
            'show_thumbnails', 'show_descriptions', 'card_size',
            'available_modes', 'allow_user_override', 'is_active',
        ];

        foreach ($fields as $field) {
            if (isset($request[$field])) {
                $settings[$field] = $request[$field];
            }
        }

        $success = $this->service->saveGlobalSettings($module, $settings);

        return [
            'success' => $success,
            'module' => $module,
            'message' => $success ? 'Settings saved successfully' : 'Failed to save settings',
        ];
    }

    /**
     * Reset module to defaults.
     */
    public function reset(array $request): array
    {
        $module = $request['module'] ?? '';

        if (empty($module)) {
            return [
                'success' => false,
                'error' => 'Module is required',
            ];
        }

        $success = $this->service->resetGlobalSettings($module);

        return [
            'success' => $success,
            'module' => $module,
            'message' => $success ? 'Settings reset to defaults' : 'Failed to reset settings',
        ];
    }

    /**
     * Get audit log.
     */
    public function auditLog(array $request): array
    {
        $filters = [];

        if (!empty($request['module'])) {
            $filters['module'] = $request['module'];
        }
        if (!empty($request['scope'])) {
            $filters['scope'] = $request['scope'];
        }

        $limit = min(500, max(10, (int) ($request['limit'] ?? 100)));
        $log = $this->service->getAuditLog($filters, $limit);

        return [
            'success' => true,
            'entries' => $log->toArray(),
            'total' => $log->count(),
        ];
    }
}
