#!/usr/bin/env php
<?php

/**
 * TIFF to PDF Merge Worker
 * Polls database for pending jobs and processes them
 */

// Bootstrap the framework
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/Jobs/TiffPdfMergeJob.php';

use Illuminate\Database\Capsule\Manager as DB;
use AtomFramework\Jobs\TiffPdfMergeJob;

echo "[" . date('Y-m-d H:i:s') . "] Starting TIFF to PDF Merge Worker (polling mode)...\n";

$pollInterval = 5; // seconds

while (true) {
    try {
        // Find pending jobs that need processing
        $pendingJob = DB::table('tiff_pdf_merge_job')
            ->where('status', 'queued')
            ->orderBy('created_at', 'asc')
            ->first();

        if ($pendingJob) {
            echo "[" . date('Y-m-d H:i:s') . "] Found job ID: {$pendingJob->id} - {$pendingJob->job_name}\n";

            try {
                $job = new TiffPdfMergeJob($pendingJob->id);
                $result = $job->handle();

                if ($result) {
                    echo "[" . date('Y-m-d H:i:s') . "] Job {$pendingJob->id} completed successfully\n";
                } else {
                    echo "[" . date('Y-m-d H:i:s') . "] Job {$pendingJob->id} failed\n";
                }
            } catch (Exception $e) {
                echo "[" . date('Y-m-d H:i:s') . "] Error processing job {$pendingJob->id}: " . $e->getMessage() . "\n";

                // Mark as failed
                DB::table('tiff_pdf_merge_job')
                    ->where('id', $pendingJob->id)
                    ->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }
        }
    } catch (Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Worker error: " . $e->getMessage() . "\n";
    }

    // Sleep before next poll
    sleep($pollInterval);
}
