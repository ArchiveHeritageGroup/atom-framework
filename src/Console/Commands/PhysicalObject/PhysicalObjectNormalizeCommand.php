<?php

namespace AtomFramework\Console\Commands\PhysicalObject;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Normalize physical object data.
 *
 * Ported from lib/task/physicalobject/physicalObjectNormalizeTask.class.php.
 */
class PhysicalObjectNormalizeCommand extends BaseCommand
{
    protected string $name = 'physicalobject:normalize';
    protected string $description = 'Normalize physical object data';
    protected string $detailedDescription = <<<'EOF'
Normalize physical object data by merging duplicates. Duplicates are identified
by name, location, and type (or name only with --name-only).
Use --force to skip confirmation, --dry-run for a preview of changes.
EOF;

    private array $toDelete = [];
    private int $relationsUpdated = 0;

    protected function configure(): void
    {
        $this->addOption('name-only', null, 'Normalize using physical object name only');
        $this->addOption('force', 'f', 'Normalize without confirmation');
        $this->addOption('dry-run', 'd', 'Dry run (no database changes)');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $databaseManager = new \sfDatabaseManager(\sfProjectConfiguration::getActive());
        $databaseManager->getDatabase('propel')->getConnection();

        $force = $this->hasOption('force');
        $dryRun = $this->hasOption('dry-run');
        $nameOnly = $this->hasOption('name-only');

        if (!$force && !$dryRun) {
            if (!$this->confirm("Are you sure you'd like to normalize physical object data?")) {
                $this->line('Aborted.');

                return 0;
            }
        }

        \QubitSearch::disable();

        if ($dryRun) {
            $this->warning('DRY RUN (no changes will be made to the database)');
        }

        $physicalObjectsCountBefore = $this->getPhysicalObjectCount();
        $relationsCountBefore = $this->getPhysicalObjectRelationCount();

        $this->displayCounts('before', $physicalObjectsCountBefore, $relationsCountBefore);

        $this->line('Detecting duplicates and updating relations to duplicates...');

        if ($nameOnly) {
            $this->line('(Using physical object name only as the basis for duplicate detection.)');
            $this->checkAllByNameOnlyAndNormalize($dryRun);
        } else {
            $this->line('(Using physical object name, location, and type as the basis for duplicate detection.)');
            $this->checkWithLocationsAndNormalize($dryRun);
            $this->checkWithoutLocationsAndNormalize($dryRun);
        }

        $this->line(sprintf(' - %d relations updated', $this->relationsUpdated));

        $this->line('Deleting duplicates...');

        foreach ($this->toDelete as $id) {
            $po = \QubitPhysicalObject::getById($id);

            if ($this->verbose) {
                $this->line(sprintf(' - %s', $this->describePhysicalObject($po)));
            }

            if (!$dryRun) {
                $po->delete();
            }
        }

        $this->line(sprintf(' - %d duplicates deleted', count($this->toDelete)));

        if (!$dryRun) {
            $physicalObjectsCountAfter = $this->getPhysicalObjectCount();
            $relationsCountAfter = $this->getPhysicalObjectRelationCount();
        } else {
            $physicalObjectsCountAfter = $physicalObjectsCountBefore - count($this->toDelete);
            $relationsCountAfter = $relationsCountBefore;
        }

        $this->displayCounts('after', $physicalObjectsCountAfter, $relationsCountAfter);

        if (
            $relationsCountBefore == $relationsCountAfter
            && (count($this->toDelete) + $physicalObjectsCountAfter) == $physicalObjectsCountBefore
        ) {
            $this->success('Normalization completed successfully.');
        } else {
            $this->error('Error: final physical object count is unexpected.');
        }

        \QubitSearch::enable();

        return 0;
    }

    private function getPhysicalObjectCount(): int
    {
        return (int) \QubitPdo::fetchColumn('SELECT count(*) FROM physical_object');
    }

    private function getPhysicalObjectRelationCount(): int
    {
        return (int) \QubitPdo::fetchColumn('SELECT count(*) FROM relation WHERE type_id=?', [\QubitTerm::HAS_PHYSICAL_OBJECT_ID]);
    }

    private function displayCounts(string $stage, int $poCount, int $relCount): void
    {
        $this->line(sprintf('Data %s clean-up:', $stage));
        $this->line(sprintf(' - %d physical objects', $poCount));
        $this->line(sprintf(' - %d physical object relations', $relCount));
    }

    private function sqlForPhysicalObjectsBySourceCulture(): string
    {
        return 'SELECT p.id, p.type_id, pi.name, pi.location
            FROM physical_object p
            INNER JOIN physical_object_i18n pi
            ON p.id=pi.id AND p.source_culture=pi.culture';
    }

    private function checkAllByNameOnlyAndNormalize(bool $dryRun): void
    {
        $sql = $this->sqlForPhysicalObjectsBySourceCulture() . ' WHERE pi.name IS NOT NULL';

        foreach (\QubitPdo::fetchAll($sql) as $physicalObject) {
            if (in_array($physicalObject->id, $this->toDelete)) {
                continue;
            }

            $dupSql = $this->sqlForPhysicalObjectsBySourceCulture() . ' WHERE pi.name=:name';
            $params = [':name' => $physicalObject->name];

            $this->findAndMarkDuplicates($dupSql, $params, $physicalObject->id, $dryRun);
        }
    }

    private function checkWithLocationsAndNormalize(bool $dryRun): void
    {
        $sql = $this->sqlForPhysicalObjectsBySourceCulture() . ' WHERE pi.name IS NOT NULL AND pi.location IS NOT NULL';

        foreach (\QubitPdo::fetchAll($sql) as $physicalObject) {
            if (in_array($physicalObject->id, $this->toDelete)) {
                continue;
            }

            $dupSql = $this->sqlForPhysicalObjectsBySourceCulture() . ' WHERE p.type_id=:type_id AND pi.name=:name AND pi.location=:location';
            $params = [
                ':type_id' => $physicalObject->type_id,
                ':name' => $physicalObject->name,
                ':location' => $physicalObject->location,
            ];

            $this->findAndMarkDuplicates($dupSql, $params, $physicalObject->id, $dryRun);
        }
    }

    private function checkWithoutLocationsAndNormalize(bool $dryRun): void
    {
        $sql = $this->sqlForPhysicalObjectsBySourceCulture() . ' WHERE pi.name IS NOT NULL AND pi.location IS NULL';

        foreach (\QubitPdo::fetchAll($sql) as $physicalObject) {
            if (in_array($physicalObject->id, $this->toDelete)) {
                continue;
            }

            $dupSql = $this->sqlForPhysicalObjectsBySourceCulture() . ' WHERE pi.name=:name AND pi.location IS NULL';
            $params = [':name' => $physicalObject->name];

            $this->findAndMarkDuplicates($dupSql, $params, $physicalObject->id, $dryRun);
        }
    }

    private function findAndMarkDuplicates(string $sql, array $params, int $physicalObjectId, bool $dryRun): void
    {
        foreach (\QubitPdo::fetchAll($sql, $params) as $duplicate) {
            if ($duplicate->id == $physicalObjectId || in_array($duplicate->id, $this->toDelete)) {
                continue;
            }

            $relations = \QubitRelation::getRelationsBySubjectId($duplicate->id, ['typeId' => \QubitTerm::HAS_PHYSICAL_OBJECT_ID]);

            foreach ($relations as $relation) {
                if (!$dryRun) {
                    $relation->indexOnSave = false;
                    $relation->subjectId = $physicalObjectId;
                    $relation->save();
                }

                ++$this->relationsUpdated;
            }

            $this->toDelete[] = $duplicate->id;
        }
    }

    private function describePhysicalObject($physicalObject): string
    {
        $po = \QubitPhysicalObject::getById($physicalObject->id);

        $description = sprintf("Name: '%s'", $po->getName(['cultureFallback' => true]));

        if (!empty($location = $po->getLocation(['cultureFallback' => true]))) {
            $description .= sprintf(", Location: '%s'", $location);
        }

        $description .= sprintf(", Type: '%s'", $po->getType(['cultureFallback' => true]));

        return $description;
    }
}
