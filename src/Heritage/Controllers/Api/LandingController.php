<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Controllers\Api;

use AtomFramework\Heritage\Config\LandingConfigService;

/**
 * Landing Controller.
 *
 * Handles API requests for the landing page.
 * Called by Symfony actions in the plugin.
 */
class LandingController
{
    private LandingConfigService $configService;
    private string $culture;

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
        $this->configService = new LandingConfigService($culture);
    }

    /**
     * Set the culture for queries.
     */
    public function setCulture(string $culture): self
    {
        $this->culture = $culture;
        $this->configService->setCulture($culture);

        return $this;
    }

    /**
     * GET /heritage/api/landing
     *
     * Returns all landing page configuration and data.
     */
    public function index(?int $institutionId = null, ?string $culture = null): array
    {
        try {
            if ($culture !== null) {
                $this->setCulture($culture);
            }

            $data = $this->configService->getLandingPageData($institutionId);

            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * GET /heritage/api/landing/config
     *
     * Returns only the configuration (for admin).
     */
    public function getConfig(?int $institutionId = null): array
    {
        try {
            $config = $this->configService->getLandingPageData($institutionId)['config'] ?? [];

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
     * GET /heritage/api/landing/filters
     *
     * Returns filters with values for landing page.
     */
    public function getFilters(?int $institutionId = null): array
    {
        try {
            $filters = $this->configService->getFiltersWithValues($institutionId);

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
}
