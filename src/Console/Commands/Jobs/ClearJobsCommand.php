<?php

namespace AtomFramework\Console\Commands\Jobs;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Clear AtoM jobs.
 *
 * Ported from lib/task/jobs/clearJobsTask.class.php.
 */
class ClearJobsCommand extends BaseCommand
{
    protected string $name = 'jobs:clear';
    protected string $description = 'Clear AtoM jobs';
    protected string $detailedDescription = <<<'EOF'
Clears all jobs from the AtoM jobs table. Will warn if there are currently
running jobs before proceeding.
EOF;

    protected function configure(): void
    {
        $this->addOption('no-confirmation', 'B', 'Do not ask for confirmation');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $databaseManager = new \sfDatabaseManager(\sfProjectConfiguration::getActive());
        $databaseManager->getDatabase('propel')->getConnection();

        $sql = 'SELECT count(1) FROM job WHERE status_id=?';
        $runningJobCount = \QubitPdo::fetchColumn($sql, [\QubitTerm::JOB_STATUS_IN_PROGRESS_ID]);

        if ($runningJobCount > 0) {
            $this->warning(
                'AtoM reports there are jobs currently running. It is highly recommended '
                . "you make sure there aren't any jobs actually running."
            );
        }

        if (!$this->hasOption('no-confirmation')) {
            if (!$this->confirm('Are you SURE you want to clear all jobs in the database?')) {
                $this->info('Aborting.');

                return 0;
            }
        }

        $jobs = \QubitJob::getAll();
        foreach ($jobs as $job) {
            $job->delete();
        }

        $this->success('All jobs cleared successfully!');

        return 0;
    }
}
