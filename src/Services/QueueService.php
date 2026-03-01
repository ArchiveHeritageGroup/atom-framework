<?php

namespace AtomFramework\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Durable Queue Service for AtoM Heratio.
 *
 * Provides dispatch, reserve, batch, chain, progress, and retry operations
 * for background job processing. Generalizes the pattern from ahgAIPlugin's
 * JobQueueService into a framework-level service available to all plugins.
 *
 * Uses MySQL-backed queues with SELECT ... FOR UPDATE SKIP LOCKED for
 * reliable worker reservation without external broker dependencies.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class QueueService
{
    // Job statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    // Batch statuses
    public const BATCH_PENDING = 'pending';
    public const BATCH_RUNNING = 'running';
    public const BATCH_PAUSED = 'paused';
    public const BATCH_COMPLETED = 'completed';
    public const BATCH_FAILED = 'failed';
    public const BATCH_CANCELLED = 'cancelled';

    // Backoff strategies
    public const BACKOFF_NONE = 'none';
    public const BACKOFF_LINEAR = 'linear';
    public const BACKOFF_EXPONENTIAL = 'exponential';

    // Status badge mapping (Bootstrap 5)
    public const STATUS_BADGES = [
        'pending'   => 'secondary',
        'reserved'  => 'info',
        'running'   => 'primary',
        'completed' => 'success',
        'failed'    => 'danger',
        'cancelled' => 'warning',
        'paused'    => 'warning',
    ];

    // =========================================================================
    // Dispatch
    // =========================================================================

    /**
     * Dispatch a job to the queue.
     *
     * @param string  $jobType        Handler identifier (e.g. 'ingest:commit')
     * @param array   $payload        Job-specific arguments
     * @param string  $queue          Queue name (default, ai, ingest, export, sync)
     * @param int     $priority       1=highest, 9=lowest
     * @param int     $delaySeconds   Delay before first attempt
     * @param int     $maxAttempts    Maximum retry attempts
     * @param int|null $userId        Dispatching user
     * @param string|null $rateLimitGroup Rate limiter group
     *
     * @return int Job ID
     */
    public function dispatch(
        string $jobType,
        array $payload = [],
        string $queue = 'default',
        int $priority = 5,
        int $delaySeconds = 0,
        int $maxAttempts = 3,
        ?int $userId = null,
        ?string $rateLimitGroup = null
    ): int {
        $now = date('Y-m-d H:i:s');
        $availableAt = $delaySeconds > 0
            ? date('Y-m-d H:i:s', time() + $delaySeconds)
            : $now;

        $jobId = DB::table('ahg_queue_job')->insertGetId([
            'queue' => $queue,
            'job_type' => $jobType,
            'payload' => json_encode($payload),
            'status' => self::STATUS_PENDING,
            'priority' => $priority,
            'attempt_count' => 0,
            'max_attempts' => $maxAttempts,
            'delay_seconds' => $delaySeconds,
            'backoff_strategy' => self::BACKOFF_EXPONENTIAL,
            'available_at' => $availableAt,
            'user_id' => $userId,
            'rate_limit_group' => $rateLimitGroup,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->logEvent($jobId, null, 'dispatched', "Job dispatched to '{$queue}' queue", [
            'job_type' => $jobType,
            'priority' => $priority,
        ]);

        return $jobId;
    }

    /**
     * Dispatch and execute a job synchronously (no queue, immediate).
     *
     * @return array Result data from the handler
     */
    public function dispatchSync(string $jobType, array $payload = []): array
    {
        $handler = QueueJobRegistry::resolve($jobType);
        if (!$handler) {
            throw new \RuntimeException("No handler registered for job type: {$jobType}");
        }

        // Create a temporary job record for tracking
        $jobId = $this->dispatch($jobType, $payload, 'sync', 1, 0, 1);

        $this->markRunning($jobId, 'sync:' . getmypid());

        try {
            $context = new QueueJobContext($jobId, null, $this);
            $startTime = microtime(true);
            $result = $handler->handle($payload, $context);
            $processingTime = (int) ((microtime(true) - $startTime) * 1000);

            $this->markCompleted($jobId, $result, $processingTime);

            return $result;
        } catch (\Throwable $e) {
            $this->markFailed($jobId, $e->getMessage(), $e->getCode(), $e->getTraceAsString());

            throw $e;
        }
    }

    // =========================================================================
    // Chain
    // =========================================================================

    /**
     * Dispatch a chain of jobs (sequential execution).
     *
     * Each job in the chain runs only after the previous one completes.
     * If a job fails after max retries, subsequent chain jobs are cancelled.
     *
     * @param array   $jobs   Array of ['job_type' => string, 'payload' => array, ...]
     * @param string  $queue  Queue name
     * @param int|null $userId
     *
     * @return int Chain ID (matches first job's chain_id)
     */
    public function dispatchChain(array $jobs, string $queue = 'default', ?int $userId = null): int
    {
        if (empty($jobs)) {
            throw new \InvalidArgumentException('Chain must contain at least one job');
        }

        $now = date('Y-m-d H:i:s');
        // Use a timestamp-based chain ID
        $chainId = (int) (microtime(true) * 1000);

        foreach ($jobs as $order => $jobDef) {
            $jobType = $jobDef['job_type'] ?? '';
            $payload = $jobDef['payload'] ?? [];
            $priority = $jobDef['priority'] ?? 5;
            $maxAttempts = $jobDef['max_attempts'] ?? 3;

            // Only the first job is immediately available
            $availableAt = $order === 0 ? $now : '9999-12-31 23:59:59';

            DB::table('ahg_queue_job')->insert([
                'queue' => $queue,
                'job_type' => $jobType,
                'payload' => json_encode($payload),
                'status' => self::STATUS_PENDING,
                'priority' => $priority,
                'chain_id' => $chainId,
                'chain_order' => $order,
                'attempt_count' => 0,
                'max_attempts' => $maxAttempts,
                'delay_seconds' => 0,
                'backoff_strategy' => self::BACKOFF_EXPONENTIAL,
                'available_at' => $availableAt,
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->logEvent(null, null, 'chain_dispatched', "Chain dispatched with " . count($jobs) . " jobs", [
            'chain_id' => $chainId,
            'queue' => $queue,
        ]);

        return $chainId;
    }

    // =========================================================================
    // Batch
    // =========================================================================

    /**
     * Create a batch.
     *
     * @return int Batch ID
     */
    public function createBatch(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $batchId = DB::table('ahg_queue_batch')->insertGetId([
            'name' => $data['name'] ?? 'Unnamed batch',
            'queue' => $data['queue'] ?? 'default',
            'status' => self::BATCH_PENDING,
            'total_jobs' => 0,
            'completed_jobs' => 0,
            'failed_jobs' => 0,
            'progress_percent' => 0,
            'max_concurrent' => $data['max_concurrent'] ?? 5,
            'delay_between_ms' => $data['delay_between_ms'] ?? 0,
            'max_retries' => $data['max_retries'] ?? 3,
            'options' => isset($data['options']) ? json_encode($data['options']) : null,
            'on_complete_callback' => $data['on_complete_callback'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->logEvent(null, $batchId, 'batch_created', 'Batch created: ' . ($data['name'] ?? 'Unnamed'));

        return $batchId;
    }

    /**
     * Add jobs to a batch.
     *
     * @param int   $batchId
     * @param array $jobs Array of ['job_type' => string, 'payload' => array, ...]
     *
     * @return int Number of jobs added
     */
    public function addToBatch(int $batchId, array $jobs): int
    {
        $batch = $this->getBatch($batchId);
        if (!$batch) {
            throw new \RuntimeException("Batch not found: {$batchId}");
        }

        $now = date('Y-m-d H:i:s');
        $count = 0;

        foreach ($jobs as $jobDef) {
            // Jobs in a batch are pending until batch starts
            DB::table('ahg_queue_job')->insert([
                'queue' => $batch->queue,
                'job_type' => $jobDef['job_type'] ?? '',
                'payload' => json_encode($jobDef['payload'] ?? []),
                'status' => self::STATUS_PENDING,
                'priority' => $jobDef['priority'] ?? 5,
                'batch_id' => $batchId,
                'attempt_count' => 0,
                'max_attempts' => $batch->max_retries,
                'delay_seconds' => 0,
                'backoff_strategy' => self::BACKOFF_EXPONENTIAL,
                'available_at' => '9999-12-31 23:59:59',
                'user_id' => $batch->user_id,
                'rate_limit_group' => $jobDef['rate_limit_group'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $count++;
        }

        DB::table('ahg_queue_batch')
            ->where('id', $batchId)
            ->update([
                'total_jobs' => DB::raw('total_jobs + ' . $count),
                'updated_at' => $now,
            ]);

        $this->logEvent(null, $batchId, 'batch_jobs_added', "Added {$count} jobs to batch");

        return $count;
    }

    /**
     * Start a batch — makes pending jobs available for workers.
     */
    public function startBatch(int $batchId): bool
    {
        $batch = $this->getBatch($batchId);
        if (!$batch || !in_array($batch->status, [self::BATCH_PENDING, self::BATCH_PAUSED])) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        DB::table('ahg_queue_batch')
            ->where('id', $batchId)
            ->update([
                'status' => self::BATCH_RUNNING,
                'started_at' => $batch->started_at ?? $now,
                'updated_at' => $now,
            ]);

        // Make batch jobs available (respect max_concurrent)
        $limit = $batch->max_concurrent ?: 5;

        DB::table('ahg_queue_job')
            ->where('batch_id', $batchId)
            ->where('status', self::STATUS_PENDING)
            ->orderBy('priority')
            ->orderBy('id')
            ->limit($limit)
            ->update([
                'available_at' => $now,
                'updated_at' => $now,
            ]);

        $this->logEvent(null, $batchId, 'batch_started', 'Batch started');

        return true;
    }

    /**
     * Pause a running batch — prevents new jobs from being picked up.
     */
    public function pauseBatch(int $batchId): bool
    {
        $batch = $this->getBatch($batchId);
        if (!$batch || $batch->status !== self::BATCH_RUNNING) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        DB::table('ahg_queue_batch')
            ->where('id', $batchId)
            ->update([
                'status' => self::BATCH_PAUSED,
                'updated_at' => $now,
            ]);

        // Push pending batch jobs back to future
        DB::table('ahg_queue_job')
            ->where('batch_id', $batchId)
            ->where('status', self::STATUS_PENDING)
            ->update([
                'available_at' => '9999-12-31 23:59:59',
                'updated_at' => $now,
            ]);

        $this->logEvent(null, $batchId, 'batch_paused', 'Batch paused');

        return true;
    }

    /**
     * Cancel a batch — cancels all pending/reserved jobs.
     */
    public function cancelBatch(int $batchId): bool
    {
        $batch = $this->getBatch($batchId);
        if (!$batch) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        DB::table('ahg_queue_batch')
            ->where('id', $batchId)
            ->update([
                'status' => self::BATCH_CANCELLED,
                'completed_at' => $now,
                'updated_at' => $now,
            ]);

        // Cancel pending and reserved jobs
        DB::table('ahg_queue_job')
            ->where('batch_id', $batchId)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_RESERVED])
            ->update([
                'status' => self::STATUS_CANCELLED,
                'updated_at' => $now,
            ]);

        $this->logEvent(null, $batchId, 'batch_cancelled', 'Batch cancelled');

        return true;
    }

    // =========================================================================
    // Worker Operations
    // =========================================================================

    /**
     * Reserve the next available job for a worker.
     *
     * Uses SELECT ... FOR UPDATE SKIP LOCKED for safe concurrent reservation.
     *
     * @param string|array $queues   Queue name(s) to poll
     * @param string       $workerId Worker process identifier
     *
     * @return object|null The reserved job row, or null if none available
     */
    public function reserveNext($queues, string $workerId): ?object
    {
        if (is_string($queues)) {
            $queues = array_map('trim', explode(',', $queues));
        }

        $now = date('Y-m-d H:i:s');
        $job = null;

        DB::connection()->transaction(function () use ($queues, $workerId, $now, &$job) {
            // SELECT ... FOR UPDATE SKIP LOCKED
            $job = DB::table('ahg_queue_job')
                ->whereIn('queue', $queues)
                ->where('status', self::STATUS_PENDING)
                ->where('available_at', '<=', $now)
                ->orderBy('priority')
                ->orderBy('available_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (!$job) {
                return;
            }

            DB::table('ahg_queue_job')
                ->where('id', $job->id)
                ->update([
                    'status' => self::STATUS_RESERVED,
                    'reserved_at' => $now,
                    'worker_id' => $workerId,
                    'updated_at' => $now,
                ]);
        });

        if ($job) {
            $job->status = self::STATUS_RESERVED;
            $job->reserved_at = $now;
            $job->worker_id = $workerId;

            $this->logEvent($job->id, $job->batch_id, 'reserved', "Reserved by worker {$workerId}");
        }

        return $job;
    }

    /**
     * Mark a job as running.
     */
    public function markRunning(int $jobId, string $workerId): void
    {
        $now = date('Y-m-d H:i:s');

        DB::table('ahg_queue_job')
            ->where('id', $jobId)
            ->update([
                'status' => self::STATUS_RUNNING,
                'started_at' => $now,
                'worker_id' => $workerId,
                'attempt_count' => DB::raw('attempt_count + 1'),
                'updated_at' => $now,
            ]);

        $this->logEvent($jobId, null, 'started', "Job started by worker {$workerId}");
    }

    /**
     * Mark a job as completed.
     *
     * Also handles chain advancement and batch progress updates.
     */
    public function markCompleted(int $jobId, array $resultData = [], int $processingTimeMs = 0): void
    {
        $now = date('Y-m-d H:i:s');

        $job = $this->getJob($jobId);

        DB::table('ahg_queue_job')
            ->where('id', $jobId)
            ->update([
                'status' => self::STATUS_COMPLETED,
                'completed_at' => $now,
                'processing_time_ms' => $processingTimeMs,
                'result_data' => !empty($resultData) ? json_encode($resultData) : null,
                'error_message' => null,
                'updated_at' => $now,
            ]);

        $this->logEvent($jobId, $job->batch_id ?? null, 'completed', "Job completed in {$processingTimeMs}ms");

        // Advance chain — make next job available
        if ($job && $job->chain_id) {
            $this->advanceChain($job->chain_id, $job->chain_order);
        }

        // Update batch progress
        if ($job && $job->batch_id) {
            $this->updateBatchProgress($job->batch_id);
        }
    }

    /**
     * Mark a job as failed.
     *
     * If retries remain, reschedules with backoff. Otherwise moves to failed table.
     */
    public function markFailed(int $jobId, string $errorMessage, $errorCode = null, ?string $errorTrace = null): void
    {
        $now = date('Y-m-d H:i:s');
        $job = $this->getJob($jobId);

        if (!$job) {
            return;
        }

        $attemptCount = $job->attempt_count;
        $maxAttempts = $job->max_attempts;

        if ($attemptCount < $maxAttempts) {
            // Retry with backoff
            $delay = $this->calculateBackoff($job->backoff_strategy, $attemptCount);
            $retryAt = date('Y-m-d H:i:s', time() + $delay);

            DB::table('ahg_queue_job')
                ->where('id', $jobId)
                ->update([
                    'status' => self::STATUS_PENDING,
                    'available_at' => $retryAt,
                    'error_message' => $errorMessage,
                    'error_code' => $errorCode,
                    'error_trace' => $errorTrace ? mb_substr($errorTrace, 0, 5000) : null,
                    'worker_id' => null,
                    'reserved_at' => null,
                    'started_at' => null,
                    'updated_at' => $now,
                ]);

            $this->logEvent($jobId, $job->batch_id, 'retried', "Retry scheduled (attempt {$attemptCount}/{$maxAttempts}), available at {$retryAt}", [
                'error' => mb_substr($errorMessage, 0, 500),
                'backoff_seconds' => $delay,
            ]);
        } else {
            // Max retries exhausted — move to failed table
            DB::table('ahg_queue_job')
                ->where('id', $jobId)
                ->update([
                    'status' => self::STATUS_FAILED,
                    'completed_at' => $now,
                    'error_message' => $errorMessage,
                    'error_code' => $errorCode,
                    'error_trace' => $errorTrace ? mb_substr($errorTrace, 0, 5000) : null,
                    'updated_at' => $now,
                ]);

            DB::table('ahg_queue_failed')->insert([
                'queue' => $job->queue,
                'job_type' => $job->job_type,
                'payload' => $job->payload,
                'error_message' => $errorMessage,
                'error_trace' => $errorTrace ? mb_substr($errorTrace, 0, 5000) : null,
                'original_job_id' => $jobId,
                'batch_id' => $job->batch_id,
                'user_id' => $job->user_id,
                'attempt_count' => $attemptCount,
                'failed_at' => $now,
            ]);

            $this->logEvent($jobId, $job->batch_id, 'failed', "Job permanently failed after {$attemptCount} attempts", [
                'error' => mb_substr($errorMessage, 0, 500),
            ]);

            // If part of a chain, cancel subsequent chain jobs
            if ($job->chain_id) {
                $this->cancelChainAfter($job->chain_id, $job->chain_order);
            }

            // Update batch progress
            if ($job->batch_id) {
                $this->updateBatchProgress($job->batch_id, true);
            }
        }
    }

    // =========================================================================
    // Chain Helpers
    // =========================================================================

    /**
     * Advance to the next job in a chain.
     */
    private function advanceChain(int $chainId, int $completedOrder): void
    {
        $now = date('Y-m-d H:i:s');
        $nextOrder = $completedOrder + 1;

        $nextJob = DB::table('ahg_queue_job')
            ->where('chain_id', $chainId)
            ->where('chain_order', $nextOrder)
            ->where('status', self::STATUS_PENDING)
            ->first();

        if ($nextJob) {
            DB::table('ahg_queue_job')
                ->where('id', $nextJob->id)
                ->update([
                    'available_at' => $now,
                    'updated_at' => $now,
                ]);

            $this->logEvent($nextJob->id, null, 'chain_advanced', "Chain job unlocked (order {$nextOrder})");
        }
    }

    /**
     * Cancel all chain jobs after a failed job.
     */
    private function cancelChainAfter(int $chainId, int $failedOrder): void
    {
        $now = date('Y-m-d H:i:s');

        $cancelled = DB::table('ahg_queue_job')
            ->where('chain_id', $chainId)
            ->where('chain_order', '>', $failedOrder)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_RESERVED])
            ->update([
                'status' => self::STATUS_CANCELLED,
                'error_message' => "Cancelled: predecessor in chain (order {$failedOrder}) failed",
                'updated_at' => $now,
            ]);

        if ($cancelled > 0) {
            $this->logEvent(null, null, 'chain_cancelled', "Cancelled {$cancelled} subsequent chain jobs after failure at order {$failedOrder}", [
                'chain_id' => $chainId,
            ]);
        }
    }

    // =========================================================================
    // Batch Progress
    // =========================================================================

    /**
     * Update batch progress counters and check for completion.
     */
    private function updateBatchProgress(int $batchId, bool $failed = false): void
    {
        $now = date('Y-m-d H:i:s');

        $stats = DB::table('ahg_queue_job')
            ->where('batch_id', $batchId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            ")
            ->first();

        $total = (int) $stats->total;
        $completed = (int) $stats->completed;
        $failedCount = (int) $stats->failed;
        $percent = $total > 0 ? round(($completed + $failedCount) / $total * 100, 2) : 0;

        $update = [
            'completed_jobs' => $completed,
            'failed_jobs' => $failedCount,
            'progress_percent' => $percent,
            'updated_at' => $now,
        ];

        // Check if batch is done
        if (($completed + $failedCount) >= $total && $total > 0) {
            $update['status'] = $failedCount > 0 ? self::BATCH_FAILED : self::BATCH_COMPLETED;
            $update['completed_at'] = $now;

            $this->logEvent(null, $batchId, 'batch_completed', "Batch finished: {$completed} completed, {$failedCount} failed");

            // Fire callback if set
            $batch = $this->getBatch($batchId);
            if ($batch && $batch->on_complete_callback) {
                $this->fireCallback($batch->on_complete_callback, $batchId);
            }
        } else {
            // Release more batch jobs if needed
            $this->releaseBatchJobs($batchId);
        }

        DB::table('ahg_queue_batch')
            ->where('id', $batchId)
            ->update($update);
    }

    /**
     * Release more pending batch jobs up to max_concurrent.
     */
    private function releaseBatchJobs(int $batchId): void
    {
        $batch = $this->getBatch($batchId);
        if (!$batch || $batch->status !== self::BATCH_RUNNING) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        // Count currently active (reserved + running)
        $active = DB::table('ahg_queue_job')
            ->where('batch_id', $batchId)
            ->whereIn('status', [self::STATUS_RESERVED, self::STATUS_RUNNING])
            ->count();

        $slotsAvailable = max(0, $batch->max_concurrent - $active);
        if ($slotsAvailable <= 0) {
            return;
        }

        DB::table('ahg_queue_job')
            ->where('batch_id', $batchId)
            ->where('status', self::STATUS_PENDING)
            ->where('available_at', '>', $now)
            ->orderBy('priority')
            ->orderBy('id')
            ->limit($slotsAvailable)
            ->update([
                'available_at' => $now,
                'updated_at' => $now,
            ]);
    }

    /**
     * Fire a batch completion callback.
     */
    private function fireCallback(string $callback, int $batchId): void
    {
        try {
            if (str_contains($callback, '::')) {
                [$class, $method] = explode('::', $callback, 2);
                if (class_exists($class) && method_exists($class, $method)) {
                    $class::$method($batchId);
                }
            }
        } catch (\Throwable $e) {
            $this->logEvent(null, $batchId, 'callback_error', 'Batch callback failed: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // Progress
    // =========================================================================

    /**
     * Update job progress.
     */
    public function updateProgress(int $jobId, int $current, int $total, string $message = ''): void
    {
        DB::table('ahg_queue_job')
            ->where('id', $jobId)
            ->update([
                'progress_current' => $current,
                'progress_total' => $total,
                'progress_message' => $message ? mb_substr($message, 0, 500) : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Get job progress.
     */
    public function getProgress(int $jobId): array
    {
        $job = DB::table('ahg_queue_job')
            ->where('id', $jobId)
            ->select(['status', 'progress_current', 'progress_total', 'progress_message',
                       'error_message', 'started_at', 'completed_at', 'processing_time_ms'])
            ->first();

        if (!$job) {
            return ['found' => false];
        }

        $percent = $job->progress_total > 0
            ? round($job->progress_current / $job->progress_total * 100, 2)
            : 0;

        return [
            'found' => true,
            'status' => $job->status,
            'current' => (int) $job->progress_current,
            'total' => (int) $job->progress_total,
            'percent' => $percent,
            'message' => $job->progress_message,
            'error' => $job->error_message,
            'started_at' => $job->started_at,
            'completed_at' => $job->completed_at,
            'processing_time_ms' => $job->processing_time_ms,
        ];
    }

    /**
     * Get batch progress.
     */
    public function getBatchProgress(int $batchId): array
    {
        $batch = $this->getBatch($batchId);
        if (!$batch) {
            return ['found' => false];
        }

        return [
            'found' => true,
            'status' => $batch->status,
            'total_jobs' => (int) $batch->total_jobs,
            'completed_jobs' => (int) $batch->completed_jobs,
            'failed_jobs' => (int) $batch->failed_jobs,
            'progress_percent' => (float) $batch->progress_percent,
            'started_at' => $batch->started_at,
            'completed_at' => $batch->completed_at,
        ];
    }

    // =========================================================================
    // Query
    // =========================================================================

    /**
     * Get a single job by ID.
     */
    public function getJob(int $id): ?object
    {
        return DB::table('ahg_queue_job')->where('id', $id)->first();
    }

    /**
     * Get a single batch by ID.
     */
    public function getBatch(int $id): ?object
    {
        return DB::table('ahg_queue_batch')->where('id', $id)->first();
    }

    /**
     * Get recent jobs with optional filters.
     */
    public function getRecentJobs(array $filters = [], int $limit = 25, int $page = 1): array
    {
        $query = DB::table('ahg_queue_job');

        if (!empty($filters['queue'])) {
            $query->where('queue', $filters['queue']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['job_type'])) {
            $query->where('job_type', $filters['job_type']);
        }
        if (!empty($filters['batch_id'])) {
            $query->where('batch_id', $filters['batch_id']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        $total = $query->count();
        $offset = ($page - 1) * $limit;

        $items = (clone $query)
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return [
            'items' => $items->toArray(),
            'total' => $total,
            'page' => $page,
            'pages' => $total > 0 ? (int) ceil($total / $limit) : 0,
        ];
    }

    /**
     * Get queue statistics.
     */
    public function getStats(?string $queue = null): array
    {
        $query = DB::table('ahg_queue_job');
        if ($queue) {
            $query->where('queue', $queue);
        }

        $stats = $query->selectRaw("
            queue,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved,
            SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) as running,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        ")->groupBy('queue')->get();

        return $stats->toArray();
    }

    /**
     * Get failed jobs.
     */
    public function getFailedJobs(int $limit = 50, int $page = 1): array
    {
        $total = DB::table('ahg_queue_failed')->count();
        $offset = ($page - 1) * $limit;

        $items = DB::table('ahg_queue_failed')
            ->orderByDesc('failed_at')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return [
            'items' => $items->toArray(),
            'total' => $total,
            'page' => $page,
            'pages' => $total > 0 ? (int) ceil($total / $limit) : 0,
        ];
    }

    /**
     * Get active workers.
     */
    public function getActiveWorkers(): array
    {
        return DB::table('ahg_queue_job')
            ->whereIn('status', [self::STATUS_RESERVED, self::STATUS_RUNNING])
            ->whereNotNull('worker_id')
            ->select(['worker_id', DB::raw('COUNT(*) as job_count'), DB::raw('MIN(reserved_at) as since')])
            ->groupBy('worker_id')
            ->get()
            ->toArray();
    }

    // =========================================================================
    // Management
    // =========================================================================

    /**
     * Retry a specific failed job by moving it from failed table back to queue.
     */
    public function retryFailed(int $failedId): ?int
    {
        $failed = DB::table('ahg_queue_failed')->where('id', $failedId)->first();
        if (!$failed) {
            return null;
        }

        $now = date('Y-m-d H:i:s');

        // If original job still exists, reset it
        if ($failed->original_job_id) {
            $original = $this->getJob($failed->original_job_id);
            if ($original) {
                DB::table('ahg_queue_job')
                    ->where('id', $original->id)
                    ->update([
                        'status' => self::STATUS_PENDING,
                        'available_at' => $now,
                        'attempt_count' => 0,
                        'error_message' => null,
                        'error_code' => null,
                        'error_trace' => null,
                        'worker_id' => null,
                        'reserved_at' => null,
                        'started_at' => null,
                        'completed_at' => null,
                        'updated_at' => $now,
                    ]);

                DB::table('ahg_queue_failed')->where('id', $failedId)->delete();

                $this->logEvent($original->id, null, 'retried', 'Job retried from failed queue');

                return $original->id;
            }
        }

        // Original job gone — create a new one
        $newId = $this->dispatch(
            $failed->job_type,
            json_decode($failed->payload, true) ?: [],
            $failed->queue,
            5,
            0,
            3,
            $failed->user_id
        );

        DB::table('ahg_queue_failed')->where('id', $failedId)->delete();

        $this->logEvent($newId, null, 'retried', 'New job created from failed entry #' . $failedId);

        return $newId;
    }

    /**
     * Retry all failed jobs.
     *
     * @return int Number of jobs retried
     */
    public function retryAllFailed(): int
    {
        $failed = DB::table('ahg_queue_failed')
            ->orderBy('id')
            ->get();

        $count = 0;
        foreach ($failed as $item) {
            if ($this->retryFailed($item->id) !== null) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Flush (delete) all failed jobs.
     *
     * @return int Number deleted
     */
    public function flushFailed(): int
    {
        return DB::table('ahg_queue_failed')->delete();
    }

    /**
     * Cancel a single job.
     */
    public function cancelJob(int $jobId): bool
    {
        $job = $this->getJob($jobId);
        if (!$job) {
            return false;
        }

        if (in_array($job->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        DB::table('ahg_queue_job')
            ->where('id', $jobId)
            ->update([
                'status' => self::STATUS_CANCELLED,
                'completed_at' => $now,
                'updated_at' => $now,
            ]);

        $this->logEvent($jobId, $job->batch_id, 'cancelled', 'Job cancelled');

        return true;
    }

    /**
     * Cleanup old completed/cancelled jobs and logs.
     *
     * @param int $days Delete items older than this many days
     *
     * @return int Total rows deleted
     */
    public function cleanup(int $days = 30): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $deleted = 0;

        // Delete old completed/cancelled jobs
        $deleted += DB::table('ahg_queue_job')
            ->whereIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED])
            ->where('updated_at', '<', $cutoff)
            ->delete();

        // Delete old failed entries
        $deleted += DB::table('ahg_queue_failed')
            ->where('failed_at', '<', $cutoff)
            ->delete();

        // Delete old logs
        $deleted += DB::table('ahg_queue_log')
            ->where('created_at', '<', $cutoff)
            ->delete();

        // Delete old completed batches
        $deleted += DB::table('ahg_queue_batch')
            ->whereIn('status', [self::BATCH_COMPLETED, self::BATCH_CANCELLED, self::BATCH_FAILED])
            ->where('updated_at', '<', $cutoff)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('ahg_queue_job')
                    ->whereColumn('ahg_queue_job.batch_id', 'ahg_queue_batch.id')
                    ->whereNotIn('ahg_queue_job.status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_FAILED]);
            })
            ->delete();

        return $deleted;
    }

    /**
     * Recover stale jobs (reserved/running too long without heartbeat).
     *
     * @param int $timeoutMinutes Jobs reserved/running longer than this are considered stale
     *
     * @return int Number of recovered jobs
     */
    public function recoverStale(int $timeoutMinutes = 10): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$timeoutMinutes} minutes"));

        $stale = DB::table('ahg_queue_job')
            ->whereIn('status', [self::STATUS_RESERVED, self::STATUS_RUNNING])
            ->where(function ($q) use ($cutoff) {
                $q->where('reserved_at', '<', $cutoff)
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->whereNull('reserved_at')
                         ->where('updated_at', '<', $cutoff);
                  });
            })
            ->get();

        $count = 0;
        foreach ($stale as $job) {
            $this->markFailed(
                $job->id,
                'Job timed out (stale recovery after ' . $timeoutMinutes . ' minutes)',
                'STALE_TIMEOUT'
            );
            $count++;
        }

        return $count;
    }

    // =========================================================================
    // Rate Limiting
    // =========================================================================

    /**
     * Check if a rate limit group has capacity.
     */
    public function checkRateLimit(string $group): bool
    {
        $now = date('Y-m-d H:i:s');
        $windowStart = date('Y-m-d H:i:s', strtotime('-1 minute'));

        $limiter = DB::table('ahg_queue_rate_limit')
            ->where('group_name', $group)
            ->first();

        if (!$limiter) {
            return true; // No limit configured
        }

        // Reset window if expired
        if (!$limiter->window_start || $limiter->window_start < $windowStart) {
            DB::table('ahg_queue_rate_limit')
                ->where('id', $limiter->id)
                ->update([
                    'window_start' => $now,
                    'request_count' => 0,
                    'updated_at' => $now,
                ]);

            return true;
        }

        return $limiter->request_count < $limiter->max_per_minute;
    }

    /**
     * Record a rate limit usage.
     */
    public function recordRateUse(string $group): void
    {
        $now = date('Y-m-d H:i:s');

        DB::table('ahg_queue_rate_limit')
            ->where('group_name', $group)
            ->update([
                'request_count' => DB::raw('request_count + 1'),
                'updated_at' => $now,
            ]);
    }

    // =========================================================================
    // Logging
    // =========================================================================

    /**
     * Log a queue event.
     */
    public function logEvent(?int $jobId, ?int $batchId, string $eventType, string $message, array $details = []): void
    {
        try {
            DB::table('ahg_queue_log')->insert([
                'job_id' => $jobId,
                'batch_id' => $batchId,
                'event_type' => $eventType,
                'message' => mb_substr($message, 0, 500),
                'details' => !empty($details) ? json_encode($details) : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Logging should never break the main flow
            error_log('QueueService::logEvent failed: ' . $e->getMessage());
        }
    }

    /**
     * Get log events.
     */
    public function getLogEvents(?int $jobId = null, ?int $batchId = null, int $limit = 50): array
    {
        $query = DB::table('ahg_queue_log');

        if ($jobId !== null) {
            $query->where('job_id', $jobId);
        }
        if ($batchId !== null) {
            $query->where('batch_id', $batchId);
        }

        return $query->orderByDesc('id')->limit($limit)->get()->toArray();
    }

    // =========================================================================
    // Backoff Calculation
    // =========================================================================

    /**
     * Calculate backoff delay in seconds.
     */
    private function calculateBackoff(string $strategy, int $attempt): int
    {
        switch ($strategy) {
            case self::BACKOFF_LINEAR:
                return $attempt * 30; // 30s, 60s, 90s...

            case self::BACKOFF_EXPONENTIAL:
                return (int) min(pow(2, $attempt) * 10, 3600); // 20s, 40s, 80s... max 1hr

            case self::BACKOFF_NONE:
            default:
                return 0;
        }
    }

    // =========================================================================
    // Status Helpers
    // =========================================================================

    /**
     * Get a Bootstrap badge class for a status.
     */
    public static function statusBadge(string $status): string
    {
        return self::STATUS_BADGES[$status] ?? 'secondary';
    }

    /**
     * Get human-readable queue names.
     */
    public static function queueNames(): array
    {
        return [
            'default' => 'Default',
            'ai' => 'AI Processing',
            'ingest' => 'Data Ingest',
            'export' => 'Export',
            'sync' => 'Synchronization',
        ];
    }
}
