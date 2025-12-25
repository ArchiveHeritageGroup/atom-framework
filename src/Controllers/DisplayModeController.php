<?php

declare(strict_types=1);

namespace AtomExtensions\Controllers;

use AtomExtensions\Services\DisplayModeService;

/**
 * Controller for display mode AJAX operations.
 */
class DisplayModeController
{
    protected DisplayModeService $service;

    public function __construct(?DisplayModeService $service = null)
    {
        $this->service = $service ?? new DisplayModeService();
    }

    /**
     * Handle display mode switch request.
     *
     * Called via AJAX: POST /api/display-mode/switch
     *
     * @param array<string, mixed> $request Request data
     *
     * @return array<string, mixed> JSON response
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

        $success = $this->service->switchMode($module, $mode);

        return [
            'success' => $success,
            'module' => $module,
            'mode' => $mode,
            'container_class' => $this->service->getContainerClass($mode),
            'template' => $this->service->getTemplatePartial($mode, $module),
        ];
    }

    /**
     * Get current display settings.
     *
     * Called via AJAX: GET /api/display-mode/settings
     *
     * @param array<string, mixed> $request Request data
     *
     * @return array<string, mixed> JSON response
     */
    public function getSettings(array $request): array
    {
        $module = $request['module'] ?? 'search';

        return [
            'success' => true,
            'settings' => $this->service->getDisplaySettings($module),
            'modes' => $this->service->getModeMetas($module),
        ];
    }

    /**
     * Save display preferences.
     *
     * Called via AJAX: POST /api/display-mode/preferences
     *
     * @param array<string, mixed> $request Request data
     *
     * @return array<string, mixed> JSON response
     */
    public function savePreferences(array $request): array
    {
        $module = $request['module'] ?? '';
        $prefs = $request['preferences'] ?? [];

        if (empty($module)) {
            return [
                'success' => false,
                'error' => 'Module is required',
            ];
        }

        $success = $this->service->savePreferences($module, $prefs);

        return [
            'success' => $success,
            'module' => $module,
        ];
    }
}
