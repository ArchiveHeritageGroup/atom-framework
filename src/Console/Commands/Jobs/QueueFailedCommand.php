<?php

namespace AtomFramework\Console\Commands\Jobs;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Services\QueueService;
use AtomExtensions\Database\DatabaseBootstrap;

/**
 * List or flush failed queue jobs.
 *
 * Usage:
 *   php bin/atom queue:failed              # List failed jobs
 *   php bin/atom queue:failed --flush      # Delete all failed jobs
 */
class QueueFailedCommand extends BaseCommand
{
    protected string $name = 'queue:failed';
    protected string $description = 'List or flush failed queue jobs';
    protected string $detailedDescription = <<<'EOF'
Show failed jobs from the ahg_queue_failed table, or flush them all.

Options:
  --flush    Delete all failed job records
  --limit    Number of entries to show (default: 25)
EOF;

    protected function configure(): void
    {
        $this->addOption('flush', 'f', 'Delete all failed jobs');
        $this->addOption('limit', 'l', 'Number to display', '25');
    }

    protected function handle(): int
    {
        DatabaseBootstrap::initializeFromAtom();

        $queueService = new QueueService();

        if ($this->hasOption('flush')) {
            $count = $queueService->flushFailed();
            if ($count > 0) {
                $this->success("Flushed {$count} failed job(s).");
            } else {
                $this->comment('No failed jobs to flush.');
            }

            return 0;
        }

        $limit = max(1, (int) ($this->option('limit') ?: 25));
        $result = $queueService->getFailedJobs($limit);

        if (empty($result['items'])) {
            $this->comment('No failed jobs.');

            return 0;
        }

        $this->bold("Failed Jobs ({$result['total']} total)");
        $this->newline();

        $headers = ['ID', 'Queue', 'Job Type', 'Attempts', 'Error', 'Failed At'];
        $rows = [];

        foreach ($result['items'] as $item) {
            $rows[] = [
                $item->id,
                $item->queue,
                $item->job_type,
                $item->attempt_count,
                mb_substr($item->error_message ?? '', 0, 60),
                $item->failed_at,
            ];
        }

        $this->table($headers, $rows);
        $this->newline();
        $this->line('Use "php bin/atom queue:retry <id>" to retry a specific job.');
        $this->line('Use "php bin/atom queue:retry --all" to retry all failed jobs.');

        return 0;
    }
}
