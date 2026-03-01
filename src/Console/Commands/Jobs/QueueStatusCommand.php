<?php

namespace AtomFramework\Console\Commands\Jobs;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Services\QueueService;
use AtomExtensions\Database\DatabaseBootstrap;

/**
 * Show queue status and statistics.
 *
 * Usage:
 *   php bin/atom queue:status
 *   php bin/atom queue:status --queue=ai
 */
class QueueStatusCommand extends BaseCommand
{
    protected string $name = 'queue:status';
    protected string $description = 'Show queue status and statistics';
    protected string $detailedDescription = <<<'EOF'
Display per-queue job counts by status and active worker information.
EOF;

    protected function configure(): void
    {
        $this->addOption('queue', 'q', 'Filter by queue name');
    }

    protected function handle(): int
    {
        DatabaseBootstrap::initializeFromAtom();

        $queueService = new QueueService();
        $queue = $this->option('queue') ?: null;

        // Queue stats
        $stats = $queueService->getStats($queue);

        if (empty($stats)) {
            $this->comment('No queue data found.');

            return 0;
        }

        $this->bold('Queue Statistics');
        $this->newline();

        $headers = ['Queue', 'Total', 'Pending', 'Reserved', 'Running', 'Completed', 'Failed', 'Cancelled'];
        $rows = [];

        foreach ($stats as $row) {
            $rows[] = [
                $row->queue,
                $row->total,
                $row->pending,
                $row->reserved,
                $row->running,
                $row->completed,
                $row->failed,
                $row->cancelled,
            ];
        }

        $this->table($headers, $rows);
        $this->newline();

        // Active workers
        $workers = $queueService->getActiveWorkers();

        if (!empty($workers)) {
            $this->bold('Active Workers');
            $this->newline();

            $wHeaders = ['Worker ID', 'Jobs', 'Since'];
            $wRows = [];
            foreach ($workers as $w) {
                $wRows[] = [$w->worker_id, $w->job_count, $w->since];
            }
            $this->table($wHeaders, $wRows);
        } else {
            $this->comment('No active workers.');
        }

        // Failed count
        $failedResult = $queueService->getFailedJobs(1);
        if ($failedResult['total'] > 0) {
            $this->newline();
            $this->warning("Failed jobs in archive: {$failedResult['total']}");
            $this->line('Run "php bin/atom queue:failed" to view, "php bin/atom queue:retry --all" to retry.');
        }

        return 0;
    }
}
