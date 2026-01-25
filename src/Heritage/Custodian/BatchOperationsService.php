<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Custodian;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Batch Operations Service.
 *
 * Manages batch update operations on objects.
 */
class BatchOperationsService
{
    private AuditService $auditService;

    public function __construct()
    {
        $this->auditService = new AuditService();
    }

    /**
     * Create a batch job.
     */
    public function createJob(array $data): int
    {
        $jobId = (int) DB::table('heritage_batch_job')->insertGetId([
            'job_type' => $data['job_type'],
            'job_name' => $data['job_name'] ?? null,
            'status' => 'pending',
            'user_id' => $data['user_id'],
            'total_items' => $data['total_items'] ?? 0,
            'processed_items' => 0,
            'successful_items' => 0,
            'failed_items' => 0,
            'skipped_items' => 0,
            'parameters' => isset($data['parameters']) ? json_encode($data['parameters']) : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Add batch items
        if (!empty($data['object_ids'])) {
            $items = [];
            foreach ($data['object_ids'] as $objectId) {
                $items[] = [
                    'job_id' => $jobId,
                    'object_id' => $objectId,
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }
            DB::table('heritage_batch_item')->insert($items);

            DB::table('heritage_batch_job')
                ->where('id', $jobId)
                ->update(['total_items' => count($data['object_ids'])]);
        }

        return $jobId;
    }

    /**
     * Get batch job by ID.
     */
    public function getJob(int $jobId): ?object
    {
        return DB::table('heritage_batch_job')
            ->leftJoin('user', 'heritage_batch_job.user_id', '=', 'user.id')
            ->select([
                'heritage_batch_job.*',
                'user.username',
            ])
            ->where('heritage_batch_job.id', $jobId)
            ->first();
    }

    /**
     * Get jobs list.
     */
    public function getJobs(array $params = []): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 25;
        $status = $params['status'] ?? null;
        $userId = $params['user_id'] ?? null;

        $query = DB::table('heritage_batch_job')
            ->leftJoin('user', 'heritage_batch_job.user_id', '=', 'user.id')
            ->select([
                'heritage_batch_job.*',
                'user.username',
            ]);

        if ($status) {
            $query->where('heritage_batch_job.status', $status);
        }

        if ($userId) {
            $query->where('heritage_batch_job.user_id', $userId);
        }

        $total = $query->count();

        $jobs = $query->orderByDesc('heritage_batch_job.created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'jobs' => $jobs,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
        ];
    }

    /**
     * Start processing a job.
     */
    public function startJob(int $jobId): bool
    {
        return DB::table('heritage_batch_job')
            ->where('id', $jobId)
            ->where('status', 'pending')
            ->update([
                'status' => 'processing',
                'started_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    /**
     * Process batch item.
     */
    public function processItem(int $jobId, int $objectId, callable $processor): array
    {
        $item = DB::table('heritage_batch_item')
            ->where('job_id', $jobId)
            ->where('object_id', $objectId)
            ->first();

        if (!$item) {
            return ['success' => false, 'error' => 'Item not found'];
        }

        DB::table('heritage_batch_item')
            ->where('id', $item->id)
            ->update(['status' => 'processing']);

        try {
            $result = $processor($objectId);

            DB::table('heritage_batch_item')
                ->where('id', $item->id)
                ->update([
                    'status' => 'success',
                    'new_values' => isset($result['changes']) ? json_encode($result['changes']) : null,
                    'processed_at' => date('Y-m-d H:i:s'),
                ]);

            $this->incrementJobCounter($jobId, 'successful_items');

            return ['success' => true, 'result' => $result];
        } catch (\Exception $e) {
            DB::table('heritage_batch_item')
                ->where('id', $item->id)
                ->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'processed_at' => date('Y-m-d H:i:s'),
                ]);

            $this->incrementJobCounter($jobId, 'failed_items');

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Increment job counter.
     */
    private function incrementJobCounter(int $jobId, string $field): void
    {
        DB::table('heritage_batch_job')
            ->where('id', $jobId)
            ->update([
                $field => DB::raw("{$field} + 1"),
                'processed_items' => DB::raw('processed_items + 1'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Complete job.
     */
    public function completeJob(int $jobId, ?array $results = null): bool
    {
        return DB::table('heritage_batch_job')
            ->where('id', $jobId)
            ->update([
                'status' => 'completed',
                'results' => $results ? json_encode($results) : null,
                'completed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    /**
     * Fail job.
     */
    public function failJob(int $jobId, string $errorMessage): bool
    {
        return DB::table('heritage_batch_job')
            ->where('id', $jobId)
            ->update([
                'status' => 'failed',
                'error_message' => $errorMessage,
                'completed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    /**
     * Cancel job.
     */
    public function cancelJob(int $jobId): bool
    {
        return DB::table('heritage_batch_job')
            ->where('id', $jobId)
            ->whereIn('status', ['pending', 'queued', 'paused'])
            ->update([
                'status' => 'cancelled',
                'completed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    /**
     * Get job items.
     */
    public function getJobItems(int $jobId, array $params = []): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 100;
        $status = $params['status'] ?? null;

        $query = DB::table('heritage_batch_item')
            ->leftJoin('information_object', 'heritage_batch_item.object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', 'en');
            })
            ->select([
                'heritage_batch_item.*',
                'information_object.slug',
                'information_object_i18n.title as object_title',
            ])
            ->where('heritage_batch_item.job_id', $jobId);

        if ($status) {
            $query->where('heritage_batch_item.status', $status);
        }

        $total = $query->count();

        $items = $query->orderBy('heritage_batch_item.id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
        ];
    }

    /**
     * Execute batch update.
     */
    public function executeBatchUpdate(int $jobId, array $updates, int $userId): array
    {
        $job = $this->getJob($jobId);
        if (!$job) {
            return ['success' => false, 'error' => 'Job not found'];
        }

        $this->startJob($jobId);

        $items = DB::table('heritage_batch_item')
            ->where('job_id', $jobId)
            ->where('status', 'pending')
            ->get();

        foreach ($items as $item) {
            try {
                // Get current values
                $object = DB::table('information_object_i18n')
                    ->where('id', $item->object_id)
                    ->where('culture', 'en')
                    ->first();

                $oldValues = [];
                $newValues = [];

                foreach ($updates as $field => $value) {
                    if (isset($object->$field)) {
                        $oldValues[$field] = $object->$field;
                    }
                    $newValues[$field] = $value;
                }

                // Apply update
                DB::table('information_object_i18n')
                    ->where('id', $item->object_id)
                    ->where('culture', 'en')
                    ->update($updates);

                DB::table('heritage_batch_item')
                    ->where('id', $item->id)
                    ->update([
                        'status' => 'success',
                        'old_values' => json_encode($oldValues),
                        'new_values' => json_encode($newValues),
                        'processed_at' => date('Y-m-d H:i:s'),
                    ]);

                $this->incrementJobCounter($jobId, 'successful_items');

                // Log audit
                $this->auditService->log([
                    'user_id' => $userId,
                    'object_id' => $item->object_id,
                    'action' => 'batch_update',
                    'action_category' => 'batch',
                    'changes' => ['old' => $oldValues, 'new' => $newValues],
                    'metadata' => ['job_id' => $jobId],
                ]);
            } catch (\Exception $e) {
                DB::table('heritage_batch_item')
                    ->where('id', $item->id)
                    ->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'processed_at' => date('Y-m-d H:i:s'),
                    ]);

                $this->incrementJobCounter($jobId, 'failed_items');
            }
        }

        $this->completeJob($jobId);

        return ['success' => true, 'job_id' => $jobId];
    }

    /**
     * Get job statistics.
     */
    public function getStats(): array
    {
        $today = date('Y-m-d');
        $thisMonth = date('Y-m-01');

        $runningJobs = DB::table('heritage_batch_job')
            ->where('status', 'processing')
            ->count();

        $completedToday = DB::table('heritage_batch_job')
            ->where('status', 'completed')
            ->where('completed_at', '>=', $today)
            ->count();

        $itemsProcessedThisMonth = DB::table('heritage_batch_job')
            ->where('completed_at', '>=', $thisMonth)
            ->sum('processed_items');

        return [
            'running' => $runningJobs,
            'completed_today' => $completedToday,
            'items_this_month' => $itemsProcessedThisMonth,
        ];
    }
}
