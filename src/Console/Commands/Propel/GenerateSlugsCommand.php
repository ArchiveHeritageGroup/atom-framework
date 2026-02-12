<?php

namespace AtomFramework\Console\Commands\Propel;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Generate slugs for all slug-less objects.
 *
 * Ported from lib/task/propel/propelGenerateSlugsTask.class.php.
 */
class GenerateSlugsCommand extends BaseCommand
{
    protected string $name = 'propel:generate-slugs';
    protected string $description = 'Generate slugs for all slug-less objects';
    protected string $detailedDescription = <<<'EOF'
Generate slugs for all slug-less objects. Use --delete to delete existing
slugs before generating new ones.
EOF;

    private array $slugs = [];

    protected function configure(): void
    {
        $this->addOption('delete', null, 'Delete existing slugs before generating');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $databaseManager = new \sfDatabaseManager(\sfProjectConfiguration::getActive());
        $conn = $databaseManager->getDatabase('propel')->getConnection();

        $classesData = [
            'QubitAccession' => ['select' => 'SELECT base.id, base.identifier', 'i18nQuery' => false],
            'QubitActor' => ['select' => 'SELECT base.id, i18n.authorized_form_of_name', 'i18nQuery' => true],
            'QubitDeaccession' => ['select' => 'SELECT base.id, base.identifier', 'i18nQuery' => false],
            'QubitDigitalObject' => ['select' => 'SELECT base.id, base.name', 'i18nQuery' => false],
            'QubitEvent' => ['select' => 'SELECT base.id, i18n.name', 'i18nQuery' => true],
            'QubitFunctionObject' => ['select' => 'SELECT base.id, i18n.authorized_form_of_name', 'i18nQuery' => true],
            'QubitInformationObject' => ['select' => 'SELECT base.id, i18n.title', 'i18nQuery' => true],
            'QubitPhysicalObject' => ['select' => 'SELECT base.id, i18n.name', 'i18nQuery' => true],
            'QubitRelation' => ['select' => 'SELECT base.id', 'i18nQuery' => false],
            'QubitRights' => ['select' => 'SELECT base.id', 'i18nQuery' => false],
            'QubitStaticPage' => ['select' => 'SELECT base.id, i18n.title', 'i18nQuery' => true],
            'QubitTaxonomy' => ['select' => 'SELECT base.id, i18n.name', 'i18nQuery' => true],
            'QubitTerm' => ['select' => 'SELECT base.id, i18n.name', 'i18nQuery' => true],
        ];

        // Optionally delete existing slugs
        if ($this->hasOption('delete')) {
            $reservedSlugs = ['home', 'about'];
            $privacySlug = 'privacy';

            $privacyPage = \QubitStaticPage::getBySlug($privacySlug);
            if (!empty($privacyPage)) {
                array_push($reservedSlugs, $privacySlug);
            }

            foreach ($classesData as $class => $data) {
                $table = constant($class . '::TABLE_NAME');
                $this->info("Delete {$table} slugs...");

                $sql = "DELETE FROM slug WHERE object_id IN (SELECT id FROM {$table})";

                if (defined("{$class}::ROOT_ID")) {
                    $sql .= ' AND object_id != ' . $class::ROOT_ID;
                }

                if ('QubitStaticPage' == $class) {
                    $reservedSlugsString = "'" . implode("','", $reservedSlugs) . "'";
                    $sql .= " AND slug NOT IN ({$reservedSlugsString})";
                }

                $conn->query($sql);
            }
        }

        // Create hash of slugs already in database
        $sql = 'SELECT slug FROM slug ORDER BY slug';
        foreach ($conn->query($sql, \PDO::FETCH_NUM) as $row) {
            $this->slugs[$row[0]] = true;
        }

        foreach ($classesData as $class => $data) {
            $table = constant($class . '::TABLE_NAME');

            $this->info("Generate {$table} slugs...");
            $newRows = [];

            $sql = $data['select'] . ' FROM ' . $table . ' base';

            if ($data['i18nQuery']) {
                $i18nTable = constant($class . 'I18n::TABLE_NAME');
                $sql .= ' INNER JOIN ' . $i18nTable . ' i18n';
                $sql .= '  ON base.id = i18n.id AND base.source_culture = i18n.culture';
            }

            $sql .= ' LEFT JOIN ' . \QubitSlug::TABLE_NAME . ' sl';
            $sql .= '  ON base.id = sl.object_id';
            $sql .= ' WHERE';

            if (defined("{$class}::ROOT_ID")) {
                $sql .= '  base.id != ' . $class::ROOT_ID . ' AND';
            }

            $sql .= ' sl.id is NULL';

            foreach ($conn->query($sql, \PDO::FETCH_NUM) as $row) {
                $slug = \QubitSlug::slugify($this->getStringToSlugify($row, $table));

                if (!$slug) {
                    $slug = $this->getRandomSlug();
                }

                // Truncate at 250 chars
                if (250 < strlen($slug)) {
                    $slug = substr($slug, 0, 250);
                }

                $count = 0;
                $suffix = '';

                while (isset($this->slugs[$slug . $suffix])) {
                    ++$count;
                    $suffix = '-' . $count;
                }

                $slug .= $suffix;

                $this->slugs[$slug] = true;
                $newRows[] = [$row[0], $slug];
            }

            // Do inserts
            $inc = 1000;
            for ($i = 0; $i < count($newRows); $i += $inc) {
                $values = [];
                $sql = 'INSERT INTO slug (object_id, slug) VALUES ';

                $last = min($i + $inc, count($newRows));
                for ($j = $i; $j < $last; ++$j) {
                    $sql .= '(?, ?), ';
                    array_push($values, $newRows[$j][0], $newRows[$j][1]);
                }

                $sql = substr($sql, 0, -2);
                $stmt = \QubitPdo::prepare($sql);
                $stmt->execute($values);
            }
        }

        $this->warning(
            'Note: you will need to rebuild your search index for slug changes to show up in search results.'
        );

        $this->success('Done!');

        return 0;
    }

    private function getRandomSlug(): string
    {
        $slug = \QubitSlug::random();

        while (isset($this->slugs[$slug])) {
            $slug = \QubitSlug::random();
        }

        return $slug;
    }

    private function getStringToSlugify(array $row, string $table): ?string
    {
        switch ($table) {
            case 'information_object':
                return $this->getInformationObjectStringToSlugify($row);

            default:
                return $row[1] ?? null;
        }
    }

    private function getInformationObjectStringToSlugify(array $row): ?string
    {
        switch (\sfConfig::get('app_slug_basis_informationobject', \QubitSlug::SLUG_BASIS_TITLE)) {
            case \QubitSlug::SLUG_BASIS_REFERENCE_CODE:
                return $this->getSlugStringFromES($row[0], 'referenceCode');

            case \QubitSlug::SLUG_BASIS_REFERENCE_CODE_NO_COUNTRY_REPO:
                return $this->getSlugStringFromES($row[0], 'referenceCodeWithoutCountryAndRepo');

            case \QubitSlug::SLUG_BASIS_IDENTIFIER:
                return $this->getSlugStringFromES($row[0], 'identifier');

            case \QubitSlug::SLUG_BASIS_TITLE:
                return $row[1] ?? null;

            default:
                throw new \RuntimeException('Unsupported slug basis specified in settings.');
        }
    }

    private function getSlugStringFromES(int $id, string $property): ?string
    {
        $query = new \Elastica\Query();
        $queryBool = new \Elastica\Query\BoolQuery();

        $queryBool->addMust(new \Elastica\Query\Term(['_id' => $id]));
        $query->setQuery($queryBool);
        $query->setSize(1);

        $results = \QubitSearch::getInstance()->index->getIndex('QubitInformationObject')->search($query);

        if (!$results->count()) {
            return null;
        }

        $doc = $results[0]->getData();

        if (!array_key_exists($property, $doc)) {
            throw new \RuntimeException("ElasticSearch document for information object (id: {$id}) has no property {$property}");
        }

        return $doc[$property];
    }
}
