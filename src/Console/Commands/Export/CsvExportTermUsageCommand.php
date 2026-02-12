<?php

namespace AtomFramework\Console\Commands\Export;

use AtomFramework\Console\BaseCommand;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Export terms associated with information objects as CSV file(s).
 *
 * Bootstraps Symfony/Propel context and uses the AtoM QubitFlatfileExport
 * writer class to export term usage data.
 */
class CsvExportTermUsageCommand extends BaseCommand
{
    protected string $name = 'csv:export-term-usage';
    protected string $description = 'Export terms associated with information objects as CSV file(s)';

    protected string $detailedDescription = <<<'EOF'
Export terms associated with information objects as CSV file(s).

You must specify either --taxonomy-id or --taxonomy-name to identify the taxonomy.

Examples:

    php bin/atom csv:export-term-usage /path/to/output.csv --taxonomy-id=35
    php bin/atom csv:export-term-usage /path/to/output.csv --taxonomy-name="Subject"
    php bin/atom csv:export-term-usage /path/to/output.csv --taxonomy-name="Sujet" --taxonomy-name-culture=fr
EOF;

    protected function configure(): void
    {
        $this->addArgument('path', 'The destination file path for the CSV export', true);
        $this->addOption('items-until-update', null, 'Indicate progress every n items');
        $this->addOption('taxonomy-id', null, 'ID of taxonomy');
        $this->addOption('taxonomy-name', null, 'Name of taxonomy');
        $this->addOption('taxonomy-name-culture', null, 'Culture to use for taxonomy name lookup');
    }

    protected function handle(): int
    {
        $itemsUntilUpdate = $this->option('items-until-update');

        if (null !== $itemsUntilUpdate && !ctype_digit($itemsUntilUpdate)) {
            $this->error('items-until-update must be a number');

            return 1;
        }

        // Bootstrap Symfony/Propel context
        $configuration = \ProjectConfiguration::getApplicationConfiguration('qubit', 'cli', false);
        $context = \sfContext::createInstance($configuration);
        $databaseManager = new \sfDatabaseManager($configuration);

        $path = $this->argument('path');

        // Check for existing export file
        $this->exportFileReplacePrompt($path);

        $taxonomyId = $this->determineTaxonomyId();
        $itemsExported = $this->exportToCsv($taxonomyId, $path, $itemsUntilUpdate);

        if ($itemsExported) {
            $this->newline();
            $this->success("Export complete ({$itemsExported} terms exported).");
        } else {
            $this->info('No term usages found to export.');
        }

        return 0;
    }

    /**
     * Determine the taxonomy ID from the options provided.
     */
    private function determineTaxonomyId(): int
    {
        $taxonomyId = $this->option('taxonomy-id');

        if (null !== $taxonomyId && ctype_digit($taxonomyId)) {
            $criteria = new \Criteria();
            $criteria->add(\QubitTaxonomy::ID, $taxonomyId);

            if (null === \QubitTaxonomy::getOne($criteria)) {
                throw new \Exception('Invalid taxonomy-id.');
            }

            return (int) $taxonomyId;
        }

        $taxonomyName = $this->option('taxonomy-name');

        if (null !== $taxonomyName) {
            $culture = $this->option('taxonomy-name-culture') ?? 'en';

            $criteria = new \Criteria();
            $criteria->add(\QubitTaxonomyI18n::NAME, $taxonomyName);
            $criteria->add(\QubitTaxonomyI18n::CULTURE, $culture);

            $taxonomy = \QubitTaxonomyI18n::getOne($criteria);

            if (null === $taxonomy) {
                throw new \Exception('Invalid taxonomy-name and/or taxonomy-name-culture.');
            }

            return (int) $taxonomy->id;
        }

        throw new \Exception('Either the taxonomy-id or taxonomy-name must be used to specify a taxonomy.');
    }

    /**
     * Prompt the user if the export file already exists.
     */
    private function exportFileReplacePrompt(string $exportPath): void
    {
        if (file_exists($exportPath)) {
            if (!$this->confirm('The export file already exists. Do you want to replace it?')) {
                throw new \Exception('Export file already exists: aborting.');
            }

            unlink(realpath($exportPath));
        }
    }

    /**
     * Export term usage data to CSV.
     */
    private function exportToCsv(int $taxonomyId, string $exportPath, ?string $rowsUntilUpdate): int
    {
        $itemsExported = 0;

        $format = 'SELECT DISTINCT t.id, COUNT(i.id) AS use_count FROM %s t INNER JOIN %s r ON r.term_id=t.id INNER JOIN %s i ON r.object_id=i.id WHERE t.taxonomy_id=? GROUP BY (t.id) ORDER BY t.id';
        $sql = sprintf($format, \QubitTerm::TABLE_NAME, \QubitObjectTermRelation::TABLE_NAME, \QubitInformationObject::TABLE_NAME);

        $result = \QubitPdo::prepareAndExecute($sql, [$taxonomyId]);

        if ($result->rowCount()) {
            // Instantiate CSV writer using "usage" column ordering
            $writer = new \QubitFlatfileExport($exportPath, 'usage');
            $writer->loadResourceSpecificConfiguration('QubitTerm');

            while ($row = $result->fetch(\PDO::FETCH_OBJ)) {
                $resource = \QubitTerm::getById($row->id);
                $writer->setColumn('name', $resource->getName(['cultureFallback' => true]));
                $writer->setColumn('use_count', $row->use_count);
                $writer->exportResource($resource);

                $this->indicateProgress($itemsExported, $rowsUntilUpdate);
                ++$itemsExported;
            }
        }

        return $itemsExported;
    }

    /**
     * Display a progress dot at configured intervals.
     */
    private function indicateProgress(int $count, ?string $itemsUntilUpdate): void
    {
        if (null === $itemsUntilUpdate || 0 === ($count % max(1, (int) $itemsUntilUpdate))) {
            echo '.';
        }
    }
}
