<?php

namespace AtomFramework\Contracts;

use AtomFramework\Services\QueueJobContext;

/**
 * Interface for queue job handlers.
 *
 * Plugins implement this to define how a job type is processed.
 */
interface QueueJobInterface
{
    /**
     * Process the job.
     *
     * @param array           $payload Job-specific arguments
     * @param QueueJobContext  $context Context for progress/logging
     *
     * @return array Result data stored in result_data column
     */
    public function handle(array $payload, QueueJobContext $context): array;

    /**
     * Maximum number of retry attempts.
     */
    public function maxAttempts(): int;

    /**
     * Per-job timeout in seconds (0 = no limit).
     */
    public function timeout(): int;
}
