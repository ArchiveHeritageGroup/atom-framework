<?php

namespace AtomFramework\Console\Commands\Jobs;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Gearman worker daemon.
 *
 * Ported from lib/task/jobs/jobWorkerTask.class.php.
 *
 * NOTE: This command still delegates to `php symfony jobs:worker` because it
 * requires the full Symfony event dispatcher, sfContext, Gearman worker loop
 * with signal handlers, and tight integration with the Net_Gearman_Worker
 * class that cannot be cleanly reproduced outside the Symfony task runner.
 * The worker loop uses beginWork() callbacks, pcntl signals, and persistent
 * database connections that depend on the Symfony runtime.
 */
class JobWorkerCommand extends BaseCommand
{
    protected string $name = 'jobs:worker';
    protected string $description = 'Gearman worker daemon';
    protected string $detailedDescription = <<<'EOF'
Start a Gearman worker daemon to process AtoM jobs.

Usage: php bin/atom jobs:worker [--abilities="myAbility1,myAbility2,..."] [--types="general,sword,..."]

Options --max-job-count and --max-mem-usage control automatic shutdown thresholds.

NOTE: This command delegates to the Symfony task runner because the Gearman
worker loop requires the full Symfony runtime (event dispatcher, sfContext,
signal handlers, persistent connections).
EOF;

    protected function configure(): void
    {
        $this->addOption('types', null, 'Type of jobs to perform (check config/gearman.yml for details)');
        $this->addOption('abilities', null, 'Comma-separated string indicating which jobs this worker can do');
        $this->addOption('max-job-count', null, 'Maximum number of jobs before shutting down');
        $this->addOption('max-mem-usage', null, 'Memory threshold (kB) before shutting down');
    }

    protected function handle(): int
    {
        // Build the Symfony command with all options
        $args = '';

        $types = $this->option('types');
        if ($types) {
            $args .= ' --types=' . escapeshellarg($types);
        }

        $abilities = $this->option('abilities');
        if ($abilities) {
            $args .= ' --abilities=' . escapeshellarg($abilities);
        }

        $maxJobCount = $this->option('max-job-count');
        if ($maxJobCount) {
            $args .= ' --max-job-count=' . escapeshellarg($maxJobCount);
        }

        $maxMemUsage = $this->option('max-mem-usage');
        if ($maxMemUsage) {
            $args .= ' --max-mem-usage=' . escapeshellarg($maxMemUsage);
        }

        $cmd = sprintf(
            'php %s/symfony %s%s',
            escapeshellarg($this->atomRoot),
            escapeshellarg('jobs:worker'),
            $args
        );

        if ($this->verbose) {
            $this->comment('Delegating to: ' . $cmd);
        }

        return $this->passthru($cmd);
    }
}
