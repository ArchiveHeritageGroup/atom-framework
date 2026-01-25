<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Controllers\Api;

use AtomFramework\Heritage\Custodian\AuditService;
use AtomFramework\Heritage\Custodian\BatchOperationsService;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Custodian Controller.
 *
 * Handles custodian interface API requests.
 */
class CustodianController
{
    private AuditService $auditService;
    private BatchOperationsService $batchService;
    private string $culture;

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
        $this->auditService = new AuditService();
        $this->batchService = new BatchOperationsService();
    }

    // ========================================================================
    // Item Management
    // ========================================================================

    /**
     * Get item for editing.
     */
    public function getItem(int $objectId): array
    {
        try {
            $item = DB::table('information_object')
                ->leftJoin('information_object_i18n', function ($join) {
                    $join->on('information_object.id', '=', 'information_object_i18n.id')
                        ->where('information_object_i18n.culture', '=', $this->culture);
                })
                ->where('information_object.id', $objectId)
                ->select([
                    'information_object.*',
                    'information_object_i18n.title',
                    'information_object_i18n.scope_and_content',
                    'information_object_i18n.arrangement',
                    'information_object_i18n.acquisition',
                    'information_object_i18n.appraisal',
                    'information_object_i18n.accruals',
                    'information_object_i18n.access_conditions',
                    'information_object_i18n.reproduction_conditions',
                    'information_object_i18n.physical_characteristics',
                    'information_object_i18n.finding_aids',
                    'information_object_i18n.location_of_originals',
                    'information_object_i18n.location_of_copies',
                    'information_object_i18n.related_units_of_description',
                    'information_object_i18n.rules',
                    'information_object_i18n.sources',
                    'information_object_i18n.revision_history',
                ])
                ->first();

            if (!$item) {
                return [
                    'success' => false,
                    'error' => 'Item not found',
                ];
            }

            return [
                'success' => true,
                'data' => $item,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update item.
     */
    public function updateItem(int $objectId, array $data, ?int $userId = null): array
    {
        try {
            // Get current values for audit
            $current = $this->getItem($objectId);
            if (!$current['success']) {
                return $current;
            }

            $currentData = (array) $current['data'];
            $changes = [];

            // Separate i18n and main fields
            $i18nFields = [
                'title', 'scope_and_content', 'arrangement', 'acquisition', 'appraisal',
                'accruals', 'access_conditions', 'reproduction_conditions', 'physical_characteristics',
                'finding_aids', 'location_of_originals', 'location_of_copies',
                'related_units_of_description', 'rules', 'sources', 'revision_history',
            ];

            $mainFields = ['identifier', 'publication_status_id', 'level_of_description_id'];

            $i18nUpdate = [];
            $mainUpdate = [];

            foreach ($data as $field => $value) {
                if (in_array($field, $i18nFields)) {
                    $i18nUpdate[$field] = $value;
                    if (isset($currentData[$field]) && $currentData[$field] !== $value) {
                        $changes[$field] = ['old' => $currentData[$field], 'new' => $value];
                    }
                } elseif (in_array($field, $mainFields)) {
                    $mainUpdate[$field] = $value;
                    if (isset($currentData[$field]) && $currentData[$field] !== $value) {
                        $changes[$field] = ['old' => $currentData[$field], 'new' => $value];
                    }
                }
            }

            // Update i18n
            if (!empty($i18nUpdate)) {
                DB::table('information_object_i18n')
                    ->where('id', $objectId)
                    ->where('culture', $this->culture)
                    ->update($i18nUpdate);
            }

            // Update main
            if (!empty($mainUpdate)) {
                $mainUpdate['updated_at'] = date('Y-m-d H:i:s');
                DB::table('information_object')
                    ->where('id', $objectId)
                    ->update($mainUpdate);
            }

            // Log changes
            if (!empty($changes)) {
                $this->auditService->logChanges($objectId, $changes, $userId, 'update');
            }

            return [
                'success' => true,
                'data' => ['changes' => count($changes)],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get item history.
     */
    public function getItemHistory(int $objectId, array $params = []): array
    {
        try {
            $result = $this->auditService->getObjectHistory($objectId, $params);

            return [
                'success' => true,
                'data' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ========================================================================
    // Batch Operations
    // ========================================================================

    /**
     * Get batch jobs.
     */
    public function getBatchJobs(array $params = []): array
    {
        try {
            $result = $this->batchService->getJobs($params);

            return [
                'success' => true,
                'data' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get batch job details.
     */
    public function getBatchJob(int $jobId): array
    {
        try {
            $job = $this->batchService->getJob($jobId);

            if (!$job) {
                return [
                    'success' => false,
                    'error' => 'Job not found',
                ];
            }

            return [
                'success' => true,
                'data' => $job,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get batch job items.
     */
    public function getBatchJobItems(int $jobId, array $params = []): array
    {
        try {
            $result = $this->batchService->getJobItems($jobId, $params);

            return [
                'success' => true,
                'data' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create batch job.
     */
    public function createBatchJob(array $data): array
    {
        try {
            if (empty($data['job_type']) || empty($data['user_id'])) {
                return [
                    'success' => false,
                    'error' => 'Job type and user ID are required',
                ];
            }

            $id = $this->batchService->createJob($data);

            return [
                'success' => true,
                'data' => ['id' => $id],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute batch update.
     */
    public function executeBatchUpdate(int $jobId, array $updates, int $userId): array
    {
        try {
            $result = $this->batchService->executeBatchUpdate($jobId, $updates, $userId);

            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel batch job.
     */
    public function cancelBatchJob(int $jobId): array
    {
        try {
            $success = $this->batchService->cancelJob($jobId);

            return [
                'success' => $success,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ========================================================================
    // Dashboard
    // ========================================================================

    /**
     * Get custodian dashboard data.
     */
    public function getDashboard(?int $userId = null): array
    {
        try {
            // Recent activity
            $recentActivity = $this->auditService->getRecentActivity([
                'user_id' => $userId,
                'limit' => 10,
                'days' => 7,
            ]);

            // Pending tasks
            $pendingTasks = [];

            // Batch job stats
            $batchStats = $this->batchService->getStats();

            // Activity summary
            $activitySummary = $this->auditService->getActivitySummary(30);

            return [
                'success' => true,
                'data' => [
                    'recent_activity' => $recentActivity['logs'],
                    'pending_tasks' => $pendingTasks,
                    'batch_stats' => $batchStats,
                    'activity_summary' => $activitySummary,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ========================================================================
    // Audit Trail
    // ========================================================================

    /**
     * Get recent activity.
     */
    public function getRecentActivity(array $params = []): array
    {
        try {
            $result = $this->auditService->getRecentActivity($params);

            return [
                'success' => true,
                'data' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search audit logs.
     */
    public function searchAuditLogs(array $params): array
    {
        try {
            $result = $this->auditService->search($params);

            return [
                'success' => true,
                'data' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
