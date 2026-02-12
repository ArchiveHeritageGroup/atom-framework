<?php

namespace AtomFramework\Console\Commands\Export;

use AtomFramework\Console\BaseCommand;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Bulk export multiple CSV files at once.
 *
 * Bootstraps Symfony/Propel context and uses the AtoM csvInformationObjectExport
 * writer class to bulk export information object descriptions as CSV.
 */
class BulkCsvExportCommand extends BaseCommand
{
    protected string $name = 'export:bulk-csv';
    protected string $description = 'Bulk export multiple CSV files at once';

    protected string $detailedDescription = <<<'EOF'
Bulk export information object descriptions to CSV files.

Examples:

    php bin/atom export:bulk-csv /path/to/export/
    php bin/atom export:bulk-csv /path/to/export/ --standard=rad
    php bin/atom export:bulk-csv /path/to/file.csv --single-slug=my-fonds
    php bin/atom export:bulk-csv /path/to/export/ --rows-per-file=1000

Use --standard to choose between "isad" (default) and "rad" formats.
Use --single-slug to export a single fonds/collection and its children.
Use --public to exclude draft descriptions.
Use --current-level-only to skip child descriptions.
EOF;

    protected function configure(): void
    {
        $this->addArgument('path', 'The destination directory (or file with --single-slug) for export', true);
        $this->addOption('items-until-update', null, 'Indicate progress every n items');
        $this->addOption('criteria', null, 'Export criteria (SQL WHERE clause)');
        $this->addOption('current-level-only', null, 'Do not export child descriptions of exported items');
        $this->addOption('single-slug', null, 'Export a single fonds or collection based on slug');
        $this->addOption('public', null, 'Do not export draft descriptions');
        $this->addOption('standard', null, 'Description format ("isad" or "rad")', 'isad');
        $this->addOption('rows-per-file', null, 'Rows per file (disregarded if writing to a file, not a directory)');
    }

    protected function handle(): int
    {
        // Bootstrap Symfony/Propel context
        $configuration = \ProjectConfiguration::getApplicationConfiguration('qubit', 'cli', false);
        $context = \sfContext::createInstance($configuration);

        // QubitSetting are not available for tasks? See lib/SiteSettingsFilter.class.php
        \sfConfig::add(\QubitSetting::getSettingsArray());

        $path = $this->argument('path');
        $standard = $this->normalizeExportFormat($this->option('standard'), ['isad', 'rad']);
        $itemsUntilUpdate = $this->option('items-until-update');
        $singleSlug = $this->option('single-slug');
        $isPublic = $this->hasOption('public');
        $rowsPerFile = $this->option('rows-per-file') ?: false;

        if (!$singleSlug) {
            $this->checkPathIsWritable($path);
        }

        $itemsExported = 0;

        $options = [
            'standard' => $standard,
            'criteria' => $this->option('criteria'),
            'current-level-only' => $this->hasOption('current-level-only'),
            'single-slug' => $singleSlug,
            'public' => $isPublic,
            'format' => $standard,
            'items-until-update' => $itemsUntilUpdate,
        ];

        $conn = $this->getDatabaseConnection($configuration);
        $sql = $this->informationObjectQuerySql($options);
        $rows = $conn->query($sql, \PDO::FETCH_ASSOC);

        $this->info('Exporting as ' . strtoupper($standard) . '.');

        // Instantiate CSV writer
        $writer = new \csvInformationObjectExport($path, $standard, $rowsPerFile);
        $writer->user = $context->getUser();
        $writer->setOptions($options);

        foreach ($rows as $row) {
            $writer->user->setCulture($row['culture']);
            $resource = \QubitInformationObject::getById($row['id']);

            // Don't export draft descriptions with public option
            if (
                $isPublic
                && \QubitTerm::PUBLICATION_STATUS_DRAFT_ID == $resource->getPublicationStatus()->statusId
            ) {
                continue;
            }

            if ($singleSlug) {
                if (is_dir($path)) {
                    $this->error('When using the single-slug option, path should be a file.');

                    return 1;
                }
            }

            $writer->exportResource($resource);

            $this->indicateProgress($itemsExported, $itemsUntilUpdate);
            ++$itemsExported;

            if (0 === $itemsExported % 1000) {
                \Qubit::clearClassCaches();
            }
        }

        $this->newline();
        $this->success("Export complete ({$itemsExported} descriptions exported).");

        return 0;
    }

    /**
     * Build the SQL query for information object export.
     */
    private function informationObjectQuerySql(array $options): string
    {
        if (isset($options['single-slug']) && $options['single-slug']) {
            $query = 'SELECT i.lft, i.rgt, i.id FROM information_object i INNER JOIN slug s ON i.id=s.object_id WHERE s.slug = ?';
            $slug = \QubitPdo::fetchOne($query, [$options['single-slug']]);

            if (false === $slug) {
                throw new \Exception('Slug ' . $options['single-slug'] . ' not found.');
            }

            $whereClause = 'i.lft >= ' . $slug->lft . ' AND i.rgt <=' . $slug->rgt;
        } else {
            if ($options['criteria']) {
                $whereClause = $options['criteria'];
            } else {
                $whereClause = $options['current-level-only']
                    ? 'i.parent_id = '
                    : 'i.id != ';
                $whereClause .= \QubitInformationObject::ROOT_ID;
            }
        }

        $query = 'SELECT i.*, i18n.* FROM information_object i
            INNER JOIN information_object_i18n i18n ON i.id=i18n.id
            LEFT JOIN digital_object do ON i.id=do.object_id
            WHERE ' . $whereClause;

        $query .= ' ORDER BY i.lft';

        return $query;
    }

    /**
     * Validate and normalize the export format.
     */
    private function normalizeExportFormat(string $format, array $validFormats): string
    {
        $format = strtolower($format);

        if (!in_array($format, $validFormats)) {
            throw new \Exception('Invalid format. Allowed formats: ' . implode(', ', $validFormats));
        }

        return $format;
    }

    /**
     * Verify that the given path is a writable directory.
     */
    private function checkPathIsWritable(string $path): void
    {
        if (!is_dir($path)) {
            throw new \Exception('You must specify a valid directory');
        }

        if (!is_writable($path)) {
            throw new \Exception("Can't write to this directory");
        }
    }

    /**
     * Get a PDO database connection via Symfony/Propel.
     */
    private function getDatabaseConnection(\ProjectConfiguration $configuration): \PDO
    {
        $databaseManager = new \sfDatabaseManager($configuration);

        return $databaseManager->getDatabase('propel')->getConnection();
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
