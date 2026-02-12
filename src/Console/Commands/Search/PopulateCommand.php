<?php

namespace AtomFramework\Console\Commands\Search;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Populate the search index.
 *
 * Ported from lib/task/search/arPopulateTask.class.php.
 */
class PopulateCommand extends BaseCommand
{
    protected string $name = 'search:populate';
    protected string $description = 'Populate search index';
    protected string $detailedDescription = <<<'EOF'
Empties, populates, and optimizes the search index. It may take quite a while
to run.

To exclude a document type, use the --exclude-types option:

  php bin/atom search:populate --exclude-types=term,actor

To see a list of available document types that can be excluded use the
--show-types option.
EOF;

    protected function configure(): void
    {
        $this->addOption('slug', null, 'Slug of resource to index (ignoring exclude-types option)');
        $this->addOption('ignore-descendants', null, 'Do not index descendants (applies to --slug only)');
        $this->addOption('exclude-types', null, 'Exclude document type(s) (comma-separated) from indexing');
        $this->addOption('show-types', null, 'Show available document type(s) that can be excluded');
        $this->addOption('update', null, 'Do not delete existing records before indexing');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        \sfContext::createInstance(\sfProjectConfiguration::getActive());
        \sfConfig::add(\QubitSetting::getSettingsArray());

        // If show-types flag set, show types available to index
        if ($this->hasOption('show-types')) {
            $types = array_keys(\QubitSearch::getInstance()->loadMappings()->asArray());
            sort($types);
            $this->info(sprintf('Available document types that can be excluded: %s', implode(', ', $types)));
            $this->ask('Press the Enter key to continue indexing or CTRL-C to abort...');
        }

        new \sfDatabaseManager(\sfProjectConfiguration::getActive());

        $slug = $this->option('slug');

        // Index by slug, if specified, or all indexable resources except those with an excluded type
        if ($slug) {
            $logMessage = (false !== $this->attemptIndexBySlug($slug)) ? 'Slug indexed.' : 'Slug not found.';
            $this->line($logMessage);
        } else {
            $populateOptions = [];
            $excludeTypes = $this->option('exclude-types');
            $populateOptions['excludeTypes'] = $excludeTypes ? explode(',', strtolower($excludeTypes)) : null;
            $populateOptions['update'] = $this->hasOption('update');

            \QubitSearch::getInstance()->populate($populateOptions);
        }

        return 0;
    }

    private function attemptIndexBySlug(string $slug)
    {
        if (null == $resource = \QubitObject::getBySlug($slug)) {
            return false;
        }

        if ($resource instanceof \QubitInformationObject) {
            $ignoreDescendants = $this->hasOption('ignore-descendants');

            if ($ignoreDescendants) {
                $this->line(sprintf('Indexing "%s"...', $slug));
            } else {
                $this->line(sprintf('Indexing "%s" and its descendants...', $slug));
            }

            $options = ['updateDescendants' => !$ignoreDescendants];
            \QubitSearch::getInstance()->update($resource, $options);
        } else {
            \QubitSearch::getInstance()->update($resource);
        }

        return true;
    }
}
