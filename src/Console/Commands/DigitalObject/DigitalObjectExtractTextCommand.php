<?php

namespace AtomFramework\Console\Commands\DigitalObject;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Extract text from PDFs for search indexing.
 *
 * Ported from lib/task/digitalobject/digitalObjectExtractTextTask.class.php.
 */
class DigitalObjectExtractTextCommand extends BaseCommand
{
    protected string $name = 'digitalobject:extract-text';
    protected string $description = 'Extract text from PDFs for search indexing';
    protected string $detailedDescription = <<<'EOF'
Extract text content from PDF digital objects for search indexing.
This processes all master digital objects with mime type application/pdf.
EOF;

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $timer = new \QubitTimer();

        $databaseManager = new \sfDatabaseManager(\sfProjectConfiguration::getActive());
        $conn = $databaseManager->getDatabase('propel')->getConnection();

        $this->info('Extracting text for the digital objects...');

        // Get all master digital objects
        $query = "SELECT id FROM digital_object WHERE parent_id IS NULL AND mime_type = 'application/pdf'";

        foreach (\QubitPdo::fetchAll($query) as $item) {
            $do = \QubitDigitalObject::getById($item->id);

            if (null == $do) {
                continue;
            }

            $this->line(sprintf(
                'Extracting text for %s... (%ss)',
                $do->name,
                $timer->elapsed()
            ));

            $do->extractText($conn);
        }

        $this->success('Done extracting text for the digital objects!');

        return 0;
    }
}
