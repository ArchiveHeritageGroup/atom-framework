<?php

namespace AtomFramework\Console\Commands\Export;

use AtomFramework\Console\BaseCommand;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Export repository information to a CSV.
 *
 * Bootstraps Symfony/Propel context and uses the AtoM csvRepositoryExport
 * writer class to export all repository records.
 */
class CsvRepositoryExportCommand extends BaseCommand
{
    protected string $name = 'csv:repository-export';
    protected string $description = 'Export repository information to a CSV';

    protected string $detailedDescription = <<<'EOF'
Export repository information to a CSV file.

Exports all repositories with their internationalized names and metadata.

Examples:

    php bin/atom csv:repository-export /path/to/repositories.csv
EOF;

    protected function configure(): void
    {
        $this->addArgument('filename', 'Filename for the CSV output', true);
    }

    protected function handle(): int
    {
        // Bootstrap Symfony/Propel context
        $configuration = \ProjectConfiguration::getApplicationConfiguration('qubit', 'cli', false);
        $context = \sfContext::createInstance($configuration);
        $databaseManager = new \sfDatabaseManager($configuration);

        $filename = $this->argument('filename');

        $writer = new \csvRepositoryExport($filename);

        $itemsExported = 0;

        foreach ($this->getRepositories() as $r) {
            $context->getUser()->setCulture($r->culture);
            $repository = \QubitRepository::getById($r->id);

            $writer->exportResource($repository);

            $name = $repository->getAuthorizedFormOfName(['cultureFallback' => true]);
            $this->info("Exported {$name} (culture: {$r->culture})");

            ++$itemsExported;
        }

        $this->newline();
        $this->success("Export complete ({$itemsExported} repositories exported).");

        return 0;
    }

    /**
     * Fetch all repository records (excluding root) with their cultures.
     */
    private function getRepositories(): array
    {
        return \QubitPdo::fetchAll(
            'SELECT id, culture FROM repository_i18n WHERE id <> ?',
            [\QubitRepository::ROOT_ID]
        );
    }
}
