<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Access;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Access Request Service.
 *
 * Manages access requests from users.
 */
class AccessRequestService
{
    /**
     * Get pending requests for admin.
     */
    public function getPendingRequests(array $params = []): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 25;

        $query = DB::table('heritage_access_request')
            ->join('user', 'heritage_access_request.user_id', '=', 'user.id')
            ->leftJoin('information_object', 'heritage_access_request.object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', 'en');
            })
            ->leftJoin('heritage_purpose', 'heritage_access_request.purpose_id', '=', 'heritage_purpose.id')
            ->select([
                'heritage_access_request.*',
                'user.username',
                'user.email',
                'information_object.slug',
                'information_object_i18n.title as object_title',
                'heritage_purpose.name as purpose_name',
            ])
            ->where('heritage_access_request.status', 'pending');

        $total = $query->count();

        $requests = $query->orderByDesc('heritage_access_request.created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'requests' => $requests,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
        ];
    }

    /**
     * Get user's access requests.
     */
    public function getUserRequests(int $userId, array $params = []): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 25;
        $status = $params['status'] ?? null;

        $query = DB::table('heritage_access_request')
            ->leftJoin('information_object', 'heritage_access_request.object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', 'en');
            })
            ->leftJoin('heritage_purpose', 'heritage_access_request.purpose_id', '=', 'heritage_purpose.id')
            ->select([
                'heritage_access_request.*',
                'information_object.slug',
                'information_object_i18n.title as object_title',
                'heritage_purpose.name as purpose_name',
            ])
            ->where('heritage_access_request.user_id', $userId);

        if ($status) {
            $query->where('heritage_access_request.status', $status);
        }

        $total = $query->count();

        $requests = $query->orderByDesc('heritage_access_request.created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'requests' => $requests,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
        ];
    }

    /**
     * Get request by ID.
     */
    public function getById(int $id): ?object
    {
        return DB::table('heritage_access_request')
            ->leftJoin('user', 'heritage_access_request.user_id', '=', 'user.id')
            ->leftJoin('information_object', 'heritage_access_request.object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', 'en');
            })
            ->leftJoin('heritage_purpose', 'heritage_access_request.purpose_id', '=', 'heritage_purpose.id')
            ->select([
                'heritage_access_request.*',
                'user.username',
                'user.email',
                'information_object.slug',
                'information_object_i18n.title as object_title',
                'heritage_purpose.name as purpose_name',
            ])
            ->where('heritage_access_request.id', $id)
            ->first();
    }

    /**
     * Create access request.
     */
    public function create(array $data): int
    {
        return (int) DB::table('heritage_access_request')->insertGetId([
            'user_id' => $data['user_id'],
            'object_id' => $data['object_id'],
            'purpose_id' => $data['purpose_id'] ?? null,
            'purpose_text' => $data['purpose_text'] ?? null,
            'justification' => $data['justification'] ?? null,
            'research_description' => $data['research_description'] ?? null,
            'institution_affiliation' => $data['institution_affiliation'] ?? null,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Approve access request.
     */
    public function approve(int $id, int $decisionBy, array $options = []): bool
    {
        $validUntil = $options['valid_until'] ?? date('Y-m-d', strtotime('+90 days'));
        $accessGranted = $options['access_granted'] ?? ['view', 'download'];

        return DB::table('heritage_access_request')
            ->where('id', $id)
            ->update([
                'status' => 'approved',
                'decision_by' => $decisionBy,
                'decision_at' => date('Y-m-d H:i:s'),
                'decision_notes' => $options['notes'] ?? null,
                'valid_from' => date('Y-m-d'),
                'valid_until' => $validUntil,
                'access_granted' => json_encode($accessGranted),
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    /**
     * Deny access request.
     */
    public function deny(int $id, int $decisionBy, ?string $reason = null): bool
    {
        return DB::table('heritage_access_request')
            ->where('id', $id)
            ->update([
                'status' => 'denied',
                'decision_by' => $decisionBy,
                'decision_at' => date('Y-m-d H:i:s'),
                'decision_notes' => $reason,
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    /**
     * Withdraw access request.
     */
    public function withdraw(int $id, int $userId): bool
    {
        return DB::table('heritage_access_request')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->update([
                'status' => 'withdrawn',
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    /**
     * Check if user has approved access.
     */
    public function hasApprovedAccess(int $userId, int $objectId): bool
    {
        $today = date('Y-m-d');

        return DB::table('heritage_access_request')
            ->where('user_id', $userId)
            ->where('object_id', $objectId)
            ->where('status', 'approved')
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', $today);
            })
            ->exists();
    }

    /**
     * Get purposes list.
     */
    public function getPurposes(bool $enabledOnly = true): Collection
    {
        $query = DB::table('heritage_purpose');

        if ($enabledOnly) {
            $query->where('is_enabled', 1);
        }

        return $query->orderBy('display_order')->get();
    }

    /**
     * Get request statistics.
     */
    public function getStats(): array
    {
        $pending = DB::table('heritage_access_request')
            ->where('status', 'pending')
            ->count();

        $thisMonth = DB::table('heritage_access_request')
            ->where('created_at', '>=', date('Y-m-01'))
            ->count();

        $approvalRate = 0;
        $decided = DB::table('heritage_access_request')
            ->whereIn('status', ['approved', 'denied'])
            ->count();
        if ($decided > 0) {
            $approved = DB::table('heritage_access_request')
                ->where('status', 'approved')
                ->count();
            $approvalRate = round(($approved / $decided) * 100, 1);
        }

        $byPurpose = DB::table('heritage_access_request')
            ->leftJoin('heritage_purpose', 'heritage_access_request.purpose_id', '=', 'heritage_purpose.id')
            ->select('heritage_purpose.name', DB::raw('COUNT(*) as count'))
            ->groupBy('heritage_purpose.id', 'heritage_purpose.name')
            ->pluck('count', 'name')
            ->toArray();

        return [
            'pending' => $pending,
            'this_month' => $thisMonth,
            'approval_rate' => $approvalRate,
            'by_purpose' => $byPurpose,
        ];
    }
}
