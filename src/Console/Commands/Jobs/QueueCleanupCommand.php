<?php

namespace AtomFramework\Console\Commands\Jobs;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Services\QueueService;
use AtomExtensions\Database\DatabaseBootstrap;

/**
 * Clean up old completed queue jobs and logs.
 *
 * Usage:
 *   php bin/atom queue:cleanup              # Purge items older than 30 days
 *   php bin/atom queue:cleanup --days=7     # Purge items older than 7 days
 */
class QueueCleanupCommand extends BaseCommand
{
    protected string $name = 'queue:cleanup';
    protected string $description = 'Clean up old completed queue jobs and logs';
    protected string $detailedDescription = <<<'EOF'
Purge completed, cancelled, and failed job records older than a specified number of days.
Also cleans up associated log entries and batch records.

Options:
  --days    Delete items older than N days (default: 30)
EOF;

    protected function configure(): void
    {
        $this->addOption('days', 'd', 'Days to keep', '30');
    }

    protected function handle(): int
    {
        DatabaseBootstrap::initializeFromAtom();

        $days = max(1, (int) ($this->option('days') ?: 30));
        $queueService = new QueueService();

        $this->line("Cleaning up queue data older than {$days} days...");

        $deleted = $queueService->cleanup($days);

        if ($deleted > 0) {
            $this->success("Cleaned up {$deleted} record(s).");
        } else {
            $this->comment('Nothing to clean up.');
        }

        return 0;
    }
}
