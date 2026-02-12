<?php

namespace AtomFramework\Console\Commands\Export;

use AtomFramework\Console\BaseCommand;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Bulk export multiple EAC XML files at once for authority records.
 *
 * Bootstraps Symfony/Propel context and exports authority records as
 * EAC-CPF XML files using AtoM's built-in XML template rendering.
 */
class EacExportCommand extends BaseCommand
{
    protected string $name = 'export:auth-recs';
    protected string $description = 'Bulk export multiple EAC XML files at once for authority records';

    protected string $detailedDescription = <<<'EOF'
Bulk export multiple EAC-CPF XML files for authority records.

Exports each authority record as an individual EAC XML file in the
specified directory.

Examples:

    php bin/atom export:auth-recs /path/to/export/
    php bin/atom export:auth-recs /path/to/export/ --criteria="a.id > 100"
    php bin/atom export:auth-recs /path/to/export/ --items-until-update=10
EOF;

    protected function configure(): void
    {
        $this->addArgument('path', 'The destination directory for export file(s)', true);
        $this->addOption('items-until-update', null, 'Indicate progress every n items');
        $this->addOption('criteria', null, 'Export criteria (additional SQL WHERE clause)');
    }

    protected function handle(): int
    {
        $path = $this->argument('path');
        $itemsUntilUpdate = $this->option('items-until-update');
        $criteria = $this->option('criteria');

        $this->checkPathIsWritable($path);

        // Bootstrap Symfony/Propel context
        $configuration = \ProjectConfiguration::getApplicationConfiguration('qubit', 'cli', false);
        $context = \sfContext::createInstance($configuration);

        $databaseManager = new \sfDatabaseManager($configuration);

        $itemsExported = 0;

        $this->includeXmlExportClassesAndHelpers();

        // Query for actors (excluding repositories and users)
        $query = 'SELECT a.id FROM actor a
            JOIN actor_i18n ai ON a.id = ai.id
            JOIN object o ON a.id = o.id
            WHERE a.id != ? AND o.class_name = ?';

        if ($criteria) {
            $query .= ' AND ' . $criteria;
        }

        foreach (\QubitPdo::fetchAll($query, [\QubitActor::ROOT_ID, 'QubitActor']) as $row) {
            $resource = \QubitActor::getById($row->id);

            $filename = $this->generateSortableFilename($resource, 'xml', 'eac');
            $filePath = sprintf('%s/%s', $path, $filename);

            // Only export actor the first time it's encountered
            if (!file_exists($filePath)) {
                $rawXml = $this->captureResourceExportTemplateOutput($resource, 'eac');

                try {
                    $xml = \Qubit::tidyXml($rawXml);
                } catch (\Exception $e) {
                    $badXmlFilePath = sys_get_temp_dir() . '/' . $filename;
                    file_put_contents($badXmlFilePath, $rawXml);

                    throw new \Exception('Saved invalid generated XML to ' . $badXmlFilePath);
                }

                file_put_contents($filePath, $xml);
                $this->indicateProgress($itemsExported, $itemsUntilUpdate);
                ++$itemsExported;
            } else {
                $this->line("{$filePath} already exists, skipping...");
            }
        }

        $this->newline();
        $this->success("Export complete ({$itemsExported} actors exported).");

        return 0;
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
     * Capture the output of rendering an export template for a resource.
     */
    private function captureResourceExportTemplateOutput($resource, string $format): string
    {
        $pluginName = 'sf' . ucfirst($format) . 'Plugin';
        $template = sprintf(
            'plugins/%s/modules/%s/templates/indexSuccess.xml.php',
            $pluginName,
            $pluginName
        );

        $eac = new \sfEacPlugin($resource);

        $iso639convertor = new \fbISO639_Map();

        $exportLanguage = \sfContext::getInstance()->user->getCulture();
        $sourceLanguage = $resource->getSourceCulture();

        ob_start();
        include $template;
        $output = ob_get_contents();
        ob_end_clean();

        return $output;
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
     * Display a progress dot at configured intervals.
     */
    private function indicateProgress(int $count, ?string $itemsUntilUpdate): void
    {
        if (null === $itemsUntilUpdate || 0 === ($count % max(1, (int) $itemsUntilUpdate))) {
            echo '.';
        }
    }
}
