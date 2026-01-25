<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Admin;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * User Management Service.
 *
 * Admin functions for managing users and trust levels.
 */
class UserManagementService
{
    /**
     * Get paginated user list with trust levels.
     */
    public function getUsers(array $params = []): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 25;
        $search = $params['search'] ?? null;
        $trustLevel = $params['trust_level'] ?? null;
        $status = $params['status'] ?? null;

        $query = DB::table('user')
            ->leftJoin('heritage_user_trust', 'user.id', '=', 'heritage_user_trust.user_id')
            ->leftJoin('heritage_trust_level', 'heritage_user_trust.trust_level_id', '=', 'heritage_trust_level.id')
            ->select([
                'user.id',
                'user.username',
                'user.email',
                'user.active',
                'user.created_at',
                'heritage_trust_level.code as trust_code',
                'heritage_trust_level.name as trust_name',
                'heritage_trust_level.level as trust_level',
                'heritage_user_trust.granted_at',
                'heritage_user_trust.expires_at',
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('user.username', 'LIKE', "%{$search}%")
                    ->orWhere('user.email', 'LIKE', "%{$search}%");
            });
        }

        if ($trustLevel !== null) {
            $query->where('heritage_trust_level.code', $trustLevel);
        }

        if ($status !== null) {
            $query->where('user.active', $status === 'active' ? 1 : 0);
        }

        $total = $query->count();

        $users = $query->orderBy('user.username')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
        ];
    }

    /**
     * Get user by ID with full details.
     */
    public function getUser(int $userId): ?object
    {
        $user = DB::table('user')
            ->leftJoin('heritage_user_trust', 'user.id', '=', 'heritage_user_trust.user_id')
            ->leftJoin('heritage_trust_level', 'heritage_user_trust.trust_level_id', '=', 'heritage_trust_level.id')
            ->select([
                'user.id',
                'user.username',
                'user.email',
                'user.active',
                'user.created_at',
                'heritage_trust_level.id as trust_level_id',
                'heritage_trust_level.code as trust_code',
                'heritage_trust_level.name as trust_name',
                'heritage_trust_level.level as trust_level',
                'heritage_user_trust.granted_at',
                'heritage_user_trust.granted_by',
                'heritage_user_trust.expires_at',
                'heritage_user_trust.notes as trust_notes',
            ])
            ->where('user.id', $userId)
            ->first();

        return $user;
    }

    /**
     * Get all trust levels.
     */
    public function getTrustLevels(): Collection
    {
        return DB::table('heritage_trust_level')
            ->orderBy('level')
            ->get();
    }

    /**
     * Assign trust level to user.
     */
    public function assignTrustLevel(
        int $userId,
        int $trustLevelId,
        ?int $grantedBy = null,
        ?string $expiresAt = null,
        ?string $notes = null,
        ?int $institutionId = null
    ): bool {
        $existing = DB::table('heritage_user_trust')
            ->where('user_id', $userId);

        if ($institutionId !== null) {
            $existing->where('institution_id', $institutionId);
        } else {
            $existing->whereNull('institution_id');
        }

        $existing = $existing->first();

        $data = [
            'trust_level_id' => $trustLevelId,
            'granted_by' => $grantedBy,
            'granted_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
            'notes' => $notes,
            'is_active' => 1,
        ];

        if ($existing) {
            return DB::table('heritage_user_trust')
                ->where('id', $existing->id)
                ->update($data) >= 0;
        }

        $data['user_id'] = $userId;
        $data['institution_id'] = $institutionId;

        return DB::table('heritage_user_trust')->insert($data);
    }

    /**
     * Remove trust level from user.
     */
    public function removeTrustLevel(int $userId, ?int $institutionId = null): bool
    {
        $query = DB::table('heritage_user_trust')
            ->where('user_id', $userId);

        if ($institutionId !== null) {
            $query->where('institution_id', $institutionId);
        } else {
            $query->whereNull('institution_id');
        }

        return $query->delete() > 0;
    }

    /**
     * Get user's trust level.
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
            $query->where('heritage_user_trust.institution_id', $institutionId);
        } else {
            $query->whereNull('heritage_user_trust.institution_id');
        }

        return $query->select([
            'heritage_trust_level.*',
            'heritage_user_trust.granted_at',
            'heritage_user_trust.expires_at',
            'heritage_user_trust.notes',
        ])->first();
    }

    /**
     * Get user statistics.
     */
    public function getUserStats(): array
    {
        $totalUsers = DB::table('user')->count();
        $activeUsers = DB::table('user')->where('active', 1)->count();

        $trustDistribution = DB::table('heritage_user_trust')
            ->join('heritage_trust_level', 'heritage_user_trust.trust_level_id', '=', 'heritage_trust_level.id')
            ->where('heritage_user_trust.is_active', 1)
            ->select('heritage_trust_level.name', DB::raw('COUNT(*) as count'))
            ->groupBy('heritage_trust_level.id', 'heritage_trust_level.name')
            ->get();

        $recentUsers = DB::table('user')
            ->where('created_at', '>=', date('Y-m-d', strtotime('-30 days')))
            ->count();

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'inactive_users' => $totalUsers - $activeUsers,
            'recent_signups' => $recentUsers,
            'trust_distribution' => $trustDistribution,
        ];
    }
}
