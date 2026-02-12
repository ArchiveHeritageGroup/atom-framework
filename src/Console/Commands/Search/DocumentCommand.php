<?php

namespace AtomFramework\Console\Commands\Search;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Print indexing configuration / document data.
 *
 * Ported from lib/task/search/arDocumentTask.class.php.
 */
class DocumentCommand extends BaseCommand
{
    protected string $name = 'search:document';
    protected string $description = 'Print indexing configuration';
    protected string $detailedDescription = <<<'EOF'
Output search index document data corresponding to an AtoM resource.
Provide the slug of the resource to display its indexed document data.
EOF;

    protected function configure(): void
    {
        $this->addArgument('slug', 'Slug of the resource', true);
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $slug = $this->argument('slug');

        $databaseManager = new \sfDatabaseManager(\sfProjectConfiguration::getActive());
        $databaseManager->getDatabase('propel')->getConnection();

        if (null !== $slugObject = \QubitObject::getBySlug($slug)) {
            $this->line(sprintf("Fetching data for %s ID %d...\n", $slugObject->className, $slugObject->id));

            $doc = \QubitSearch::getInstance()->index->getIndex($slugObject->className)->getDocument($slugObject->id);

            echo json_encode($doc->getData(), JSON_PRETTY_PRINT) . "\n";
        } else {
            $this->error('Slug not found');

            return 1;
        }

        return 0;
    }
}
