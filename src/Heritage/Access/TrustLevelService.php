<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Access;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Trust Level Service.
 *
 * Manages user trust levels for access control.
 */
class TrustLevelService
{
    /**
     * Get all trust levels.
     */
    public function getAllLevels(): Collection
    {
        return DB::table('heritage_trust_level')
            ->orderBy('level')
            ->get();
    }

    /**
     * Get trust level by code.
     */
    public function getByCode(string $code): ?object
    {
        return DB::table('heritage_trust_level')
            ->where('code', $code)
            ->first();
    }

    /**
     * Get trust level by ID.
     */
    public function getById(int $id): ?object
    {
        return DB::table('heritage_trust_level')
            ->where('id', $id)
            ->first();
    }

    /**
     * Get user's effective trust level.
     */
    public function getUserTrustLevel(int $userId, ?int $institutionId = null): ?object
    {
        $query = DB::table('heritage_user_trust')
            ->join('heritage_trust_level', 'heritage_user_trust.trust_level_id', '=', 'heritage_trust_level.id')
            ->where('heritage_user_trust.user_id', $userId)
            ->where('heritage_user_trust.is_active', 1)
            ->where(function ($q) {
                $q->whereNull('heritage_user_trust.expires_at')
                    ->orWhere('heritage_user_trust.expires_at', '>', date('Y-m-d H:i:s'));
            });

        if ($institutionId !== null) {
            $query->where(function ($q) use ($institutionId) {
                $q->where('heritage_user_trust.institution_id', $institutionId)
                    ->orWhereNull('heritage_user_trust.institution_id');
            })->orderByRaw('heritage_user_trust.institution_id IS NULL ASC');
        } else {
            $query->whereNull('heritage_user_trust.institution_id');
        }

        return $query->select('heritage_trust_level.*')
            ->first();
    }

    /**
     * Get user's trust level numeric value.
     */
    public function getUserLevel(int $userId, ?int $institutionId = null): int
    {
        $trustLevel = $this->getUserTrustLevel($userId, $institutionId);

        return $trustLevel ? (int) $trustLevel->level : 0;
    }

    /**
     * Check if user has minimum trust level.
     */
    public function hasMinLevel(int $userId, int $minLevel, ?int $institutionId = null): bool
    {
        return $this->getUserLevel($userId, $institutionId) >= $minLevel;
    }

    /**
     * Check if user can view restricted content.
     */
    public function canViewRestricted(int $userId, ?int $institutionId = null): bool
    {
        $trustLevel = $this->getUserTrustLevel($userId, $institutionId);

        return $trustLevel ? (bool) $trustLevel->can_view_restricted : false;
    }

    /**
     * Check if user can download.
     */
    public function canDownload(int $userId, ?int $institutionId = null): bool
    {
        $trustLevel = $this->getUserTrustLevel($userId, $institutionId);

        return $trustLevel ? (bool) $trustLevel->can_download : false;
    }

    /**
     * Create a new trust level.
     */
    public function create(array $data): int
    {
        return (int) DB::table('heritage_trust_level')->insertGetId([
            'code' => $data['code'],
            'name' => $data['name'],
            'level' => $data['level'] ?? 0,
            'can_view_restricted' => $data['can_view_restricted'] ?? 0,
            'can_download' => $data['can_download'] ?? 0,
            'can_bulk_download' => $data['can_bulk_download'] ?? 0,
            'is_system' => 0,
            'description' => $data['description'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Update trust level.
     */
    public function update(int $id, array $data): bool
    {
        $level = $this->getById($id);
        if (!$level || $level->is_system) {
            return false; // Cannot modify system levels
        }

        $allowedFields = ['name', 'level', 'can_view_restricted', 'can_download', 'can_bulk_download', 'description'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        return DB::table('heritage_trust_level')
            ->where('id', $id)
            ->update($updateData) >= 0;
    }

    /**
     * Delete trust level.
     */
    public function delete(int $id): bool
    {
        $level = $this->getById($id);
        if (!$level || $level->is_system) {
            return false;
        }

        return DB::table('heritage_trust_level')
            ->where('id', $id)
            ->delete() > 0;
    }
}
