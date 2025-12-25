<?php

declare(strict_types=1);

namespace AtomFramework\Commands;

use AtomFramework\Repositories\TiffPdfMergeRepository;
use AtomFramework\Services\TiffPdfMergeService;

/**
 * CLI command to process pending TIFF to PDF merge jobs
 */
class TiffPdfProcessCommand
{
    protected TiffPdfMergeService $service;
    protected TiffPdfMergeRepository $repository;

    public function __construct()
    {
        $this->repository = new TiffPdfMergeRepository();
        $this->service = new TiffPdfMergeService();
    }

    /**
     * Process pending jobs
     */
    public function processPending(int $limit = 10): array
    {
        $jobs = $this->repository->getPendingJobs($limit);
        $results = [];

        echo "Found " . $jobs->count() . " pending jobs\n";

        foreach ($jobs as $job) {
            echo "Processing job {$job->id}: {$job->job_name}\n";

            try {
                $result = $this->service->processJob($job->id);

                if ($result['success']) {
                    echo "  ✓ Created: {$result['output_filename']} ({$result['pages']} pages)\n";
                } else {
                    echo "  ✗ Failed: {$result['error']}\n";
                }

                $results[$job->id] = $result;
            } catch (\Exception $e) {
                echo "  ✗ Error: {$e->getMessage()}\n";
                $results[$job->id] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Process a specific job
     */
    public function processJob(int $jobId): array
    {
        echo "Processing job {$jobId}\n";

        try {
            $result = $this->service->processJob($jobId);

            if ($result['success']) {
                echo "  ✓ Created: {$result['output_filename']} ({$result['pages']} pages)\n";

                if (!empty($result['digital_object_id'])) {
                    echo "  → Attached as digital object ID: {$result['digital_object_id']}\n";
                }
            } else {
                echo "  ✗ Failed: {$result['error']}\n";
            }

            return $result;
        } catch (\Exception $e) {
            echo "  ✗ Error: {$e->getMessage()}\n";
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cleanup old jobs
     */
    public function cleanup(int $hoursOld = 24): int
    {
        echo "Cleaning up jobs older than {$hoursOld} hours...\n";

        $count = $this->repository->cleanupOldJobs($hoursOld);
        echo "Cleaned up {$count} old jobs\n";

        return $count;
    }

    /**
     * Show statistics
     */
    public function stats(): array
    {
        $stats = $this->repository->getStatistics();

        echo "\n=== TIFF to PDF Merge Statistics ===\n";
        echo "Total Jobs:      {$stats['total_jobs']}\n";
        echo "Pending:         {$stats['pending']}\n";
        echo "Processing:      {$stats['processing']}\n";
        echo "Completed:       {$stats['completed']}\n";
        echo "Failed:          {$stats['failed']}\n";
        echo "Total Files:     {$stats['total_files']}\n";
        echo "====================================\n\n";

        return $stats;
    }
}
