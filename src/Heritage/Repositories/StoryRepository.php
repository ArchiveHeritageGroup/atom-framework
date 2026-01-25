<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Repositories;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Story Repository.
 *
 * Provides database access for heritage_curated_story table.
 * Manages featured stories and collections on landing page.
 */
class StoryRepository
{
    /**
     * Get enabled stories for institution.
     */
    public function getEnabledStories(?int $institutionId = null, ?int $limit = null): Collection
    {
        $query = DB::table('heritage_curated_story')
            ->where('is_enabled', 1);

        if ($institutionId !== null) {
            $query->where('institution_id', $institutionId);
        } else {
            $query->whereNull('institution_id');
        }

        // Check date range
        $today = date('Y-m-d');
        $query->where(function ($q) use ($today) {
            $q->whereNull('start_date')
                ->orWhere('start_date', '<=', $today);
        });
        $query->where(function ($q) use ($today) {
            $q->whereNull('end_date')
                ->orWhere('end_date', '>=', $today);
        });

        $query->orderByDesc('is_featured')
            ->orderBy('display_order');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get featured stories only.
     */
    public function getFeaturedStories(?int $institutionId = null, int $limit = 3): Collection
    {
        $query = DB::table('heritage_curated_story')
            ->where('is_enabled', 1)
            ->where('is_featured', 1);

        if ($institutionId !== null) {
            $query->where('institution_id', $institutionId);
        } else {
            $query->whereNull('institution_id');
        }

        // Check date range
        $today = date('Y-m-d');
        $query->where(function ($q) use ($today) {
            $q->whereNull('start_date')
                ->orWhere('start_date', '<=', $today);
        });
        $query->where(function ($q) use ($today) {
            $q->whereNull('end_date')
                ->orWhere('end_date', '>=', $today);
        });

        return $query->orderBy('display_order')
            ->limit($limit)
            ->get();
    }

    /**
     * Get all stories (for admin).
     */
    public function getAllStories(?int $institutionId = null): Collection
    {
        $query = DB::table('heritage_curated_story');

        if ($institutionId !== null) {
            $query->where('institution_id', $institutionId);
        } else {
            $query->whereNull('institution_id');
        }

        return $query->orderBy('display_order')->get();
    }

    /**
     * Get story by ID.
     */
    public function findById(int $id): ?object
    {
        return DB::table('heritage_curated_story')
            ->where('id', $id)
            ->first();
    }

    /**
     * Create story.
     */
    public function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return (int) DB::table('heritage_curated_story')->insertGetId($data);
    }

    /**
     * Update story.
     */
    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        return DB::table('heritage_curated_story')
            ->where('id', $id)
            ->update($data) > 0;
    }

    /**
     * Delete story.
     */
    public function delete(int $id): bool
    {
        return DB::table('heritage_curated_story')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Reorder stories.
     */
    public function reorder(array $storyOrders): void
    {
        foreach ($storyOrders as $id => $order) {
            DB::table('heritage_curated_story')
                ->where('id', $id)
                ->update([
                    'display_order' => $order,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }
    }

    /**
     * Count stories.
     */
    public function count(?int $institutionId = null, bool $enabledOnly = true): int
    {
        $query = DB::table('heritage_curated_story');

        if ($institutionId !== null) {
            $query->where('institution_id', $institutionId);
        } else {
            $query->whereNull('institution_id');
        }

        if ($enabledOnly) {
            $query->where('is_enabled', 1);
        }

        return $query->count();
    }
}
