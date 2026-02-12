<?php

namespace AtomFramework\Console\Commands\Export;

use AtomFramework\Console\BaseCommand;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Bulk export multiple XML files at once.
 *
 * Bootstraps Symfony/Propel context and exports information objects as
 * EAD or MODS XML files using AtoM's built-in XML template rendering.
 */
class BulkExportCommand extends BaseCommand
{
    protected string $name = 'export:bulk';
    protected string $description = 'Bulk export multiple XML files at once';

    protected string $detailedDescription = <<<'EOF'
Bulk export information object descriptions as XML files (EAD or MODS).

Each top-level description is exported as a separate XML file in the
specified directory.

Examples:

    php bin/atom export:bulk /path/to/export/
    php bin/atom export:bulk /path/to/export/ --format=mods
    php bin/atom export:bulk /path/to/file.xml --single-slug=my-fonds --format=ead
    php bin/atom export:bulk /path/to/export/ --public --criteria="i.id > 100"

Use --format to choose between "ead" (default) and "mods" formats.
Use --single-slug to export a single fonds/collection (EAD only).
Use --public to exclude draft descriptions.
EOF;

    protected function configure(): void
    {
        $this->addArgument('path', 'The destination directory (or file with --single-slug) for export', true);
        $this->addOption('items-until-update', null, 'Indicate progress every n items');
        $this->addOption('criteria', null, 'Export criteria (SQL WHERE clause)');
        $this->addOption('current-level-only', null, 'Do not export child descriptions of exported items');
        $this->addOption('single-slug', null, 'Export a single fonds or collection based on slug');
        $this->addOption('public', null, 'Do not export draft descriptions');
        $this->addOption('format', null, 'XML format ("ead" or "mods")', 'ead');
    }

    protected function handle(): int
    {
        $path = $this->argument('path');
        $format = $this->normalizeExportFormat($this->option('format'), ['ead', 'mods']);
        $itemsUntilUpdate = $this->option('items-until-update');
        $singleSlug = $this->option('single-slug');
        $isPublic = $this->hasOption('public');

        if (!$singleSlug) {
            $this->checkPathIsWritable($path);
        }

        // Bootstrap Symfony/Propel context
        $configuration = \ProjectConfiguration::getApplicationConfiguration('qubit', 'cli', false);
        $context = \sfContext::createInstance($configuration);

        // QubitSetting are not available for tasks? See lib/SiteSettingsFilter.class.php
        \sfConfig::add(\QubitSetting::getSettingsArray());

        $options = [
            'format' => $format,
            'criteria' => $this->option('criteria'),
            'current-level-only' => $this->hasOption('current-level-only'),
            'single-slug' => $singleSlug,
            'public' => $isPublic,
            'items-until-update' => $itemsUntilUpdate,
        ];

        $itemsExported = 0;

        $conn = $this->getDatabaseConnection($configuration);
        $sql = $this->informationObjectQuerySql($options);
        $rows = $conn->query($sql, \PDO::FETCH_ASSOC);

        $this->includeXmlExportClassesAndHelpers();

        foreach ($rows as $row) {
            $resource = \QubitInformationObject::getById($row['id']);

            // Don't export draft descriptions with public option
            if (
                $isPublic
                && \QubitTerm::PUBLICATION_STATUS_DRAFT_ID == $resource->getPublicationStatus()->statusId
            ) {
                continue;
            }

            $xml = $this->generateXml($resource, $format, $options);

            if ($singleSlug && 'ead' === $format) {
                if (is_dir($path)) {
                    $this->error('When using the single-slug option with EAD, path should be a file.');

                    return 1;
                }

                $filePath = $path;
            } else {
                $filename = $this->generateSortableFilename($resource, 'xml', $format);
                $filePath = sprintf('%s/%s', $path, $filename);
            }

            if (false === file_put_contents($filePath, $xml)) {
                throw new \Exception("Cannot write to path: {$filePath}");
            }

            $this->indicateProgress($itemsExported, $itemsUntilUpdate);

            if (0 === $itemsExported++ % 1000) {
                \Qubit::clearClassCaches();
            }
        }

        $this->newline();
        $this->success("Export complete ({$itemsExported} descriptions exported).");

        return 0;
    }

    /**
     * Generate XML for a resource using AtoM's template rendering.
     */
    private function generateXml($resource, string $format, array $options = []): string
    {
        try {
            $errLevel = error_reporting(E_ALL);

            $rawXml = $this->captureResourceExportTemplateOutput($resource, $format, $options);
            $xml = \Qubit::tidyXml($rawXml);

            error_reporting($errLevel);
        } catch (\Exception $e) {
            throw new \Exception(
                sprintf('Invalid XML generated for object %s.', $resource->id)
            );
        }

        return $xml;
    }

    /**
     * Capture the output of rendering an export template for a resource.
     */
    private function captureResourceExportTemplateOutput($resource, string $format, array $options = []): string
    {
        $pluginName = 'sf' . ucfirst($format) . 'Plugin';
        $template = sprintf(
            'plugins/%s/modules/%s/templates/indexSuccess.xml.php',
            $pluginName,
            $pluginName
        );

        switch ($format) {
            case 'ead':
                $eadLevels = ['class', 'collection', 'file', 'fonds', 'item', 'otherlevel', 'recordgrp', 'series', 'subfonds', 'subgrp', 'subseries'];
                $ead = new \sfEadPlugin($resource, $options);

                $findingAid = isset($options['findingAidVisibilitiy']) ? (bool) $options['findingAidVisibilitiy'] : false;

                extract([
                    'resource' => $resource,
                    'findingAidVisibilitiy' => $findingAid,
                ]);

                break;

            case 'mods':
                $mods = new \sfModsPlugin($resource);

                break;

            case 'dc':
                $dc = new \sfDcPlugin($resource);
                $template = 'plugins/sfDcPlugin/modules/sfDcPlugin/templates/_dc.xml.php';

                break;

            default:
                throw new \Exception('Unknown format.');
        }

        $iso639convertor = new \fbISO639_Map();

        $exportLanguage = \sfContext::getInstance()->user->getCulture();
        $sourceLanguage = $resource->getSourceCulture();

        ob_start();
        include $template;
        $output = ob_get_contents();
        if ('dc' === $format) {
            $output = '<?xml version="1.0" encoding="' . \sfConfig::get('sf_charset', 'UTF-8') . "\" ?>\n" . $output;
        }
        ob_end_clean();

        return $output;
    }

    /**
     * Include XML export plugin classes and helpers.
     */
    private function includeXmlExportClassesAndHelpers(): void
    {
        $appRoot = \sfConfig::get('sf_root_dir');

        $includes = [
            '/plugins/sfEadPlugin/lib/sfEadPlugin.class.php',
            '/plugins/sfModsPlugin/lib/sfModsPlugin.class.php',
            '/plugins/sfDcPlugin/lib/sfDcPlugin.class.php',
            '/plugins/sfIsaarPlugin/lib/sfIsaarPlugin.class.php',
            '/plugins/sfEacPlugin/lib/sfEacPlugin.class.php',
            '/vendor/symfony/lib/helper/UrlHelper.php',
            '/vendor/symfony/lib/helper/I18NHelper.php',
            '/vendor/FreeBeerIso639Map.php',
            '/vendor/symfony/lib/helper/EscapingHelper.php',
            '/lib/helper/QubitHelper.php',
        ];

        foreach ($includes as $include) {
            include_once $appRoot . $include;
        }
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
                // Fetch top-level descriptions if EAD (EAD data nests children) or if only exporting top-level
                $whereClause = ('ead' === $options['format'] || $options['current-level-only'])
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

        // EAD data nests children, so if exporting specific slug we just need top-level item
        if ('ead' === $options['format'] && isset($options['single-slug']) && $options['single-slug']) {
            $query .= ' LIMIT 1';
        }

        return $query;
    }

    /**
     * Generate a sortable filename for an export file.
     */
    private function generateSortableFilename($resource, string $extension, string $formatAbbreviation): string
    {
        $maxSlugChars = 200;

        return sprintf(
            '%s_%s_%s.%s',
            $formatAbbreviation,
            str_pad($resource->id, 10, '0', STR_PAD_LEFT),
            substr($resource->slug, 0, $maxSlugChars),
            $extension
        );
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
