<?php

namespace AtomFramework\Console\Commands\PhysicalObject;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Delete physical objects that are not linked to descriptions.
 *
 * Ported from lib/task/physicalobject/physicalObjectDeleteUnlinkedTask.class.php.
 */
class PhysicalObjectDeleteUnlinkedCommand extends BaseCommand
{
    protected string $name = 'physicalobject:delete-unlinked';
    protected string $description = 'Delete physical objects not linked to descriptions';
    protected string $detailedDescription = <<<'EOF'
Delete physical objects that are not linked to any archival descriptions.
Use --force to skip confirmation, --dry-run for a preview of changes.
EOF;

    protected function configure(): void
    {
        $this->addOption('force', 'f', 'Delete without confirmation');
        $this->addOption('dry-run', 'd', 'Dry run (no database changes)');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $databaseManager = new \sfDatabaseManager(\sfProjectConfiguration::getActive());
        $databaseManager->getDatabase('propel')->getConnection();

        $force = $this->hasOption('force');
        $dryRun = $this->hasOption('dry-run');

        // Offer to abort if not using --force or --dry-run options
        if (!$force && !$dryRun) {
            if (!$this->confirm("Are you sure you'd like to delete all unlinked physical objects?")) {
                $this->line('Aborted.');

                return 0;
            }
        }

        // Disable search index
        \QubitSearch::disable();

        if ($dryRun) {
            $this->warning('DRY RUN (no changes will be made to the database)');
        }

        // Display initial count
        $physicalObjectsCountBefore = $this->getPhysicalObjectCount();
        $this->displayPhysicalObjectCount('before', $physicalObjectsCountBefore);

        // Check for unlinked physical objects
        $this->line("\nDetecting unlinked physical objects...");

        $toDelete = $this->checkPhysicalObjects();

        $this->line(sprintf(' - %d physical objects marked for deletion', count($toDelete)));

        // Delete unlinked physical objects
        $this->newline();
        $this->line('Deleting unlinked physical objects...');

        foreach ($toDelete as $id) {
            $po = \QubitPhysicalObject::getById($id);

            if ($this->verbose) {
                $description = sprintf(" - Name: '%s'", $po->getName(['cultureFallback' => true]));

                if (!empty($location = $po->getLocation(['cultureFallback' => true]))) {
                    $description .= sprintf(", Location: '%s'", $location);
                }

                $description .= sprintf(", Type: '%s'", $po->getType(['cultureFallback' => true]));

                $this->line($description);
            }

            if (!$dryRun) {
                $po->delete();
            }
        }

        $this->line(sprintf(' - %d physical objects deleted', count($toDelete)));

        // Display post-deletion count
        if (!$dryRun) {
            $physicalObjectsCountAfter = $this->getPhysicalObjectCount();
        } else {
            $physicalObjectsCountAfter = $physicalObjectsCountBefore - count($toDelete);
        }

        $this->newline();
        $this->displayPhysicalObjectCount('after', $physicalObjectsCountAfter);

        // Enable search index
        \QubitSearch::enable();

        return 0;
    }

    private function getPhysicalObjectCount(): int
    {
        $sql = 'SELECT count(*) FROM physical_object';

        return (int) \QubitPdo::fetchColumn($sql);
    }

    private function displayPhysicalObjectCount(string $stage, int $physicalObjectsCount): void
    {
        $this->line(sprintf('Data %s clean-up:', $stage));
        $this->line(sprintf(' - %d physical objects', $physicalObjectsCount));
    }

    private function checkPhysicalObjects(): array
    {
        $toDelete = [];

        $sql = 'SELECT id FROM physical_object';

        foreach (\QubitPdo::fetchAll($sql) as $physicalObject) {
            $relations = \QubitRelation::getRelationsBySubjectId($physicalObject->id, ['typeId' => \QubitTerm::HAS_PHYSICAL_OBJECT_ID]);

            $informationObjectFound = false;

            if (count($relations)) {
                foreach ($relations as $relation) {
                    if (null !== \QubitInformationObject::getById($relation->objectId)) {
                        $informationObjectFound = true;
                        break;
                    }
                }
            }

            if (!$informationObjectFound) {
                $toDelete[] = $physicalObject->id;
            }
        }

        return $toDelete;
    }
}
