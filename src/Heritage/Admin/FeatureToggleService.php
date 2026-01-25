<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Admin;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Feature Toggle Service.
 *
 * Manages feature flags for institutions.
 */
class FeatureToggleService
{
    /**
     * Check if a feature is enabled.
     */
    public function isEnabled(string $featureCode, ?int $institutionId = null): bool
    {
        $toggle = DB::table('heritage_feature_toggle')
            ->where('feature_code', $featureCode)
            ->where(function ($q) use ($institutionId) {
                if ($institutionId !== null) {
                    $q->where('institution_id', $institutionId)
                        ->orWhereNull('institution_id');
                } else {
                    $q->whereNull('institution_id');
                }
            })
            ->orderByRaw('institution_id IS NULL ASC')
            ->first();

        return $toggle ? (bool) $toggle->is_enabled : false;
    }

    /**
     * Get feature configuration.
     */
    public function getConfig(string $featureCode, ?int $institutionId = null): ?array
    {
        $toggle = DB::table('heritage_feature_toggle')
            ->where('feature_code', $featureCode)
            ->where(function ($q) use ($institutionId) {
                if ($institutionId !== null) {
                    $q->where('institution_id', $institutionId)
                        ->orWhereNull('institution_id');
                } else {
                    $q->whereNull('institution_id');
                }
            })
            ->orderByRaw('institution_id IS NULL ASC')
            ->first();

        if (!$toggle) {
            return null;
        }

        $config = $toggle->config_json;
        if (is_string($config)) {
            $config = json_decode($config, true);
        }

        return $config ?: [];
    }

    /**
     * Get all feature toggles for an institution.
     */
    public function getAllToggles(?int $institutionId = null): Collection
    {
        // Get global toggles first
        $globalToggles = DB::table('heritage_feature_toggle')
            ->whereNull('institution_id')
            ->get()
            ->keyBy('feature_code');

        if ($institutionId !== null) {
            // Override with institution-specific settings
            $institutionToggles = DB::table('heritage_feature_toggle')
                ->where('institution_id', $institutionId)
                ->get()
                ->keyBy('feature_code');

            foreach ($institutionToggles as $code => $toggle) {
                $globalToggles[$code] = $toggle;
            }
        }

        return $globalToggles->values();
    }

    /**
     * Update a feature toggle.
     */
    public function updateToggle(string $featureCode, array $data, ?int $institutionId = null): bool
    {
        $existing = DB::table('heritage_feature_toggle')
            ->where('feature_code', $featureCode);

        if ($institutionId !== null) {
            $existing->where('institution_id', $institutionId);
        } else {
            $existing->whereNull('institution_id');
        }

        $existing = $existing->first();

        $updateData = [
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (isset($data['is_enabled'])) {
            $updateData['is_enabled'] = $data['is_enabled'] ? 1 : 0;
        }

        if (isset($data['feature_name'])) {
            $updateData['feature_name'] = $data['feature_name'];
        }

        if (isset($data['config_json'])) {
            $updateData['config_json'] = is_array($data['config_json'])
                ? json_encode($data['config_json'])
                : $data['config_json'];
        }

        if ($existing) {
            return DB::table('heritage_feature_toggle')
                ->where('id', $existing->id)
                ->update($updateData) >= 0;
        }

        // Create new for institution override
        $updateData['institution_id'] = $institutionId;
        $updateData['feature_code'] = $featureCode;
        $updateData['feature_name'] = $data['feature_name'] ?? $featureCode;
        $updateData['created_at'] = date('Y-m-d H:i:s');

        return DB::table('heritage_feature_toggle')->insert($updateData);
    }

    /**
     * Toggle a feature on/off.
     */
    public function toggle(string $featureCode, ?int $institutionId = null): bool
    {
        $current = $this->isEnabled($featureCode, $institutionId);

        return $this->updateToggle($featureCode, ['is_enabled' => !$current], $institutionId);
    }

    /**
     * Create a new feature toggle.
     */
    public function createToggle(array $data, ?int $institutionId = null): int
    {
        $insertData = [
            'institution_id' => $institutionId,
            'feature_code' => $data['feature_code'],
            'feature_name' => $data['feature_name'] ?? $data['feature_code'],
            'is_enabled' => $data['is_enabled'] ?? 1,
            'config_json' => isset($data['config_json'])
                ? (is_array($data['config_json']) ? json_encode($data['config_json']) : $data['config_json'])
                : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return (int) DB::table('heritage_feature_toggle')->insertGetId($insertData);
    }

    /**
     * Delete a feature toggle.
     */
    public function deleteToggle(int $id): bool
    {
        return DB::table('heritage_feature_toggle')
            ->where('id', $id)
            ->delete() > 0;
    }
}
