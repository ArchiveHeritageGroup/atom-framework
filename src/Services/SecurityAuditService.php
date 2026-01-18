<?php
declare(strict_types=1);

namespace AtomFramework\Services;

use AtomExtensions\Helpers\CultureHelper;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Service for Security Audit Reports
 */
class SecurityAuditService
{
    /**
     * Log an audit event
     */
    public function logEvent(array $data): int
    {
        return DB::table('security_audit_log')->insertGetId([
            'object_id' => $data['object_id'] ?? null,
            'object_type' => $data['object_type'] ?? 'information_object',
            'user_id' => $data['user_id'] ?? null,
            'user_name' => $data['user_name'] ?? null,
            'action' => $data['action'],
            'action_category' => $data['action_category'] ?? 'access',
            'details' => isset($data['details']) ? json_encode($data['details']) : null,
            'ip_address' => $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get audit logs with filters
     */
    public function getAuditLogs(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $query = DB::table('security_audit_log as sal')
            ->leftJoin('user as u', 'sal.user_id', '=', 'u.id')
            ->leftJoin('information_object as io', function($join) {
                $join->on('sal.object_id', '=', 'io.id')
                    ->where('sal.object_type', '=', 'information_object');
            })
            ->leftJoin('information_object_i18n as ioi', function($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->select(
                'sal.*',
                'ioi.title as object_title',
                'slug.slug as object_slug'
            );

        // Apply filters
        if (!empty($filters['user_id'])) {
            $query->where('sal.user_id', $filters['user_id']);
        }
        if (!empty($filters['object_id'])) {
            $query->where('sal.object_id', $filters['object_id']);
        }
        if (!empty($filters['action'])) {
            $query->where('sal.action', $filters['action']);
        }
        if (!empty($filters['action_category'])) {
            $query->where('sal.action_category', $filters['action_category']);
        }
        if (!empty($filters['date_from'])) {
            $query->where('sal.created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('sal.created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }
        if (!empty($filters['ip_address'])) {
            $query->where('sal.ip_address', 'LIKE', '%' . $filters['ip_address'] . '%');
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('sal.user_name', 'LIKE', "%{$search}%")
                  ->orWhere('ioi.title', 'LIKE', "%{$search}%")
                  ->orWhere('sal.action', 'LIKE', "%{$search}%");
            });
        }

        $total = $query->count();
        
        $logs = $query->orderBy('sal.created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->all();

        return [
            'logs' => $logs,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Get combined audit logs from all sources
     */
    public function getCombinedAuditLogs(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        // Get from security_audit_log
        $query1 = DB::table('security_audit_log as sal')
            ->leftJoin('information_object_i18n as ioi', function($join) {
                $join->on('sal.object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('slug', 'sal.object_id', '=', 'slug.object_id')
            ->select(
                'sal.id',
                'sal.object_id',
                DB::raw("'security' as source"),
                'sal.user_id',
                'sal.user_name',
                'sal.action',
                'sal.action_category',
                'sal.details',
                'sal.ip_address',
                'sal.created_at',
                'ioi.title as object_title',
                'slug.slug as object_slug'
            );

        // Get from spectrum_audit_log
        $query2 = DB::table('spectrum_audit_log as spal')
            ->leftJoin('information_object_i18n as ioi', function($join) {
                $join->on('spal.object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('slug', 'spal.object_id', '=', 'slug.object_id')
            ->select(
                'spal.id',
                'spal.object_id',
                DB::raw("'spectrum' as source"),
                'spal.user_id',
                'spal.user_name',
                'spal.action',
                'spal.procedure_type as action_category',
                'spal.new_values as details',
                'spal.ip_address',
                'spal.action_date as created_at',
                'ioi.title as object_title',
                'slug.slug as object_slug'
            );

        // Get from access_log (view events)
        $query3 = DB::table('access_log as al')
            ->leftJoin('information_object_i18n as ioi', function($join) {
                $join->on('al.object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('slug', 'al.object_id', '=', 'slug.object_id')
            ->select(
                'al.id',
                'al.object_id',
                DB::raw("'access' as source"),
                DB::raw('NULL as user_id'),
                DB::raw("'anonymous' as user_name"),
                DB::raw("'view' as action"),
                DB::raw("'access' as action_category"),
                DB::raw('NULL as details'),
                DB::raw('NULL as ip_address'),
                'al.access_date as created_at',
                'ioi.title as object_title',
                'slug.slug as object_slug'
            );

        // Union all queries
        $combined = $query1->unionAll($query2)->unionAll($query3);

        // Apply filters
        $wrapper = DB::table(DB::raw("({$combined->toSql()}) as combined"))
            ->mergeBindings($combined);

        if (!empty($filters['user_name'])) {
            $wrapper->where('user_name', 'LIKE', '%' . $filters['user_name'] . '%');
        }
        if (!empty($filters['action'])) {
            $wrapper->where('action', $filters['action']);
        }
        if (!empty($filters['action_category'])) {
            $wrapper->where('action_category', $filters['action_category']);
        }
        if (!empty($filters['date_from'])) {
            $wrapper->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $wrapper->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }
        if (!empty($filters['source'])) {
            $wrapper->where('source', $filters['source']);
        }

        $total = $wrapper->count();

        $logs = $wrapper->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->all();

        return [
            'logs' => $logs,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Get audit statistics
     */
    public function getStatistics(string $period = '7 days'): array
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$period}"));

        // Total events by category
        $byCategory = DB::table('security_audit_log')
            ->where('created_at', '>=', $since)
            ->select('action_category', DB::raw('COUNT(*) as count'))
            ->groupBy('action_category')
            ->pluck('count', 'action_category')
            ->all();

        // Events by user
        $byUser = DB::table('security_audit_log')
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_name')
            ->select('user_name', DB::raw('COUNT(*) as count'))
            ->groupBy('user_name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->all();

        // Events by action
        $byAction = DB::table('security_audit_log')
            ->where('created_at', '>=', $since)
            ->select('action', DB::raw('COUNT(*) as count'))
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->all();

        // Events by day
        $byDay = DB::table('security_audit_log')
            ->where('created_at', '>=', $since)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->all();

        // Most accessed objects
        $topObjects = DB::table('access_log as al')
            ->leftJoin('information_object_i18n as ioi', function($join) {
                $join->on('al.object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('slug', 'al.object_id', '=', 'slug.object_id')
            ->where('al.access_date', '>=', $since)
            ->select('al.object_id', 'ioi.title', 'slug.slug', DB::raw('COUNT(*) as count'))
            ->groupBy('al.object_id', 'ioi.title', 'slug.slug')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->all();

        // Security events (clearance changes)
        $securityEvents = DB::table('spectrum_audit_log')
            ->where('action_date', '>=', $since)
            ->where('procedure_type', 'LIKE', '%clearance%')
            ->count();

        return [
            'period' => $period,
            'since' => $since,
            'by_category' => $byCategory,
            'by_user' => $byUser,
            'by_action' => $byAction,
            'by_day' => $byDay,
            'top_objects' => $topObjects,
            'security_events' => $securityEvents,
            'total_events' => array_sum($byCategory),
        ];
    }

    /**
     * Get users list for filter dropdown
     */
    public function getUsers(): array
    {
        return DB::table('user')
            ->join('user_i18n', 'user.id', '=', 'user_i18n.id')
            ->select('user.id', 'user.username', 'user.email')
            ->orderBy('user.username')
            ->get()
            ->all();
    }

    /**
     * Get distinct actions for filter
     */
    public function getActions(): array
    {
        $actions1 = DB::table('security_audit_log')
            ->distinct()
            ->pluck('action')
            ->all();

        $actions2 = DB::table('spectrum_audit_log')
            ->distinct()
            ->pluck('action')
            ->all();

        return array_unique(array_merge($actions1, $actions2));
    }
}
