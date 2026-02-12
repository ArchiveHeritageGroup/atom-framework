<?php

namespace AtomFramework\Console\Commands\Search;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Display search index status.
 *
 * Ported from lib/task/search/arSearchStatusTask.class.php.
 */
class StatusCommand extends BaseCommand
{
    protected string $name = 'search:status';
    protected string $description = 'Show search index status';
    protected string $detailedDescription = <<<'EOF'
Display the status of search indexing for each document type, including
the number of indexed documents versus the number available to index.
EOF;

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $databaseManager = new \sfDatabaseManager(\sfProjectConfiguration::getActive());
        $databaseManager->getDatabase('propel')->getConnection();

        // Display Elasticsearch server configuration
        $config = \arElasticSearchPluginConfiguration::$config;

        $this->bold('Elasticsearch server information:');
        $this->line(sprintf(' - Version: %s', \QubitSearch::getInstance()->client->getVersion()));
        $this->line(sprintf(' - Host: %s', $config['server']['host']));
        $this->line(sprintf(' - Port: %s', $config['server']['port']));
        $this->line(sprintf(' - Index name: %s', $config['index']['name']));
        $this->newline();

        // Display how many objects are indexed versus how many are available
        $this->bold('Document indexing status:');

        $types = array_keys(\QubitSearch::getInstance()->loadMappings()->asArray());
        sort($types);

        foreach ($types as $docType) {
            $docTypeDescription = \sfInflector::humanize(\sfInflector::underscore($docType));

            $docTypeIndexedCount = $this->objectsIndexed($docType);
            $docTypeAvailableCount = $this->objectsAvailableToIndex($docType);

            $this->line(sprintf(' - %s: %d/%d', $docTypeDescription, $docTypeIndexedCount, $docTypeAvailableCount));
        }

        return 0;
    }

    private function objectsIndexed(string $docType): int
    {
        $docTypeModelClass = 'Qubit' . ucfirst($docType);

        return \QubitSearch::getInstance()->index->getIndex($docTypeModelClass)->count();
    }

    private function objectsAvailableToIndex(string $docType): int
    {
        $docTypeSearchModelClass = 'arElasticSearch' . ucwords($docType);

        $docTypeInstance = new $docTypeSearchModelClass();
        $docTypeInstance->load();

        return $docTypeInstance->getCount();
    }
}
