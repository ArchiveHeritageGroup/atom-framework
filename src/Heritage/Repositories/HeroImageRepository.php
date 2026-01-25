<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Repositories;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Hero Image Repository.
 *
 * Provides database access for heritage_hero_slide table.
 * Manages hero background images/slides for landing page.
 */
class HeroImageRepository
{
    private string $table = 'heritage_hero_slide';

    /**
     * Get enabled hero images for institution.
     * Filters by scheduling dates if set.
     */
    public function getEnabledImages(?int $institutionId = null): Collection
    {
        $today = date('Y-m-d');

        $query = DB::table($this->table)
            ->where('is_enabled', 1)
            ->where(function ($q) use ($today) {
                // Check scheduling - show if no dates set or within date range
                $q->where(function ($inner) use ($today) {
                    $inner->whereNull('start_date')
                        ->orWhere('start_date', '<=', $today);
                });
                $q->where(function ($inner) use ($today) {
                    $inner->whereNull('end_date')
                        ->orWhere('end_date', '>=', $today);
                });
            });

        if ($institutionId !== null) {
            $query->where(function ($q) use ($institutionId) {
                $q->where('institution_id', $institutionId)
                    ->orWhereNull('institution_id');
            });
        } else {
            $query->whereNull('institution_id');
        }

        return $query->orderBy('display_order')->get();
    }

    /**
     * Get all images (for admin).
     */
    public function getAllImages(?int $institutionId = null): Collection
    {
        $query = DB::table($this->table);

        if ($institutionId !== null) {
            $query->where(function ($q) use ($institutionId) {
                $q->where('institution_id', $institutionId)
                    ->orWhereNull('institution_id');
            });
        } else {
            $query->whereNull('institution_id');
        }

        return $query->orderBy('display_order')->get();
    }

    /**
     * Get image by ID.
     */
    public function findById(int $id): ?object
    {
        return DB::table($this->table)
            ->where('id', $id)
            ->first();
    }

    /**
     * Create hero image.
     */
    public function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');

        return (int) DB::table($this->table)->insertGetId($data);
    }

    /**
     * Update hero image.
     */
    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        return DB::table($this->table)
            ->where('id', $id)
            ->update($data) > 0;
    }

    /**
     * Delete hero image.
     */
    public function delete(int $id): bool
    {
        return DB::table($this->table)
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Reorder images.
     */
    public function reorder(array $imageOrders): void
    {
        foreach ($imageOrders as $id => $order) {
            DB::table($this->table)
                ->where('id', $id)
                ->update(['display_order' => $order]);
        }
    }

    /**
     * Count images.
     */
    public function count(?int $institutionId = null, bool $enabledOnly = true): int
    {
        $query = DB::table($this->table);

        if ($institutionId !== null) {
            $query->where(function ($q) use ($institutionId) {
                $q->where('institution_id', $institutionId)
                    ->orWhereNull('institution_id');
            });
        } else {
            $query->whereNull('institution_id');
        }

        if ($enabledOnly) {
            $query->where('is_enabled', 1);
        }

        return $query->count();
    }
}
