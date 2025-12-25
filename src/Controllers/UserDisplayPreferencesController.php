<?php

declare(strict_types=1);

namespace AtomExtensions\Controllers;

use AtomExtensions\Services\DisplayModeService;

/**
 * Controller for user display preferences.
 */
class UserDisplayPreferencesController
{
    protected DisplayModeService $service;

    public function __construct(?DisplayModeService $service = null)
    {
        $this->service = $service ?? new DisplayModeService();
    }

    /**
     * Get all user preferences.
     */
    public function index(): array
    {
        $modules = [
            'informationobject', 'actor', 'repository', 'digitalobject',
            'library', 'gallery', 'dam', 'search',
        ];

        $preferences = [];
        foreach ($modules as $module) {
            $settings = $this->service->getDisplaySettings($module);
            $preferences[$module] = [
                'settings' => $settings,
                'source' => $settings['_source'] ?? 'default',
                'can_override' => $this->service->canOverride($module),
                'has_custom' => $this->service->hasCustomPreference($module),
                'available_modes' => $this->service->getModeMetas($module),
            ];
        }

        return [
            'success' => true,
            'preferences' => $preferences,
        ];
    }

    /**
     * Get preference for a specific module.
     */
    public function show(string $module): array
    {
        $settings = $this->service->getDisplaySettings($module);

        return [
            'success' => true,
            'module' => $module,
            'settings' => $settings,
            'source' => $settings['_source'] ?? 'default',
            'can_override' => $this->service->canOverride($module),
            'has_custom' => $this->service->hasCustomPreference($module),
            'available_modes' => $this->service->getModeMetas($module),
        ];
    }

    /**
     * Save user preference.
     */
    public function save(array $request): array
    {
        $module = $request['module'] ?? '';

        if (empty($module)) {
            return [
                'success' => false,
                'error' => 'Module is required',
            ];
        }

        if (!$this->service->canOverride($module)) {
            return [
                'success' => false,
                'error' => 'User customization is disabled for this module',
            ];
        }

        $prefs = [];
        $fields = [
            'display_mode', 'items_per_page', 'sort_field', 'sort_direction',
            'show_thumbnails', 'show_descriptions', 'card_size',
        ];

        foreach ($fields as $field) {
            if (isset($request[$field])) {
                $prefs[$field] = $request[$field];
            }
        }

        $success = $this->service->savePreferences($module, $prefs);

        return [
            'success' => $success,
            'module' => $module,
            'message' => $success ? 'Preferences saved' : 'Failed to save preferences',
        ];
    }

    /**
     * Quick switch display mode.
     */
    public function switchMode(array $request): array
    {
        $module = $request['module'] ?? '';
        $mode = $request['mode'] ?? '';

        if (empty($module) || empty($mode)) {
            return [
                'success' => false,
                'error' => 'Module and mode are required',
            ];
        }

        if (!$this->service->canOverride($module)) {
            return [
                'success' => false,
                'error' => 'Display mode is locked by administrator',
                'locked' => true,
            ];
        }

        $success = $this->service->switchMode($module, $mode);

        return [
            'success' => $success,
            'module' => $module,
            'mode' => $mode,
            'container_class' => $this->service->getContainerClass($mode),
        ];
    }

    /**
     * Reset to global/default.
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

        $success = $this->service->resetToGlobal($module);

        // Get new effective settings
        $newSettings = $this->service->getDisplaySettings($module);

        return [
            'success' => $success,
            'module' => $module,
            'settings' => $newSettings,
            'message' => $success ? 'Reset to default' : 'Failed to reset',
        ];
    }
}
