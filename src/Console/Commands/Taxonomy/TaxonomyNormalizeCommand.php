<?php

namespace AtomFramework\Console\Commands\Taxonomy;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Normalize taxonomy terms.
 *
 * Ported from lib/task/taxonomy/taxonomyNormalizeTask.class.php.
 */
class TaxonomyNormalizeCommand extends BaseCommand
{
    protected string $name = 'taxonomy:normalize';
    protected string $description = 'Normalize taxonomy terms';
    protected string $detailedDescription = <<<'EOF'
Normalize taxonomy terms by merging duplicate term names within a given
taxonomy. Use --culture to specify the culture to normalize (defaults to "en").
EOF;

    private int $taxonomyId;

    protected function configure(): void
    {
        $this->addArgument('taxonomy-name', 'The name of the taxonomy to normalize', true);
        $this->addOption('culture', null, 'The culture to normalize (defaults to "en")', 'en');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $databaseManager = new \sfDatabaseManager(\sfProjectConfiguration::getActive());
        $databaseManager->getDatabase('propel')->getConnection();

        $taxonomyName = $this->argument('taxonomy-name');
        $culture = $this->option('culture') ?? 'en';

        // Look up taxonomy ID using name
        $this->taxonomyId = $this->getTaxonomyIdByName($taxonomyName, $culture);
        if (!$this->taxonomyId) {
            $this->error("A taxonomy named '" . $taxonomyName . "' not found for culture '" . $culture . "'.");

            return 1;
        }

        $this->info("Normalizing for '" . $culture . "' culture...");

        // Determine taxonomy term usage then normalize
        $names = [];
        $affectedObjects = [];
        $this->populateTaxonomyNameUsage($names, $culture);
        $this->normalizeTaxonomy($names, $affectedObjects);
        $this->reindexAffectedObjects($affectedObjects);

        $this->success('Affected objects have been reindexed.');

        return 0;
    }

    private function getTaxonomyIdByName(string $name, string $culture): int|false
    {
        $sql = "SELECT id FROM taxonomy_i18n
            WHERE culture=?
            AND name=?";

        $statement = \QubitFlatfileImport::sqlQuery($sql, [$culture, $name]);

        if ($object = $statement->fetch(\PDO::FETCH_OBJ)) {
            return $object->id;
        }

        return false;
    }

    private function populateTaxonomyNameUsage(array &$names, string $culture): void
    {
        $sql = 'SELECT t.id, i.name FROM term t
            INNER JOIN term_i18n i ON t.id=i.id
            WHERE t.taxonomy_id=:id AND i.culture=:culture
            ORDER BY t.id';

        $params = [':id' => $this->taxonomyId, ':culture' => $culture];

        $terms = \QubitPdo::fetchAll($sql, $params, ['fetchMode' => \PDO::FETCH_OBJ]);

        foreach ($terms as $term) {
            if (!isset($names[$term->name])) {
                $names[$term->name] = [];
            }

            array_push($names[$term->name], $term->id);
        }
    }

    private function normalizeTaxonomy(array $names, array &$affectedObjects): void
    {
        foreach ($names as $name => $usage) {
            if (count($usage) > 1) {
                $this->normalizeTaxonomyTerm($name, $usage, $affectedObjects);
            }
        }
    }

    private function normalizeTaxonomyTerm(string $name, array $usage, array &$affectedObjects): void
    {
        $selected_id = array_shift($usage);

        $this->line("Normalizing terms with name '" . $name . "'...");

        foreach ($usage as $id) {
            $sql = 'select object_id from object_term_relation where term_id=?';
            $statement = \QubitFlatfileImport::sqlQuery($sql, [$id]);
            while ($object = $statement->fetch(\PDO::FETCH_OBJ)) {
                $affectedObjects[] = $object->object_id;
            }

            $this->line('Changing object term relations from term ' . $id . ' to ' . $selected_id . '.');

            $sql = 'UPDATE object_term_relation SET term_id=:newId WHERE term_id=:oldId';
            $params = [':newId' => $selected_id, ':oldId' => $id];
            \QubitPdo::modify($sql, $params);

            if (\QubitTaxonomy::LEVEL_OF_DESCRIPTION_ID == $this->taxonomyId) {
                $this->line('Changing level of descriptions from term ' . $id . ' to ' . $selected_id . '.');

                $sql = 'UPDATE information_object SET level_of_description_id=:newId WHERE level_of_description_id=:oldId';
                \QubitPdo::modify($sql, $params);
            }

            $this->line('Deleting term ID ' . $id . '.');

            $term = \QubitTerm::getById($id);
            $term->delete();
        }
    }

    private function reindexAffectedObjects(array $affectedObjects): void
    {
        $search = \QubitSearch::getInstance();
        foreach ($affectedObjects as $id) {
            $o = \QubitInformationObject::getById($id);
            if (null !== $o) {
                $search->update($o);
            }
        }
    }
}
