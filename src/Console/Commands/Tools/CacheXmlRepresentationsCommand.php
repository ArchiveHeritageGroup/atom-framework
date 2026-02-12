<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Render all descriptions as XML and cache the results as files.
 *
 * Ported from lib/task/arCacheDescriptionXmlTask.class.php.
 * Uses Propel to iterate over information objects and generate
 * EAD and DC XML representations.
 */
class CacheXmlRepresentationsCommand extends BaseCommand
{
    protected string $name = 'cache:xml-representations';
    protected string $description = 'Render all descriptions as XML and cache the results as files';
    protected string $detailedDescription = <<<'EOF'
Cycle through all information objects and export their EAD and DC XML
representations, caching the results as files on disk.

Options:
    --skip     Number of information objects to skip (default: 0)
    --limit    Number of information objects to export (default: all)
    --format   Format to export: "ead" or "dc" (default: both)
EOF;

    protected function configure(): void
    {
        $this->addOption('skip', null, 'Number of information objects to skip', '0');
        $this->addOption('limit', null, 'Number of information objects to export');
        $this->addOption('format', null, 'Format to export ("ead" or "dc")');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $skip = (int) $this->option('skip', '0');
        $limit = $this->option('limit');
        $format = $this->option('format');

        $this->info('Caching XML representations of information objects...');

        $cache = new \QubitInformationObjectXmlCache(['logger' => null]);
        $cache->exportAll([
            'skip' => $skip,
            'limit' => $limit ? (int) $limit : null,
            'format' => $format,
        ]);

        $this->success('Done.');

        return 0;
    }
}
