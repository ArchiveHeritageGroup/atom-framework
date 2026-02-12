<?php

namespace AtomFramework\Console\Commands\Jobs;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * List AtoM jobs.
 *
 * Ported from lib/task/jobs/listJobsTask.class.php.
 */
class ListJobsCommand extends BaseCommand
{
    protected string $name = 'jobs:list';
    protected string $description = 'List AtoM jobs';
    protected string $detailedDescription = <<<'EOF'
List AtoM jobs. If no options are set it will list ALL the jobs.
Use --completed to list only completed jobs, or --running for running jobs only.
EOF;

    protected function configure(): void
    {
        $this->addOption('completed', null, 'List only completed jobs');
        $this->addOption('running', null, 'List only running jobs');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $databaseManager = new \sfDatabaseManager(\sfProjectConfiguration::getActive());
        $databaseManager->getDatabase('propel')->getConnection();

        $criteria = new \Criteria();

        if ($this->hasOption('completed')) {
            $criteria->add(\QubitJob::STATUS_ID, \QubitTerm::JOB_STATUS_COMPLETED_ID);
            $criteria->add(\QubitJob::STATUS_ID, \QubitTerm::JOB_STATUS_ERROR_ID);
        }

        if ($this->hasOption('running')) {
            $criteria->add(\QubitJob::STATUS_ID, \QubitTerm::JOB_STATUS_IN_PROGRESS_ID);
        }

        $jobs = \QubitJob::get($criteria);

        foreach ($jobs as $job) {
            $this->bold($job->name);
            $this->line(' Status: ' . $job->getStatusString());
            $this->line(' Started: ' . $job->getCreationDateString());
            $this->line(' Completed: ' . $job->getCompletionDateString());
            $this->line(' User: ' . \QubitJob::getUserString($job));

            // Add notes (indented for readability)
            if (count($notes = $job->getNotes()) > 0) {
                $notesLabel = ' Notes: ';

                foreach ($notes as $note) {
                    $this->line($notesLabel . $note);
                    $notesLabel = '        ';
                }
            }

            $this->newline();
        }

        return 0;
    }
}
