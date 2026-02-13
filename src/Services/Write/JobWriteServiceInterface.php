<?php

namespace AtomFramework\Services\Write;

/**
 * Contract for job write operations.
 *
 * Covers: creating and updating AtoM job records.
 * The job entity uses the AtoM inheritance chain:
 *   object -> job
 */
interface JobWriteServiceInterface
{
    /**
     * Create a new job record.
     *
     * @param array $data Job data including:
     *                    - name (string) job class name
     *                    - user_id (int) user who created the job
     *                    - status_id (int) QubitTerm status ID
     *                    - object_id (int|null) related object ID
     *                    - download_path (string|null)
     *                    - output (string|null)
     *
     * @return int The new job ID
     */
    public function createJob(array $data): int;

    /**
     * Update the status of an existing job.
     *
     * @param int $jobId    The job ID
     * @param int $statusId QubitTerm status ID (e.g., JOB_STATUS_COMPLETED_ID)
     */
    public function updateJobStatus(int $jobId, int $statusId): void;
}
