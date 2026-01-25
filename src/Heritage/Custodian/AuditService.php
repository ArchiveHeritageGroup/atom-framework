<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Custodian;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Audit Service.
 *
 * Change tracking and audit trail for objects.
 */
class AuditService
{
    /**
     * Log an action.
     */
    public function log(array $data): int
    {
        $insertData = [
            'user_id' => $data['user_id'] ?? null,
            'username' => $data['username'] ?? null,
            'object_id' => $data['object_id'] ?? null,
            'object_type' => $data['object_type'] ?? 'information_object',
            'object_identifier' => $data['object_identifier'] ?? null,
            'action' => $data['action'],
            'action_category' => $data['action_category'] ?? 'update',
            'field_name' => $data['field_name'] ?? null,
            'old_value' => $data['old_value'] ?? null,
            'new_value' => $data['new_value'] ?? null,
            'changes_json' => isset($data['changes']) ? json_encode($data['changes']) : null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return (int) DB::table('heritage_audit_log')->insertGetId($insertData);
    }

    /**
     * Log multiple field changes.
     */
    public function logChanges(
        int $objectId,
        array $changes,
        ?int $userId = null,
        ?string $action = 'update',
        ?string $ipAddress = null
    ): void {
        foreach ($changes as $field => $change) {
            $this->log([
                'user_id' => $userId,
                'object_id' => $objectId,
                'action' => $action,
                'action_category' => 'update',
                'field_name' => $field,
                'old_value' => is_array($change['old']) ? json_encode($change['old']) : $change['old'],
                'new_value' => is_array($change['new']) ? json_encode($change['new']) : $change['new'],
                'ip_address' => $ipAddress,
            ]);
        }
    }

    /**
     * Get object history.
     */
    public function getObjectHistory(int $objectId, array $params = []): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 50;
        $action = $params['action'] ?? null;
        $category = $params['category'] ?? null;

        $query = DB::table('heritage_audit_log')
            ->leftJoin('user', 'heritage_audit_log.user_id', '=', 'user.id')
            ->select([
                'heritage_audit_log.*',
                'user.username as user_name',
            ])
            ->where('heritage_audit_log.object_id', $objectId);

        if ($action) {
            $query->where('heritage_audit_log.action', $action);
        }

        if ($category) {
            $query->where('heritage_audit_log.action_category', $category);
        }

        $total = $query->count();

        $logs = $query->orderByDesc('heritage_audit_log.created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
        ];
    }

    /**
     * Get recent activity.
     */
    public function getRecentActivity(array $params = []): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 50;
        $userId = $params['user_id'] ?? null;
        $category = $params['category'] ?? null;
        $days = $params['days'] ?? 7;

        $query = DB::table('heritage_audit_log')
            ->leftJoin('user', 'heritage_audit_log.user_id', '=', 'user.id')
            ->leftJoin('information_object', 'heritage_audit_log.object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', 'en');
            })
            ->select([
                'heritage_audit_log.*',
                'user.username as user_name',
                'information_object.slug',
                'information_object_i18n.title as object_title',
            ])
            ->where('heritage_audit_log.created_at', '>=', date('Y-m-d', strtotime("-{$days} days")));

        if ($userId) {
            $query->where('heritage_audit_log.user_id', $userId);
        }

        if ($category) {
            $query->where('heritage_audit_log.action_category', $category);
        }

        $total = $query->count();

        $logs = $query->orderByDesc('heritage_audit_log.created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
        ];
    }

    /**
     * Get activity by user.
     */
    public function getUserActivity(int $userId, array $params = []): array
    {
        $params['user_id'] = $userId;

        return $this->getRecentActivity($params);
    }

    /**
     * Get activity summary.
     */
    public function getActivitySummary(int $days = 30): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $byCategory = DB::table('heritage_audit_log')
            ->where('created_at', '>=', $startDate)
            ->select('action_category', DB::raw('COUNT(*) as count'))
            ->groupBy('action_category')
            ->pluck('count', 'action_category')
            ->toArray();

        $byDay = DB::table('heritage_audit_log')
            ->where('created_at', '>=', $startDate)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $topUsers = DB::table('heritage_audit_log')
            ->leftJoin('user', 'heritage_audit_log.user_id', '=', 'user.id')
            ->where('heritage_audit_log.created_at', '>=', $startDate)
            ->whereNotNull('heritage_audit_log.user_id')
            ->select('user.username', DB::raw('COUNT(*) as count'))
            ->groupBy('heritage_audit_log.user_id', 'user.username')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return [
            'by_category' => $byCategory,
            'by_day' => $byDay,
            'top_users' => $topUsers,
            'total' => array_sum($byCategory),
        ];
    }

    /**
     * Search audit logs.
     */
    public function search(array $params): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 50;

        $query = DB::table('heritage_audit_log')
            ->leftJoin('user', 'heritage_audit_log.user_id', '=', 'user.id')
            ->leftJoin('information_object', 'heritage_audit_log.object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', 'en');
            })
            ->select([
                'heritage_audit_log.*',
                'user.username as user_name',
                'information_object.slug',
                'information_object_i18n.title as object_title',
            ]);

        if (!empty($params['user_id'])) {
            $query->where('heritage_audit_log.user_id', $params['user_id']);
        }

        if (!empty($params['object_id'])) {
            $query->where('heritage_audit_log.object_id', $params['object_id']);
        }

        if (!empty($params['action'])) {
            $query->where('heritage_audit_log.action', 'LIKE', "%{$params['action']}%");
        }

        if (!empty($params['category'])) {
            $query->where('heritage_audit_log.action_category', $params['category']);
        }

        if (!empty($params['date_from'])) {
            $query->where('heritage_audit_log.created_at', '>=', $params['date_from']);
        }

        if (!empty($params['date_to'])) {
            $query->where('heritage_audit_log.created_at', '<=', $params['date_to'] . ' 23:59:59');
        }

        $total = $query->count();

        $logs = $query->orderByDesc('heritage_audit_log.created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
        ];
    }
}
