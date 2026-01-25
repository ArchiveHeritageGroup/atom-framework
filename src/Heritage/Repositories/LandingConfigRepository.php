<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Repositories;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Landing Config Repository.
 *
 * Provides database access for heritage_landing_config table.
 * Manages institution landing page configuration.
 */
class LandingConfigRepository
{
    /**
     * Get landing config for an institution (or default if null).
     */
    public function getConfig(?int $institutionId = null): ?object
    {
        $query = DB::table('heritage_landing_config');

        if ($institutionId !== null) {
            $query->where('institution_id', $institutionId);
        } else {
            $query->whereNull('institution_id');
        }

        $result = $query->first();

        if ($result) {
            // Decode JSON fields
            $result->suggested_searches = $this->decodeJson($result->suggested_searches);
            $result->hero_media = $this->decodeJson($result->hero_media);
            $result->stats_config = $this->decodeJson($result->stats_config);
        }

        return $result;
    }

    /**
     * Get config by ID.
     */
    public function findById(int $id): ?object
    {
        $result = DB::table('heritage_landing_config')
            ->where('id', $id)
            ->first();

        if ($result) {
            $result->suggested_searches = $this->decodeJson($result->suggested_searches);
            $result->hero_media = $this->decodeJson($result->hero_media);
            $result->stats_config = $this->decodeJson($result->stats_config);
        }

        return $result;
    }

    /**
     * Create or update landing config.
     */
    public function save(array $data, ?int $institutionId = null): int
    {
        $existing = $this->getConfig($institutionId);

        // Encode JSON fields
        if (isset($data['suggested_searches']) && is_array($data['suggested_searches'])) {
            $data['suggested_searches'] = json_encode($data['suggested_searches']);
        }
        if (isset($data['hero_media']) && is_array($data['hero_media'])) {
            $data['hero_media'] = json_encode($data['hero_media']);
        }
        if (isset($data['stats_config']) && is_array($data['stats_config'])) {
            $data['stats_config'] = json_encode($data['stats_config']);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        if ($existing) {
            DB::table('heritage_landing_config')
                ->where('id', $existing->id)
                ->update($data);

            return (int) $existing->id;
        }

        $data['institution_id'] = $institutionId;
        $data['created_at'] = date('Y-m-d H:i:s');

        return (int) DB::table('heritage_landing_config')->insertGetId($data);
    }

    /**
     * Update specific fields.
     */
    public function update(int $id, array $data): bool
    {
        // Encode JSON fields if present
        if (isset($data['suggested_searches']) && is_array($data['suggested_searches'])) {
            $data['suggested_searches'] = json_encode($data['suggested_searches']);
        }
        if (isset($data['hero_media']) && is_array($data['hero_media'])) {
            $data['hero_media'] = json_encode($data['hero_media']);
        }
        if (isset($data['stats_config']) && is_array($data['stats_config'])) {
            $data['stats_config'] = json_encode($data['stats_config']);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        return DB::table('heritage_landing_config')
            ->where('id', $id)
            ->update($data) > 0;
    }

    /**
     * Delete landing config.
     */
    public function delete(int $id): bool
    {
        return DB::table('heritage_landing_config')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Decode JSON string to array.
     */
    private function decodeJson(?string $json): ?array
    {
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
