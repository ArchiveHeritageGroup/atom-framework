<?php

namespace AtomFramework\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Context object passed to job handlers for progress reporting and logging.
 */
class QueueJobContext
{
    private int $jobId;
    private ?int $batchId;
    private QueueService $queueService;

    public function __construct(int $jobId, ?int $batchId, QueueService $queueService)
    {
        $this->jobId = $jobId;
        $this->batchId = $batchId;
        $this->queueService = $queueService;
    }

    /**
     * Get the current job ID.
     */
    public function jobId(): int
    {
        return $this->jobId;
    }

    /**
     * Get the batch ID (null if not part of a batch).
     */
    public function batchId(): ?int
    {
        return $this->batchId;
    }

    /**
     * Update job progress.
     */
    public function progress(int $current, int $total, string $message = ''): void
    {
        $this->queueService->updateProgress($this->jobId, $current, $total, $message);
    }

    /**
     * Log an event against this job.
     */
    public function log(string $message, array $details = []): void
    {
        $this->queueService->logEvent(
            $this->jobId,
            $this->batchId,
            'info',
            $message,
            $details
        );
    }

    /**
     * Check if the job has been cancelled (cooperative cancellation).
     */
    public function isCancelled(): bool
    {
        $status = DB::table('ahg_queue_job')
            ->where('id', $this->jobId)
            ->value('status');

        return $status === 'cancelled';
    }
}
