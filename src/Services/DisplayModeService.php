<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

use AtomExtensions\Repositories\DisplayPreferenceRepository;
use AtomExtensions\Repositories\GlobalDisplaySettingsRepository;

/**
 * Service for managing display modes across the application.
 *
 * Supports both global (admin) and user-level configuration.
 */
class DisplayModeService
{
    protected DisplayPreferenceRepository $userRepo;
    protected GlobalDisplaySettingsRepository $globalRepo;

    /** @var int|null Current user ID */
    protected ?int $currentUserId = null;

    public function __construct(
        ?DisplayPreferenceRepository $userRepo = null,
        ?GlobalDisplaySettingsRepository $globalRepo = null
    ) {
        $this->userRepo = $userRepo ?? new DisplayPreferenceRepository();
        $this->globalRepo = $globalRepo ?? new GlobalDisplaySettingsRepository();
    }

    /**
     * Set the current user ID.
     */
    public function setCurrentUser(?int $userId): self
    {
        $this->currentUserId = $userId ?? 0;

        return $this;
    }

    /**
     * Get current user ID from session if available.
     */
    public function getCurrentUserId(): int
    {
        if (null !== $this->currentUserId) {
            return $this->currentUserId;
        }

        if (class_exists('sfContext') && \sfContext::hasInstance()) {
            $user = \sfContext::getInstance()->getUser();
            if ($user && $user->isAuthenticated()) {
                $this->currentUserId = (int) $user->getAttribute('user_id');

                return $this->currentUserId;
            }
        }

        return 0;
    }

    /**
     * Get effective display settings for a module.
     *
     * Uses fallback chain: User → Global → Default
     */
    public function getDisplaySettings(string $module): array
    {
        $userId = $this->getCurrentUserId();

        return $this->userRepo->getPreference($userId, $module);
    }

    /**
     * Get current display mode for a module.
     */
    public function getCurrentMode(string $module): string
    {
        $settings = $this->getDisplaySettings($module);

        return $settings['display_mode'] ?? 'list';
    }

    /**
     * Get source of current settings.
     *
     * @return string 'user', 'global', or 'default'
     */
    public function getSettingsSource(string $module): string
    {
        $settings = $this->getDisplaySettings($module);

        return $settings['_source'] ?? 'default';
    }

    /**
     * Switch display mode for current user.
     */
    public function switchMode(string $module, string $mode): bool
    {
        $userId = $this->getCurrentUserId();

        return $this->userRepo->setDisplayMode($userId, $module, $mode);
    }

    /**
     * Save user display preferences.
     */
    public function savePreferences(string $module, array $prefs): bool
    {
        $userId = $this->getCurrentUserId();

        return $this->userRepo->savePreference($userId, $module, $prefs);
    }

    /**
     * Reset user preference to global/default.
     */
    public function resetToGlobal(string $module): bool
    {
        $userId = $this->getCurrentUserId();

        return $this->userRepo->resetToGlobal($userId, $module);
    }

    /**
     * Check if user has custom preference.
     */
    public function hasCustomPreference(string $module): bool
    {
        $userId = $this->getCurrentUserId();

        return $this->userRepo->hasCustomPreference($userId, $module);
    }

    /**
     * Check if user can override settings.
     */
    public function canOverride(string $module): bool
    {
        return $this->globalRepo->isUserOverrideAllowed($module);
    }

    /**
     * Get available modes for module with current selection.
     */
    public function getModeMetas(string $module): array
    {
        $available = $this->globalRepo->getAvailableModes($module);
        $allModes = $this->userRepo->getAvailableModes();
        $current = $this->getCurrentMode($module);

        $result = [];
        foreach ($available as $mode) {
            if (isset($allModes[$mode])) {
                $result[$mode] = array_merge($allModes[$mode], [
                    'active' => $mode === $current,
                ]);
            }
        }

        return $result;
    }

    /**
     * Get items per page for module.
     */
    public function getItemsPerPage(string $module): int
    {
        $settings = $this->getDisplaySettings($module);

        return (int) ($settings['items_per_page'] ?? 30);
    }

    /**
     * Get sort settings for module.
     */
    public function getSortSettings(string $module): array
    {
        $settings = $this->getDisplaySettings($module);

        return [
            'field' => $settings['sort_field'] ?? 'updated_at',
            'direction' => $settings['sort_direction'] ?? 'desc',
        ];
    }

    /**
     * Get CSS class for results container based on mode.
     */
    public function getContainerClass(string $mode): string
    {
        $classes = [
            'tree' => 'display-tree-view',
            'grid' => 'display-grid-view row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3',
            'gallery' => 'display-gallery-view row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4',
            'list' => 'display-list-view table-responsive',
            'timeline' => 'display-timeline-view',
        ];

        return $classes[$mode] ?? $classes['list'];
    }

    /**
     * Build toggle buttons HTML with user override indicator.
     */
    public function renderToggleButtons(string $module, string $baseUrl = '', bool $useAjax = true): string
    {
        $modes = $this->getModeMetas($module);

        if (count($modes) < 2) {
            return '';
        }

        $canOverride = $this->canOverride($module);
        $hasCustom = $this->hasCustomPreference($module);
        $source = $this->getSettingsSource($module);

        $html = '<div class="display-mode-wrapper">';

        // Mode toggle buttons
        $html .= '<div class="display-mode-toggle btn-group" role="group" aria-label="Display mode"';
        $html .= ' data-module="' . htmlspecialchars($module, ENT_QUOTES) . '"';
        if ($useAjax) {
            $html .= ' data-ajax="true"';
        }
        if (!$canOverride) {
            $html .= ' data-locked="true"';
        }
        $html .= '>';

        foreach ($modes as $mode => $meta) {
            $activeClass = $meta['active'] ? ' active' : '';
            $disabledAttr = !$canOverride ? ' disabled' : '';
            $url = $baseUrl ? $baseUrl . '?display_mode=' . $mode : '#';
            $title = htmlspecialchars($meta['description'], ENT_QUOTES);

            $html .= '<button type="button" class="btn btn-outline-secondary btn-sm' . $activeClass . '"';
            $html .= ' data-mode="' . $mode . '"';
            $html .= ' data-url="' . htmlspecialchars($url, ENT_QUOTES) . '"';
            $html .= ' title="' . $title . '"';
            $html .= $disabledAttr;
            if ($meta['active']) {
                $html .= ' aria-pressed="true"';
            }
            $html .= '>';
            $html .= '<i class="bi ' . $meta['icon'] . '"></i>';
            $html .= '<span class="visually-hidden">' . htmlspecialchars($meta['name'], ENT_QUOTES) . '</span>';
            $html .= '</button>';
        }

        $html .= '</div>';

        // Settings source indicator and reset button
        if ($hasCustom && $canOverride) {
            $html .= '<button type="button" class="btn btn-link btn-sm text-muted ms-2 reset-display-mode" ';
            $html .= 'data-module="' . htmlspecialchars($module, ENT_QUOTES) . '" ';
            $html .= 'title="Reset to default">';
            $html .= '<i class="bi bi-arrow-counterclockwise"></i>';
            $html .= '</button>';
        } elseif (!$canOverride) {
            $html .= '<span class="badge bg-secondary ms-2" title="Display mode is locked by administrator">';
            $html .= '<i class="bi bi-lock"></i>';
            $html .= '</span>';
        }

        $html .= '</div>';

        return $html;
    }

    // ==========================================
    // ADMIN METHODS (for global settings)
    // ==========================================

    /**
     * Get all global settings (admin).
     */
    public function getAllGlobalSettings(): \Illuminate\Support\Collection
    {
        return $this->globalRepo->getAllGlobalSettings();
    }

    /**
     * Save global settings for a module (admin).
     */
    public function saveGlobalSettings(string $module, array $settings): bool
    {
        $adminId = $this->getCurrentUserId();

        return $this->globalRepo->saveGlobalSettings($module, $settings, $adminId);
    }

    /**
     * Reset global settings to defaults (admin).
     */
    public function resetGlobalSettings(string $module): bool
    {
        $adminId = $this->getCurrentUserId();

        return $this->globalRepo->resetToDefaults($module, $adminId);
    }

    /**
     * Get audit log.
     */
    public function getAuditLog(array $filters = [], int $limit = 100): \Illuminate\Support\Collection
    {
        return $this->globalRepo->getAuditLog($filters, $limit);
    }
}
