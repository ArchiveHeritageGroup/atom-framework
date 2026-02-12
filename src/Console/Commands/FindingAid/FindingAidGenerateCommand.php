<?php

namespace AtomFramework\Console\Commands\FindingAid;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Generate a Finding Aid document.
 *
 * Ported from lib/task/findingAid/findingAidGenerateTask.class.php.
 */
class FindingAidGenerateCommand extends BaseCommand
{
    protected string $name = 'finding-aid:generate';
    protected string $description = 'Generate a Finding Aid document';
    protected string $detailedDescription = <<<'EOF'
Generate and attach a Finding Aid document, in PDF or RTF format, for the
top-level archival description selected by SLUG.
EOF;

    protected function configure(): void
    {
        $this->addArgument('slug', 'The top-level archival description slug', true);
        $this->addOption('format', null, 'Finding aid format ("pdf" or "rtf")', 'pdf');
        $this->addOption('model', null, 'Finding aid model ("inventory-summary" or "full-details")', 'inventory-summary');
        $this->addOption('private', null, 'Include sensitive data like physical storage locations');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $slug = $this->argument('slug');

        $databaseManager = new \sfDatabaseManager(\sfProjectConfiguration::getActive());
        $databaseManager->getDatabase('propel')->getConnection();

        $resource = \QubitInformationObject::getBySlug($slug);

        if (null === $resource) {
            $this->error(sprintf('Invalid slug "%s"', $slug));

            return 1;
        }

        $options = [];
        $options['format'] = $this->option('format') ?? 'pdf';
        $options['model'] = $this->option('model') ?? 'inventory-summary';

        // Set up logger
        $dispatcher = new \sfEventDispatcher();
        $logger = new \sfConsoleLogger($dispatcher);

        if ($this->verbose) {
            $logger->setLogLevel(\sfLogger::DEBUG);
        }

        $options['logger'] = $logger;

        // Set authLevel option
        if ($this->hasOption('private')) {
            $options['authLevel'] = 'private';
        }

        $generator = new \QubitFindingAidGenerator($resource, $options);
        $generator->generate();

        $this->success('Finding aid generated successfully');

        return 0;
    }
}
