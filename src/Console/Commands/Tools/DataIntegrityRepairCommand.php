<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Check and repair data integrity issues.
 *
 * Ported from lib/task/tools/dataIntegrityRepairTask.class.php.
 * Uses Propel/QubitPdo for complex integrity checks spanning
 * nested sets, foreign keys, and Propel object validation.
 */
class DataIntegrityRepairCommand extends BaseCommand
{
    protected string $name = 'tools:data-integrity-repair';
    protected string $description = 'Check and repair data integrity issues';
    protected string $detailedDescription = <<<'EOF'
Attempt to repair data integrity. It does the following:
- Adds missing object rows for all resources extending QubitObject
- Regenerates slugs to use them in CSV report
- Adds missing parent ids to terms
- Checks descriptions with missing data and provides options for
  attempting to generate a list, fix them, or delete them
- Re-builds the nested sets

Usage:
    php bin/atom tools:data-integrity-repair report.csv
    php bin/atom tools:data-integrity-repair report.csv --mode=delete
    php bin/atom tools:data-integrity-repair report.csv --mode=fix
EOF;

    protected function configure(): void
    {
        $this->addArgument('filename', 'A filepath (ending in .csv) for the generated CSV report file');
        $this->addOption('mode', null, 'Mode: report (default), fix, or delete', 'report');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $filename = $this->argument('filename', 'affected-records.csv');
        $mode = $this->option('mode', 'report');

        \QubitSearch::disable();

        $this->info("Adding missing object rows (except for descriptions):\n");

        // List of classes with a related object row
        $classes = [
            'QubitRepository',
            'QubitRightsHolder',
            'QubitUser',
            'QubitDonor',
            'QubitActor',
            'QubitAip',
            'QubitJob',
            'QubitDigitalObject',
            'QubitEvent',
            'QubitFunctionObject',
            'QubitObjectTermRelation',
            'QubitPhysicalObject',
            'QubitPremisObject',
            'QubitRelation',
            'QubitRights',
            'QubitRightsHolder',
            'QubitStaticPage',
            'QubitTaxonomy',
            'QubitTerm',
            'QubitAccession',
            'QubitDeaccession',
        ];

        foreach ($classes as $class) {
            $fixed = 0;

            // Find resources without object row
            $sql = 'SELECT tb.id
                FROM ' . constant("\\{$class}::TABLE_NAME") . ' tb
                LEFT JOIN object o ON tb.id=o.id
                WHERE o.id IS NULL;';
            $noObjectIds = \QubitPdo::fetchAll(
                $sql,
                [],
                ['fetchMode' => \PDO::FETCH_COLUMN]
            );

            foreach ($noObjectIds as $id) {
                $this->insertObjectRow($id, $class);
                ++$fixed;
            }

            $this->line(sprintf("  - %s: %d", $class, $fixed));
        }

        $this->info("\nRegenerating slugs ...\n");

        // Run slug regeneration via Symfony task
        $this->passthru(sprintf('php %s/symfony propel:generate-slugs', escapeshellarg($this->atomRoot)));

        // Set root term as parent for terms without one
        $sql = 'UPDATE term SET parent_id=110 WHERE parent_id IS NULL AND id<>110;';
        $updated = \QubitPdo::modify($sql);
        $this->line(sprintf("Updating terms without parent id: %d", $updated));

        $this->info("\nChecking descriptions integrity:\n");

        $sql = 'SELECT COUNT(io.id)
            FROM information_object io
            LEFT JOIN object o ON io.id=o.id
            WHERE io.id<>1
            AND o.id IS NULL;';
        $this->line(sprintf("  - Descriptions without object row: %d", \QubitPdo::fetchColumn($sql)));

        $sql = 'SELECT COUNT(id)
            FROM information_object
            WHERE id<>1
            AND parent_id IS NULL;';
        $this->line(sprintf("  - Descriptions without parent id: %d", \QubitPdo::fetchColumn($sql)));

        $sql = 'SELECT COUNT(io.id)
            FROM information_object io
            LEFT JOIN information_object p ON io.parent_id=p.id
            WHERE io.id<>1
            AND p.id IS NULL;';
        $this->line(sprintf("  - Descriptions without parent: %d", \QubitPdo::fetchColumn($sql)));

        $sql = 'SELECT COUNT(io.id)
            FROM information_object io
            LEFT JOIN status st ON io.id=st.object_id AND st.type_id=158
            WHERE io.id<>1
            AND st.status_id IS NULL;';
        $this->line(sprintf("  - Descriptions without publication status: %d", \QubitPdo::fetchColumn($sql)));

        $sql = 'SELECT COUNT(o.id)
            FROM information_object_i18n o
            WHERE o.id<>1
            AND coalesce(o.access_conditions, o.accruals, o.acquisition, o.alternate_title, o.appraisal, o.archival_history, o.arrangement, o.edition, o.extent_and_medium, o.finding_aids, o.location_of_copies, o.location_of_originals, o.physical_characteristics, o.related_units_of_description, o.reproduction_conditions, o.revision_history, o.rules, o.scope_and_content, o.sources, o.title) IS NULL;';
        $this->line(sprintf("  - Descriptions with all fields NULL: %d", \QubitPdo::fetchColumn($sql)));

        $sql = 'SELECT io.id, o.id as object_id, io.parent_id, p.id as parent, st.id as status, st.status_id
            FROM information_object io
            LEFT JOIN object o ON io.id=o.id
            LEFT JOIN information_object p ON io.parent_id=p.id
            LEFT JOIN status st ON io.id=st.object_id AND st.type_id=158
            INNER JOIN information_object_i18n i18n ON o.id=i18n.id
            WHERE io.id<>1
            AND (o.id IS NULL OR io.parent_id IS NULL
            OR p.id IS NULL
            OR st.id IS NULL
            OR st.status_id IS NULL
            OR coalesce(i18n.access_conditions, i18n.accruals, i18n.acquisition, i18n.alternate_title, i18n.appraisal, i18n.archival_history, i18n.arrangement, i18n.edition, i18n.extent_and_medium, i18n.finding_aids, i18n.location_of_copies, i18n.location_of_originals, i18n.physical_characteristics, i18n.related_units_of_description, i18n.reproduction_conditions, i18n.revision_history, i18n.rules, i18n.scope_and_content, i18n.sources, i18n.title) IS NULL);';
        $affectedIos = \QubitPdo::fetchAll($sql, [], ['fetchMode' => \PDO::FETCH_ASSOC]);
        $this->line(sprintf("  - Affected descriptions: %d", count($affectedIos)));

        $this->info("\nChecking for invalid descriptions:\n");

        $sql = 'SELECT s.object_id, s.slug
            FROM slug s
            INNER JOIN object o
            ON o.id = s.object_id
            WHERE class_name="QubitInformationObject"
            AND object_id NOT IN (SELECT id FROM information_object);';
        $invalidIos = \QubitPdo::fetchAll($sql, [], ['fetchMode' => \PDO::FETCH_ASSOC]);
        $this->line(sprintf("  - Invalid descriptions: %d", count($invalidIos)));

        if (0 == count($affectedIos) && 0 == count($invalidIos)) {
            $this->success("All descriptions seem to be okay.");
        } else {
            $affectedIosAndDescendantIds = [];
            $affectedIosById = [];
            foreach (array_reverse($affectedIos) as $io) {
                $this->populateAffectedIosAndDescendantIds($io['id'], $affectedIosAndDescendantIds);
                $affectedIosById[$io['id']] = $io;
            }
            $this->line(sprintf("  - Affected descriptions (including descendants): %d", count($affectedIosAndDescendantIds)));

            $this->report($filename, $affectedIosById, $affectedIosAndDescendantIds, $invalidIos);

            switch ($mode) {
                case 'fix':
                    $this->fix($affectedIosById);
                    break;

                case 'delete':
                    $this->deleteDescriptions($affectedIosById, $affectedIosAndDescendantIds, $invalidIos);
                    break;
            }
        }

        $this->info("\nRebuilding nested set ...\n");

        $this->passthru(sprintf('php %s/symfony propel:build-nested-set', escapeshellarg($this->atomRoot)));

        $this->warning("The ES index has not been updated! Run the search:populate task to do so.");

        return 0;
    }

    private function insertObjectRow(int $id, string $class): void
    {
        $sql = 'INSERT INTO object
            (id, class_name, created_at, updated_at, serial_number)
            VALUES
            (:id, :class, now(), now(), 0);';
        \QubitPdo::modify($sql, [':id' => $id, ':class' => $class]);
    }

    private function populateAffectedIosAndDescendantIds(int $id, array &$affectedIosAndDescendantIds): void
    {
        // Skip already added IOs
        if (in_array($id, $affectedIosAndDescendantIds)) {
            return;
        }

        // Find children
        $sql = 'SELECT id FROM information_object WHERE parent_id=:id;';
        $children = \QubitPdo::fetchAll($sql, [':id' => $id], ['fetchMode' => \PDO::FETCH_COLUMN]);

        // Add descendants first
        foreach ($children as $childId) {
            $this->populateAffectedIosAndDescendantIds($childId, $affectedIosAndDescendantIds);
        }

        $affectedIosAndDescendantIds[] = $id;
    }

    private function report(string $filename, array $affectedIosById, array $affectedIosAndDescendantIds, array $invalidIos): void
    {
        $csvFile = fopen($filename, 'w');
        fputcsv($csvFile, ['id', 'parent_id', 'slug', 'issue(s)']);

        if (count($invalidIos) > 0) {
            foreach ($invalidIos as $io) {
                $details = [];
                $details[] = $io['object_id'];
                $details[] = 'parent not set';
                $details[] = $io['slug'];
                $details[] = 'invalid description entry';
                fputcsv($csvFile, $details);
            }
        }

        // Reverse IOs to show ancestors first on the report
        foreach (array_reverse($affectedIosAndDescendantIds) as $id) {
            // Get current IO data
            $sql = 'SELECT io.id, io.parent_id, slug
                FROM information_object io
                LEFT JOIN slug ON io.id=slug.object_id
                WHERE io.id=:id;';
            $stmt = \QubitPdo::prepareAndExecute($sql, [':id' => $id]);
            $result = $stmt->fetch(\PDO::FETCH_NUM);

            // Check issues
            $issues = [];
            if (isset($affectedIosById[$id])) {
                $flag = false;
                if (!isset($affectedIosById[$id]['object_id'])) {
                    $issues[] = 'missing object row';
                    $flag = true;
                }
                if (!isset($affectedIosById[$id]['parent'])) {
                    $issues[] = 'parent does not exist';
                    $flag = true;
                }
                if (!isset($affectedIosById[$id]['parent_id'])) {
                    $issues[] = 'parent not set';
                    $flag = true;
                }
                if (!isset($affectedIosById[$id]['status_id']) || !isset($affectedIosById[$id]['status'])) {
                    $issues[] = 'missing publication status';
                    $flag = true;
                }

                // If none of the above issues has been flagged, this is an empty IO
                if (!$flag) {
                    $issues[] = 'empty information object with no data';
                }
            } else {
                $issues[] = 'descendant';
            }

            $result[] = implode(' | ', $issues);
            fputcsv($csvFile, $result);
        }

        fclose($csvFile);
        $this->success(sprintf("CSV generated: '%s'.", $filename));
    }

    private function fix(array $affectedIosById): void
    {
        $count = 0;
        $this->info("Fixing descriptions ...\n");

        foreach ($affectedIosById as $id => $io) {
            // Fix missing object row
            if (!isset($io['object_id'])) {
                $this->insertObjectRow($id, 'QubitInformationObject');
            }

            // Set root IO as parent
            if (!isset($io['parent']) || !isset($io['parent_id'])) {
                $sql = 'UPDATE information_object SET parent_id=1 WHERE id=:id;';
                \QubitPdo::modify($sql, [':id' => $id]);
            }

            // Add publication status row
            if (!isset($io['status'])) {
                $sql = "INSERT INTO status
                    (object_id, type_id, status_id, serial_number)
                    VALUES (:id, '158', '159', '0');";
                \QubitPdo::modify($sql, [':id' => $id]);
            } elseif (!isset($io['status_id'])) {
                // Set publication status to draft
                $sql = 'UPDATE status SET status_id=159 WHERE type_id=158 AND object_id=:id;';
                \QubitPdo::modify($sql, [':id' => $id]);
            }

            ++$count;
            if (0 == $count % 100) {
                $this->line(sprintf("%d descriptions fixed ...", $count));
            }
        }

        $this->success(sprintf("%d descriptions fixed.", count($affectedIosById)));
    }

    private function deleteDescriptions(array $affectedIosById, array $affectedIosAndDescendantIds, array $invalidIos): void
    {
        $count = 0;
        $this->info("Deleting descriptions ...\n");

        if (count($invalidIos) > 0) {
            $sql = 'DELETE FROM object
                WHERE id IN (
                    SELECT * FROM (
                        SELECT object_id
                        FROM slug s
                        INNER JOIN object o
                        ON o.id = s.object_id
                        WHERE class_name="QubitInformationObject"
                        AND object_id NOT IN (SELECT id FROM information_object)
                    ) as temp
                );';
            \QubitPdo::prepareAndExecute($sql);
            $count = count($invalidIos);
            if (0 == $count % 100) {
                $this->line(sprintf("%d descriptions deleted ...", $count));
            }
        }

        // Description trees are already flattened and reversed to avoid foreign key issues
        foreach ($affectedIosAndDescendantIds as $id) {
            // Fix object row if needed
            if (isset($affectedIosById[$id]) && !isset($affectedIosById[$id]['object_id'])) {
                $this->insertObjectRow($id, 'QubitInformationObject');
            }

            // Delete IO without updating nested set
            $io = \QubitInformationObject::getById($id);
            $io->disableNestedSetUpdating = true;
            $io->delete();

            // Avoid high memory usage
            \Qubit::clearClassCaches();

            ++$count;
            if (0 == $count % 100) {
                $this->line(sprintf("%d descriptions deleted ...", $count));
            }
        }

        $this->success(sprintf("%d descriptions deleted.", count($affectedIosAndDescendantIds)));
    }
}
