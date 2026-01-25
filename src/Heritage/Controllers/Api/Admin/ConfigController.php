<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Controllers\Api\Admin;

use AtomFramework\Heritage\Config\LandingConfigService;
use AtomFramework\Heritage\Filters\FilterService;
use AtomFramework\Heritage\Repositories\LandingConfigRepository;
use AtomFramework\Heritage\Repositories\StoryRepository;
use AtomFramework\Heritage\Repositories\HeroImageRepository;

/**
 * Admin Config Controller.
 *
 * Handles admin API requests for heritage configuration.
 * Called by Symfony actions in the plugin.
 */
class ConfigController
{
    private LandingConfigService $configService;
    private LandingConfigRepository $configRepo;
    private FilterService $filterService;
    private StoryRepository $storyRepo;
    private HeroImageRepository $heroRepo;
    private string $culture;

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
        $this->configService = new LandingConfigService($culture);
        $this->configRepo = new LandingConfigRepository();
        $this->filterService = new FilterService($culture);
        $this->storyRepo = new StoryRepository();
        $this->heroRepo = new HeroImageRepository();
    }

    /**
     * Set the culture for queries.
     */
    public function setCulture(string $culture): self
    {
        $this->culture = $culture;
        $this->configService->setCulture($culture);
        $this->filterService->setCulture($culture);

        return $this;
    }

    /**
     * Get current culture.
     */
    public function getCulture(): string
    {
        return $this->culture;
    }

    // ========================================================================
    // Landing Config
    // ========================================================================

    /**
     * GET /heritage/admin/api/landing-config
     */
    public function getLandingConfig(?int $institutionId = null): array
    {
        try {
            $config = $this->configRepo->getConfig($institutionId);

            return [
                'success' => true,
                'data' => $config,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * PUT /heritage/admin/api/landing-config
     */
    public function updateLandingConfig(array $data, ?int $institutionId = null): array
    {
        try {
            // Validate data
            $errors = $this->validateLandingConfig($data);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors,
                ];
            }

            // Filter allowed fields
            $allowedFields = [
                'hero_tagline',
                'hero_subtext',
                'hero_search_placeholder',
                'suggested_searches',
                'hero_rotation_seconds',
                'hero_effect',
                'show_curated_stories',
                'show_community_activity',
                'show_filters',
                'show_stats',
                'show_recent_additions',
                'stats_config',
                'primary_color',
                'secondary_color',
            ];

            $filteredData = array_intersect_key($data, array_flip($allowedFields));

            $id = $this->configService->saveConfig($filteredData, $institutionId);

            return [
                'success' => true,
                'data' => ['id' => $id],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate landing config data.
     */
    private function validateLandingConfig(array $data): array
    {
        $errors = [];

        if (isset($data['hero_tagline']) && strlen($data['hero_tagline']) > 500) {
            $errors['hero_tagline'] = 'Tagline must be 500 characters or less';
        }

        if (isset($data['hero_rotation_seconds'])) {
            if (!is_numeric($data['hero_rotation_seconds']) || $data['hero_rotation_seconds'] < 1 || $data['hero_rotation_seconds'] > 60) {
                $errors['hero_rotation_seconds'] = 'Rotation seconds must be between 1 and 60';
            }
        }

        if (isset($data['hero_effect']) && !in_array($data['hero_effect'], ['kenburns', 'fade', 'none'])) {
            $errors['hero_effect'] = 'Invalid hero effect';
        }

        if (isset($data['primary_color']) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $data['primary_color'])) {
            $errors['primary_color'] = 'Invalid color format (use #RRGGBB)';
        }

        return $errors;
    }

    // ========================================================================
    // Filters
    // ========================================================================

    /**
     * GET /heritage/admin/api/filters
     */
    public function getFilters(?int $institutionId = null): array
    {
        try {
            $filters = $this->filterService->getFiltersWithValues($institutionId);

            return [
                'success' => true,
                'data' => $filters,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * GET /heritage/admin/api/filters/:id
     */
    public function getFilter(int $id, ?int $institutionId = null): array
    {
        try {
            $filter = $this->filterService->getFilterById($id, $institutionId);

            if (!$filter) {
                return [
                    'success' => false,
                    'error' => 'Filter not found',
                ];
            }

            return [
                'success' => true,
                'data' => $filter,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * PUT /heritage/admin/api/filters/:id
     */
    public function updateFilter(int $id, array $data): array
    {
        try {
            $allowedFields = [
                'is_enabled',
                'display_name',
                'display_icon',
                'display_order',
                'show_on_landing',
                'show_in_search',
                'max_items_landing',
                'is_hierarchical',
                'allow_multiple',
            ];

            $filteredData = array_intersect_key($data, array_flip($allowedFields));

            $success = $this->filterService->updateInstitutionFilter($id, $filteredData);

            return [
                'success' => $success,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * POST /heritage/admin/api/filters/reorder
     */
    public function reorderFilters(array $filterOrders): array
    {
        try {
            $this->filterService->reorderFilters($filterOrders);

            return [
                'success' => true,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ========================================================================
    // Stories
    // ========================================================================

    /**
     * GET /heritage/admin/api/stories
     */
    public function getStories(?int $institutionId = null): array
    {
        try {
            $stories = $this->storyRepo->getAllStories($institutionId);

            return [
                'success' => true,
                'data' => $stories->toArray(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * GET /heritage/admin/api/stories/:id
     */
    public function getStory(int $id): array
    {
        try {
            $story = $this->storyRepo->findById($id);

            if (!$story) {
                return [
                    'success' => false,
                    'error' => 'Story not found',
                ];
            }

            return [
                'success' => true,
                'data' => $story,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * POST /heritage/admin/api/stories
     */
    public function createStory(array $data, ?int $institutionId = null): array
    {
        try {
            $errors = $this->validateStory($data);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors,
                ];
            }

            $data['institution_id'] = $institutionId;
            $id = $this->storyRepo->create($data);

            return [
                'success' => true,
                'data' => ['id' => $id],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * PUT /heritage/admin/api/stories/:id
     */
    public function updateStory(int $id, array $data): array
    {
        try {
            $errors = $this->validateStory($data, false);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors,
                ];
            }

            $success = $this->storyRepo->update($id, $data);

            return [
                'success' => $success,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * DELETE /heritage/admin/api/stories/:id
     */
    public function deleteStory(int $id): array
    {
        try {
            $success = $this->storyRepo->delete($id);

            return [
                'success' => $success,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate story data.
     */
    private function validateStory(array $data, bool $requireTitle = true): array
    {
        $errors = [];

        if ($requireTitle && empty($data['title'])) {
            $errors['title'] = 'Title is required';
        }

        if (isset($data['title']) && strlen($data['title']) > 255) {
            $errors['title'] = 'Title must be 255 characters or less';
        }

        if (isset($data['link_type']) && !in_array($data['link_type'], ['collection', 'search', 'external', 'page'])) {
            $errors['link_type'] = 'Invalid link type';
        }

        return $errors;
    }

    // ========================================================================
    // Hero Images
    // ========================================================================

    /**
     * GET /heritage/admin/api/hero-images
     */
    public function getHeroImages(?int $institutionId = null): array
    {
        try {
            $images = $this->heroRepo->getAllImages($institutionId);

            return [
                'success' => true,
                'data' => $images->toArray(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * POST /heritage/admin/api/hero-images
     */
    public function createHeroImage(array $data, ?int $institutionId = null): array
    {
        try {
            if (empty($data['image_path'])) {
                return [
                    'success' => false,
                    'errors' => ['image_path' => 'Image path is required'],
                ];
            }

            $data['institution_id'] = $institutionId;
            $id = $this->heroRepo->create($data);

            return [
                'success' => true,
                'data' => ['id' => $id],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * PUT /heritage/admin/api/hero-images/:id
     */
    public function updateHeroImage(int $id, array $data): array
    {
        try {
            $success = $this->heroRepo->update($id, $data);

            return [
                'success' => $success,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * DELETE /heritage/admin/api/hero-images/:id
     */
    public function deleteHeroImage(int $id): array
    {
        try {
            $success = $this->heroRepo->delete($id);

            return [
                'success' => $success,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * POST /heritage/admin/api/hero-images/reorder
     */
    public function reorderHeroImages(array $imageOrders): array
    {
        try {
            $this->heroRepo->reorder($imageOrders);

            return [
                'success' => true,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ========================================================================
    // Feature Toggles (Session 8)
    // ========================================================================

    /**
     * GET /heritage/admin/api/feature-toggles
     */
    public function getFeatureToggles(?int $institutionId = null): array
    {
        try {
            $service = new \AtomFramework\Heritage\Admin\FeatureToggleService();
            $toggles = $service->getAllToggles($institutionId);

            return [
                'success' => true,
                'data' => $toggles->toArray(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * PUT /heritage/admin/api/feature-toggles/:code
     */
    public function updateFeatureToggle(string $featureCode, array $data, ?int $institutionId = null): array
    {
        try {
            $service = new \AtomFramework\Heritage\Admin\FeatureToggleService();
            $success = $service->updateToggle($featureCode, $data, $institutionId);

            return [
                'success' => $success,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * POST /heritage/admin/api/feature-toggles/:code/toggle
     */
    public function toggleFeature(string $featureCode, ?int $institutionId = null): array
    {
        try {
            $service = new \AtomFramework\Heritage\Admin\FeatureToggleService();
            $success = $service->toggle($featureCode, $institutionId);
            $isEnabled = $service->isEnabled($featureCode, $institutionId);

            return [
                'success' => $success,
                'data' => ['is_enabled' => $isEnabled],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ========================================================================
    // Branding (Session 8)
    // ========================================================================

    /**
     * GET /heritage/admin/api/branding
     */
    public function getBrandingConfig(?int $institutionId = null): array
    {
        try {
            $service = new \AtomFramework\Heritage\Admin\BrandingService();
            $config = $service->getMergedConfig($institutionId);

            return [
                'success' => true,
                'data' => $config,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * PUT /heritage/admin/api/branding
     */
    public function updateBrandingConfig(array $data, ?int $institutionId = null): array
    {
        try {
            $service = new \AtomFramework\Heritage\Admin\BrandingService();

            // Validate colors
            foreach (['primary_color', 'secondary_color', 'accent_color'] as $colorField) {
                if (isset($data[$colorField]) && !$service->validateColor($data[$colorField])) {
                    return [
                        'success' => false,
                        'errors' => [$colorField => 'Invalid color format (use #RRGGBB)'],
                    ];
                }
            }

            $success = $service->updateConfig($data, $institutionId);

            return [
                'success' => $success,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ========================================================================
    // User Management (Session 8)
    // ========================================================================

    /**
     * GET /heritage/admin/api/users
     */
    public function getUsers(array $params = []): array
    {
        try {
            $service = new \AtomFramework\Heritage\Admin\UserManagementService();
            $result = $service->getUsers($params);

            return [
                'success' => true,
                'data' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * GET /heritage/admin/api/users/:id
     */
    public function getUser(int $userId): array
    {
        try {
            $service = new \AtomFramework\Heritage\Admin\UserManagementService();
            $user = $service->getUser($userId);

            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'User not found',
                ];
            }

            return [
                'success' => true,
                'data' => $user,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * GET /heritage/admin/api/trust-levels
     */
    public function getTrustLevels(): array
    {
        try {
            $service = new \AtomFramework\Heritage\Admin\UserManagementService();
            $levels = $service->getTrustLevels();

            return [
                'success' => true,
                'data' => $levels->toArray(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * POST /heritage/admin/api/users/:id/trust-level
     */
    public function assignUserTrustLevel(int $userId, array $data, ?int $grantedBy = null): array
    {
        try {
            $service = new \AtomFramework\Heritage\Admin\UserManagementService();
            $success = $service->assignTrustLevel(
                $userId,
                (int) $data['trust_level_id'],
                $grantedBy,
                $data['expires_at'] ?? null,
                $data['notes'] ?? null,
                $data['institution_id'] ?? null
            );

            return [
                'success' => $success,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * GET /heritage/admin/api/user-stats
     */
    public function getUserStats(): array
    {
        try {
            $service = new \AtomFramework\Heritage\Admin\UserManagementService();
            $stats = $service->getUserStats();

            return [
                'success' => true,
                'data' => $stats,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
