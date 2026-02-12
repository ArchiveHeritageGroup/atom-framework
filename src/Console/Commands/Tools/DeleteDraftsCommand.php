<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Delete all draft descriptions.
 *
 * Ported from lib/task/tools/deleteDraftsTask.class.php.
 * Uses Propel for complex publication status queries and nested set cleanup.
 */
class DeleteDraftsCommand extends BaseCommand
{
    protected string $name = 'tools:delete-drafts';
    protected string $description = 'Delete all draft archival descriptions';
    protected string $detailedDescription = <<<'EOF'
Delete all information objects with publication status: DRAFT.

This will permanently remove all draft descriptions and their descendants
from the database. Use with extreme caution.
EOF;

    protected function configure(): void
    {
        $this->addOption('no-confirmation', 'B', 'Do not ask for confirmation');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $conn = \Propel::getConnection();

        $sqlQuery = 'SELECT s.object_id FROM information_object i JOIN status s ON i.id = s.object_id '
            . 'WHERE s.type_id = ' . \QubitTerm::STATUS_TYPE_PUBLICATION_ID
            . ' AND s.status_id = ' . \QubitTerm::PUBLICATION_STATUS_DRAFT_ID
            . ' AND i.id <> 1';

        $this->info('Deleting all information objects marked as draft...');

        // Confirmation
        if (!$this->hasOption('no-confirmation') && !$this->confirm('Are you SURE you want to do this?')) {
            return 1;
        }

        $n = 0;
        $stmt = $conn->query($sqlQuery);

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $id = $row['object_id'];
            $resource = \QubitInformationObject::getById($id);

            if (!$resource) {
                continue;
            }

            foreach ($resource->descendants->andSelf()->orderBy('rgt') as $item) {
                try {
                    $item->delete();
                } catch (\Exception $e) {
                    $this->warning('Got error while deleting: ' . $e->getMessage());
                }

                if (0 == ++$n % 10) {
                    echo '.';
                    fflush(STDOUT);
                }
            }
        }

        echo "\n";
        $this->success("Finished! {$n} items deleted.");

        return 0;
    }
}
