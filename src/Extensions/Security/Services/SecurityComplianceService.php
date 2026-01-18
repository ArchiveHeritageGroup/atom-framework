<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\Security\Services;

use AtomExtensions\Helpers\CultureHelper;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

/**
 * Security Compliance Service
 * 
 * Provides NARSSA/POPIA compliance reporting, retention schedule management,
 * and audit-ready export capabilities for South African archival requirements.
 * 
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class SecurityComplianceService
{
    private static ?Logger $logger = null;

    private static function getLogger(): Logger
    {
        if (null === self::$logger) {
            self::$logger = new Logger('security_compliance');
            $logPath = '/var/log/atom/security_compliance.log';
            $logDir = dirname($logPath);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            if (is_writable($logDir)) {
                self::$logger->pushHandler(new RotatingFileHandler($logPath, 30, Logger::DEBUG));
            }
        }
        return self::$logger;
    }

    /**
     * Link access conditions to archival description (ISAD conditions of access/use)
     */
    public static function linkAccessConditions(
        int $objectId,
        int $classificationId,
        ?string $accessConditions = null,
        ?string $reproductionConditions = null,
        int $updatedBy = 0
    ): bool {
        try {
            DB::beginTransaction();

            // Update information_object_i18n with access conditions
            if ($accessConditions !== null || $reproductionConditions !== null) {
                $updateData = [];
                if ($accessConditions !== null) {
                    $updateData['access_conditions'] = $accessConditions;
                }
                if ($reproductionConditions !== null) {
                    $updateData['reproduction_conditions'] = $reproductionConditions;
                }

                DB::table('information_object_i18n')
                    ->where('id', $objectId)
                    ->where('culture', CultureHelper::getCulture())
                    ->update($updateData);
            }

            // Create link record
            DB::table('security_access_condition_link')->updateOrInsert(
                ['object_id' => $objectId],
                [
                    'classification_id' => $classificationId,
                    'access_conditions' => $accessConditions,
                    'reproduction_conditions' => $reproductionConditions,
                    'updated_by' => $updatedBy,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );

            // Log the action
            self::logComplianceAction($objectId, 'access_conditions_linked', $updatedBy, [
                'classification_id' => $classificationId,
            ]);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            self::getLogger()->error('Failed to link access conditions', [
                'object_id' => $objectId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate NARSSA/POPIA compliance report
     * Shows who can access which classification levels and last review dates
     */
    public static function generateComplianceReport(
        ?int $repositoryId = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        try {
            $report = [
                'generated_at' => date('Y-m-d H:i:s'),
                'parameters' => [
                    'repository_id' => $repositoryId,
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                ],
                'summary' => [],
                'classification_access' => [],
                'pending_reviews' => [],
                'declassification_schedule' => [],
                'access_statistics' => [],
            ];

            // Classification access matrix
            $classifications = DB::table('security_classification')
                ->where('active', 1)
                ->orderBy('level')
                ->get();

            foreach ($classifications as $classification) {
                $usersWithAccess = DB::table('user_security_clearance as usc')
                    ->join('user as u', 'usc.user_id', '=', 'u.id')
                    ->join('security_classification as sc', 'usc.classification_id', '=', 'sc.id')
                    ->where('sc.level', '>=', $classification->level)
                    ->whereRaw('(usc.expires_at IS NULL OR usc.expires_at > NOW())')
                    ->select([
                        'u.id',
                        'u.username',
                        'u.email',
                        'usc.granted_at',
                        'usc.expires_at',
                        'sc.name as clearance_level',
                    ])
                    ->get();

                $objectCount = DB::table('object_security_classification')
                    ->where('classification_id', $classification->id)
                    ->where('active', 1)
                    ->count();

                $report['classification_access'][] = [
                    'classification' => [
                        'id' => $classification->id,
                        'code' => $classification->code,
                        'name' => $classification->name,
                        'level' => $classification->level,
                    ],
                    'object_count' => $objectCount,
                    'users_with_access' => $usersWithAccess->toArray(),
                    'user_count' => $usersWithAccess->count(),
                ];
            }

            // Pending reviews (objects needing security review)
            $report['pending_reviews'] = DB::table('object_security_classification as osc')
                ->join('security_classification as sc', 'osc.classification_id', '=', 'sc.id')
                ->join('information_object as io', 'osc.object_id', '=', 'io.id')
                ->leftJoin('information_object_i18n as ioi', function ($join) {
                    $join->on('io.id', '=', 'ioi.id')
                        ->where('ioi.culture', '=', CultureHelper::getCulture());
                })
                ->where('osc.active', 1)
                ->where(function ($query) {
                    $query->whereNull('osc.review_date')
                        ->orWhere('osc.review_date', '<=', date('Y-m-d'));
                })
                ->select([
                    'osc.*',
                    'sc.code as classification_code',
                    'sc.name as classification_name',
                    'ioi.title as object_title',
                    'io.identifier',
                ])
                ->orderBy('osc.review_date')
                ->limit(100)
                ->get()
                ->toArray();

            // Upcoming declassifications
            $report['declassification_schedule'] = DB::table('object_security_classification as osc')
                ->join('security_classification as sc', 'osc.classification_id', '=', 'sc.id')
                ->leftJoin('security_classification as sc2', 'osc.declassify_to_id', '=', 'sc2.id')
                ->join('information_object as io', 'osc.object_id', '=', 'io.id')
                ->leftJoin('information_object_i18n as ioi', function ($join) {
                    $join->on('io.id', '=', 'ioi.id')
                        ->where('ioi.culture', '=', CultureHelper::getCulture());
                })
                ->where('osc.active', 1)
                ->whereNotNull('osc.declassify_date')
                ->where('osc.declassify_date', '>=', date('Y-m-d'))
                ->select([
                    'osc.*',
                    'sc.code as current_classification',
                    'sc.name as current_classification_name',
                    'sc2.code as target_classification',
                    'sc2.name as target_classification_name',
                    'ioi.title as object_title',
                    'io.identifier',
                ])
                ->orderBy('osc.declassify_date')
                ->limit(100)
                ->get()
                ->toArray();

            // Access statistics
            $report['access_statistics'] = self::getAccessStatistics($fromDate, $toDate);

            // Summary
            $report['summary'] = [
                'total_classified_objects' => DB::table('object_security_classification')
                    ->where('active', 1)
                    ->count(),
                'total_cleared_users' => DB::table('user_security_clearance')
                    ->whereRaw('(expires_at IS NULL OR expires_at > NOW())')
                    ->count(),
                'pending_review_count' => count($report['pending_reviews']),
                'upcoming_declassifications' => count($report['declassification_schedule']),
                'total_access_requests' => DB::table('access_request')->count(),
                'pending_access_requests' => DB::table('access_request')
                    ->where('status', 'pending')
                    ->count(),
            ];

            return $report;

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to generate compliance report', [
                'error' => $e->getMessage(),
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get access statistics for a period
     */
    public static function getAccessStatistics(?string $fromDate = null, ?string $toDate = null): array
    {
        $query = DB::table('access_request');

        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('created_at', '<=', $toDate . ' 23:59:59');
        }

        return [
            'total_requests' => $query->count(),
            'by_status' => DB::table('access_request')
                ->when($fromDate, fn($q) => $q->where('created_at', '>=', $fromDate))
                ->when($toDate, fn($q) => $q->where('created_at', '<=', $toDate . ' 23:59:59'))
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'by_type' => DB::table('access_request')
                ->when($fromDate, fn($q) => $q->where('created_at', '>=', $fromDate))
                ->when($toDate, fn($q) => $q->where('created_at', '<=', $toDate . ' 23:59:59'))
                ->selectRaw('request_type, COUNT(*) as count')
                ->groupBy('request_type')
                ->pluck('count', 'request_type')
                ->toArray(),
            'average_response_time' => DB::table('access_request')
                ->whereNotNull('reviewed_at')
                ->when($fromDate, fn($q) => $q->where('created_at', '>=', $fromDate))
                ->when($toDate, fn($q) => $q->where('created_at', '<=', $toDate . ' 23:59:59'))
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, reviewed_at)) as avg_hours')
                ->value('avg_hours'),
        ];
    }

    /**
     * Create or update retention schedule for a classification
     */
    public static function setRetentionSchedule(
        int $classificationId,
        int $retentionYears,
        string $action,
        ?int $declassifyToId = null,
        ?string $legalBasis = null,
        int $updatedBy = 0
    ): bool {
        try {
            DB::table('security_retention_schedule')->updateOrInsert(
                ['classification_id' => $classificationId],
                [
                    'retention_years' => $retentionYears,
                    'action' => $action, // 'declassify', 'review', 'destroy'
                    'declassify_to_id' => $declassifyToId,
                    'legal_basis' => $legalBasis,
                    'updated_by' => $updatedBy,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );

            self::getLogger()->info('Retention schedule updated', [
                'classification_id' => $classificationId,
                'retention_years' => $retentionYears,
                'action' => $action,
            ]);

            return true;

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to set retention schedule', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get retention schedule for a classification
     */
    public static function getRetentionSchedule(int $classificationId): ?object
    {
        return DB::table('security_retention_schedule as srs')
            ->leftJoin('security_classification as sc', 'srs.declassify_to_id', '=', 'sc.id')
            ->where('srs.classification_id', $classificationId)
            ->select([
                'srs.*',
                'sc.code as declassify_to_code',
                'sc.name as declassify_to_name',
            ])
            ->first();
    }

    /**
     * Auto-suggest declassification date based on retention schedule
     */
    public static function suggestDeclassificationDate(int $objectId, ?string $classifiedDate = null): ?array
    {
        try {
            $classification = DB::table('object_security_classification as osc')
                ->join('security_classification as sc', 'osc.classification_id', '=', 'sc.id')
                ->where('osc.object_id', $objectId)
                ->where('osc.active', 1)
                ->select(['osc.*', 'sc.id as classification_id', 'sc.code', 'sc.name'])
                ->first();

            if (!$classification) {
                return null;
            }

            $schedule = self::getRetentionSchedule($classification->classification_id);

            if (!$schedule) {
                return null;
            }

            $baseDate = $classifiedDate ?: $classification->classified_at;
            $suggestedDate = date('Y-m-d', strtotime($baseDate . " +{$schedule->retention_years} years"));

            return [
                'current_classification' => $classification->code,
                'retention_years' => $schedule->retention_years,
                'action' => $schedule->action,
                'suggested_date' => $suggestedDate,
                'declassify_to' => $schedule->declassify_to_code,
                'legal_basis' => $schedule->legal_basis,
            ];

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to suggest declassification date', [
                'object_id' => $objectId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Export access logs for AGSA/internal audit
     */
    public static function exportAccessLogs(
        string $format,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $userId = null,
        bool $includeHash = true
    ): array {
        try {
            $query = DB::table('access_request_log as arl')
                ->leftJoin('access_request as ar', 'arl.request_id', '=', 'ar.id')
                ->leftJoin('user as u', 'arl.actor_id', '=', 'u.id')
                ->leftJoin('user as requester', 'ar.user_id', '=', 'requester.id')
                ->select([
                    'arl.id',
                    'arl.request_id',
                    'arl.action',
                    'arl.details',
                    'arl.ip_address',
                    'arl.created_at',
                    'u.username as actor_username',
                    'u.email as actor_email',
                    'ar.request_type',
                    'ar.status as request_status',
                    'requester.username as requester_username',
                ]);

            if ($fromDate) {
                $query->where('arl.created_at', '>=', $fromDate);
            }
            if ($toDate) {
                $query->where('arl.created_at', '<=', $toDate . ' 23:59:59');
            }
            if ($userId) {
                $query->where('arl.actor_id', $userId);
            }

            $logs = $query->orderBy('arl.created_at', 'desc')->get()->toArray();

            // Generate content hash for integrity verification
            $contentHash = null;
            if ($includeHash) {
                $contentHash = hash('sha256', json_encode($logs) . date('Y-m-d H:i:s'));
            }

            $result = [
                'export_date' => date('Y-m-d H:i:s'),
                'parameters' => [
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'user_id' => $userId,
                ],
                'record_count' => count($logs),
                'format' => $format,
                'logs' => $logs,
                'content_hash' => $contentHash,
            ];

            // Log export action
            self::logComplianceAction(0, 'access_logs_exported', $userId ?? 0, [
                'format' => $format,
                'record_count' => count($logs),
                'content_hash' => $contentHash,
            ]);

            return $result;

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to export access logs', [
                'error' => $e->getMessage(),
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Export clearance logs for audit
     */
    public static function exportClearanceLogs(
        string $format,
        ?string $fromDate = null,
        ?string $toDate = null,
        bool $includeHash = true
    ): array {
        try {
            $query = DB::table('user_security_clearance_log as uscl')
                ->leftJoin('user as u', 'uscl.user_id', '=', 'u.id')
                ->leftJoin('user as cb', 'uscl.changed_by', '=', 'cb.id')
                ->leftJoin('security_classification as sc', 'uscl.classification_id', '=', 'sc.id')
                ->select([
                    'uscl.id',
                    'uscl.user_id',
                    'uscl.classification_id',
                    'uscl.action',
                    'uscl.notes',
                    'uscl.created_at',
                    'u.username',
                    'u.email',
                    'cb.username as changed_by_username',
                    'sc.code as classification_code',
                    'sc.name as classification_name',
                ]);

            if ($fromDate) {
                $query->where('uscl.created_at', '>=', $fromDate);
            }
            if ($toDate) {
                $query->where('uscl.created_at', '<=', $toDate . ' 23:59:59');
            }

            $logs = $query->orderBy('uscl.created_at', 'desc')->get()->toArray();

            $contentHash = null;
            if ($includeHash) {
                $contentHash = hash('sha256', json_encode($logs) . date('Y-m-d H:i:s'));
            }

            return [
                'export_date' => date('Y-m-d H:i:s'),
                'parameters' => [
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                ],
                'record_count' => count($logs),
                'format' => $format,
                'logs' => $logs,
                'content_hash' => $contentHash,
            ];

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to export clearance logs', [
                'error' => $e->getMessage(),
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Log compliance action
     */
    private static function logComplianceAction(
        int $objectId,
        string $action,
        int $userId,
        array $details = []
    ): void {
        try {
            DB::table('security_compliance_log')->insert([
                'object_id' => $objectId,
                'action' => $action,
                'user_id' => $userId,
                'details' => json_encode($details),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            self::getLogger()->warning('Failed to log compliance action', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get compliance statistics
     */
    public static function getComplianceStats(): array
    {
        try {
            $db = \Illuminate\Database\Capsule\Manager::connection();
            
            $totalClassified = $db->table('object_security_classification')->count();
            
            $byLevel = $db->table('object_security_classification as osc')
                ->leftJoin('security_classification as sc', 'osc.classification_id', '=', 'sc.id')
                ->select('sc.level_name', $db->raw('COUNT(*) as count'))
                ->groupBy('sc.level_name')
                ->pluck('count', 'level_name')
                ->toArray();
            
            $pendingReview = $db->table('object_security_classification')
                ->whereNotNull('review_date')
                ->where('review_date', '<=', date('Y-m-d'))
                ->count();
            
            $recentChanges = $db->table('object_security_classification')
                ->where('classified_at', '>=', date('Y-m-d', strtotime('-30 days')))
                ->count();
            
            return [
                'total_classified' => $totalClassified,
                'by_level' => $byLevel,
                'pending_review' => $pendingReview,
                'recent_changes' => $recentChanges
            ];
        } catch (\Exception $e) {
            return [
                'total_classified' => 0,
                'by_level' => [],
                'pending_review' => 0,
                'recent_changes' => 0
            ];
        }
    }

    /**
     * Get items pending security review
     */
    public static function getPendingReviews(int $limit = 20): array
    {
        try {
            $db = \Illuminate\Database\Capsule\Manager::connection();
            
            return $db->table('object_security_classification as osc')
                ->leftJoin('information_object_i18n as io', function($join) {
                    $join->on('osc.object_id', '=', 'io.id')
                         ->where('io.culture', '=', CultureHelper::getCulture());
                })
                ->leftJoin('security_classification as sc', 'osc.classification_id', '=', 'sc.id')
                ->select(
                    'osc.id',
                    'osc.object_id',
                    'io.title',
                    'sc.level_name',
                    'osc.review_date',
                    $db->raw("DATEDIFF(osc.review_date, CURDATE()) as days_until_review")
                )
                ->whereNotNull('osc.review_date')
                ->where('osc.review_date', '<=', date('Y-m-d', strtotime('+30 days')))
                ->orderBy('osc.review_date', 'asc')
                ->limit($limit)
                ->get()
                ->map(fn($r) => (array)$r)
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get retention schedules
     */
    public static function getRetentionSchedules(int $limit = 20): array
    {
        try {
            $db = \Illuminate\Database\Capsule\Manager::connection();
            
            // Check if retention_schedule table exists
            if (!$db->getSchemaBuilder()->hasTable('retention_schedule')) {
                // Return from security_classification if it has retention info
                return $db->table('security_classification')
                    ->select('id', 'level_name', 'retention_years', 'review_period_months')
                    ->whereNotNull('retention_years')
                    ->orderBy('level_name')
                    ->get()
                    ->map(fn($r) => (array)$r)
                    ->toArray();
            }
            
            return $db->table('retention_schedule as rs')
                ->select('rs.*')
                ->orderBy('rs.name')
                ->limit($limit)
                ->get()
                ->map(fn($r) => (array)$r)
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get recent compliance logs
     */
    public static function getRecentComplianceLogs(int $limit = 10): array
    {
        try {
            $db = \Illuminate\Database\Capsule\Manager::connection();
            
            // Check if security_audit_log table exists
            if (!$db->getSchemaBuilder()->hasTable('security_audit_log')) {
                // Fallback to audit_log if available
                if ($db->getSchemaBuilder()->hasTable('audit_log')) {
                    return $db->table('audit_log')
                        ->leftJoin('user', 'audit_log.user_id', '=', 'user.id')
                        ->select('audit_log.*', 'user.username')
                        ->whereIn('audit_log.action', ['classification_change', 'access_granted', 'access_denied', 'declassification'])
                        ->orWhere('audit_log.object_type', 'like', '%security%')
                        ->orderBy('audit_log.created_at', 'desc')
                        ->limit($limit)
                        ->get()
                        ->map(fn($r) => (array)$r)
                        ->toArray();
                }
                return [];
            }
            
            return $db->table('security_audit_log as sal')
                ->leftJoin('user as u', 'sal.user_id', '=', 'u.id')
                ->leftJoin('information_object_i18n as io', function($join) {
                    $join->on('sal.object_id', '=', 'io.id')
                         ->where('io.culture', '=', CultureHelper::getCulture());
                })
                ->select(
                    'sal.id',
                    'sal.action',
                    'sal.object_id',
                    'io.title',
                    'u.username',
                    'sal.created_at'
                )
                ->orderBy('sal.created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn($r) => (array)$r)
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
}