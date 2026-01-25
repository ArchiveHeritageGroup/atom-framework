<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Analytics;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Alert Service.
 *
 * Manages actionable alerts and insights.
 */
class AlertService
{
    /**
     * Alert types.
     */
    public const TYPES = [
        'zero_result_spike' => 'Zero Result Spike',
        'content_trending' => 'Content Trending',
        'low_engagement' => 'Low Engagement',
        'embargo_expiring' => 'Embargo Expiring',
        'access_request_pending' => 'Access Request Pending',
        'popia_critical' => 'POPIA Critical',
        'quality_issue' => 'Quality Issue',
        'batch_completed' => 'Batch Completed',
        'batch_failed' => 'Batch Failed',
    ];

    /**
     * Get active alerts.
     */
    public function getActiveAlerts(?int $institutionId = null, array $params = []): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 25;
        $category = $params['category'] ?? null;
        $severity = $params['severity'] ?? null;

        $query = DB::table('heritage_analytics_alert')
            ->where('is_dismissed', 0)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', date('Y-m-d H:i:s'));
            });

        if ($institutionId) {
            $query->where(function ($q) use ($institutionId) {
                $q->where('institution_id', $institutionId)
                    ->orWhereNull('institution_id');
            });
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($severity) {
            $query->where('severity', $severity);
        }

        $total = $query->count();

        $alerts = $query->orderByRaw("FIELD(severity, 'critical', 'warning', 'info', 'success')")
            ->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'alerts' => $alerts,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
        ];
    }

    /**
     * Get alert counts by severity.
     */
    public function getAlertCounts(?int $institutionId = null): array
    {
        $query = DB::table('heritage_analytics_alert')
            ->select('severity', DB::raw('COUNT(*) as count'))
            ->where('is_dismissed', 0)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', date('Y-m-d H:i:s'));
            });

        if ($institutionId) {
            $query->where(function ($q) use ($institutionId) {
                $q->where('institution_id', $institutionId)
                    ->orWhereNull('institution_id');
            });
        }

        return $query->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();
    }

    /**
     * Create an alert.
     */
    public function create(array $data): int
    {
        return (int) DB::table('heritage_analytics_alert')->insertGetId([
            'institution_id' => $data['institution_id'] ?? null,
            'alert_type' => $data['alert_type'],
            'category' => $data['category'] ?? 'system',
            'severity' => $data['severity'] ?? 'info',
            'title' => $data['title'],
            'message' => $data['message'] ?? null,
            'action_url' => $data['action_url'] ?? null,
            'action_label' => $data['action_label'] ?? null,
            'related_data' => isset($data['related_data']) ? json_encode($data['related_data']) : null,
            'expires_at' => $data['expires_at'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Mark alert as read.
     */
    public function markRead(int $id): bool
    {
        return DB::table('heritage_analytics_alert')
            ->where('id', $id)
            ->update(['is_read' => 1]) >= 0;
    }

    /**
     * Dismiss alert.
     */
    public function dismiss(int $id, ?int $userId = null): bool
    {
        return DB::table('heritage_analytics_alert')
            ->where('id', $id)
            ->update([
                'is_dismissed' => 1,
                'dismissed_by' => $userId,
                'dismissed_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    /**
     * Dismiss all alerts of a type.
     */
    public function dismissByType(string $alertType, ?int $institutionId = null, ?int $userId = null): int
    {
        $query = DB::table('heritage_analytics_alert')
            ->where('alert_type', $alertType)
            ->where('is_dismissed', 0);

        if ($institutionId) {
            $query->where('institution_id', $institutionId);
        }

        return $query->update([
            'is_dismissed' => 1,
            'dismissed_by' => $userId,
            'dismissed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Generate system alerts based on current state.
     */
    public function generateSystemAlerts(?int $institutionId = null): int
    {
        $alertsCreated = 0;

        // Check for pending access requests
        $pendingRequests = DB::table('heritage_access_request')
            ->where('status', 'pending')
            ->where('created_at', '<=', date('Y-m-d H:i:s', strtotime('-24 hours')))
            ->count();

        if ($pendingRequests > 0) {
            $existing = DB::table('heritage_analytics_alert')
                ->where('alert_type', 'access_request_pending')
                ->where('is_dismissed', 0)
                ->where('created_at', '>=', date('Y-m-d'))
                ->exists();

            if (!$existing) {
                $this->create([
                    'institution_id' => $institutionId,
                    'alert_type' => 'access_request_pending',
                    'category' => 'access',
                    'severity' => $pendingRequests > 10 ? 'warning' : 'info',
                    'title' => "{$pendingRequests} access requests pending",
                    'message' => 'There are access requests waiting for review for over 24 hours.',
                    'action_url' => '/heritage/admin/access-requests',
                    'action_label' => 'Review Requests',
                    'related_data' => ['count' => $pendingRequests],
                ]);
                $alertsCreated++;
            }
        }

        // Check for critical POPIA flags
        $criticalPopia = DB::table('heritage_popia_flag')
            ->where('is_resolved', 0)
            ->where('severity', 'critical')
            ->count();

        if ($criticalPopia > 0) {
            $existing = DB::table('heritage_analytics_alert')
                ->where('alert_type', 'popia_critical')
                ->where('is_dismissed', 0)
                ->where('created_at', '>=', date('Y-m-d'))
                ->exists();

            if (!$existing) {
                $this->create([
                    'institution_id' => $institutionId,
                    'alert_type' => 'popia_critical',
                    'category' => 'access',
                    'severity' => 'critical',
                    'title' => "{$criticalPopia} critical privacy flags",
                    'message' => 'Items with critical personal data require immediate attention.',
                    'action_url' => '/heritage/admin/popia',
                    'action_label' => 'Review Flags',
                    'related_data' => ['count' => $criticalPopia],
                ]);
                $alertsCreated++;
            }
        }

        // Check for expiring embargoes
        $expiringEmbargoes = DB::table('heritage_embargo')
            ->whereNotNull('end_date')
            ->where('end_date', '>=', date('Y-m-d'))
            ->where('end_date', '<=', date('Y-m-d', strtotime('+7 days')))
            ->count();

        if ($expiringEmbargoes > 0) {
            $existing = DB::table('heritage_analytics_alert')
                ->where('alert_type', 'embargo_expiring')
                ->where('is_dismissed', 0)
                ->where('created_at', '>=', date('Y-m-d'))
                ->exists();

            if (!$existing) {
                $this->create([
                    'institution_id' => $institutionId,
                    'alert_type' => 'embargo_expiring',
                    'category' => 'access',
                    'severity' => 'info',
                    'title' => "{$expiringEmbargoes} embargoes expiring soon",
                    'message' => 'Some embargoes will expire within the next 7 days.',
                    'action_url' => '/heritage/admin/embargoes',
                    'action_label' => 'Review Embargoes',
                    'related_data' => ['count' => $expiringEmbargoes],
                ]);
                $alertsCreated++;
            }
        }

        return $alertsCreated;
    }

    /**
     * Clean up old alerts.
     */
    public function cleanupOldAlerts(int $days = 90): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return DB::table('heritage_analytics_alert')
            ->where('is_dismissed', 1)
            ->where('dismissed_at', '<', $cutoff)
            ->delete();
    }
}
