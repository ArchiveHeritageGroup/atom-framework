<?php

namespace AtomFramework\Console\Commands\Jobs;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Services\QueueService;
use AtomExtensions\Database\DatabaseBootstrap;

/**
 * Retry failed queue jobs.
 *
 * Usage:
 *   php bin/atom queue:retry 42       # Retry specific failed job
 *   php bin/atom queue:retry --all    # Retry all failed jobs
 */
class QueueRetryCommand extends BaseCommand
{
    protected string $name = 'queue:retry';
    protected string $description = 'Retry failed queue jobs';
    protected string $detailedDescription = <<<'EOF'
Move failed jobs back to the queue for re-processing.

Arguments:
  id     The ID from the ahg_queue_failed table

Options:
  --all  Retry all failed jobs
EOF;

    protected function configure(): void
    {
        $this->addArgument('id', 'Failed job ID to retry', false);
        $this->addOption('all', 'a', 'Retry all failed jobs');
    }

    protected function handle(): int
    {
        DatabaseBootstrap::initializeFromAtom();

        $queueService = new QueueService();

        if ($this->hasOption('all')) {
            $count = $queueService->retryAllFailed();
            if ($count > 0) {
                $this->success("Retried {$count} failed job(s).");
            } else {
                $this->comment('No failed jobs to retry.');
            }

            return 0;
        }

        $id = $this->argument('id');
        if (!$id) {
            $this->error('Provide a failed job ID or use --all.');

            return 1;
        }

        $newId = $queueService->retryFailed((int) $id);
        if ($newId) {
            $this->success("Failed job #{$id} retried as queue job #{$newId}.");
        } else {
            $this->error("Failed job #{$id} not found.");

            return 1;
        }

        return 0;
    }
}
