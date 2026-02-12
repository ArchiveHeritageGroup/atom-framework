<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\BaseCommand;

/**
 * Import CSV event record data.
 *
 * Native implementation of the csv:event-import Symfony task.
 */
class CsvEventImportCommand extends BaseCommand
{
    protected string $name = 'import:csv-event';
    protected string $description = 'Import events from CSV';

    protected string $detailedDescription = <<<'EOF'
    Import CSV event record data into AtoM. Creates event records linking actors
    to information objects using keymap source IDs. Supports caching of actor and
    object lookups for performance.
    EOF;

    protected function configure(): void
    {
        $this->addArgument('filename', 'The input file (CSV format)', true);

        $this->addOption('rows-until-update', null, 'Output total rows imported every n rows');
        $this->addOption('skip-rows', null, 'Skip n rows before importing');
        $this->addOption('error-log', null, 'File to log errors to');
        $this->addOption('event-types', null, 'Event type terms to create, if they do not yet exist, before import');
        $this->addOption('source-name', null, 'Source name to use when inserting keymap entries');
        $this->addOption('index', null, 'Index for search during import');
        $this->addOption('update', null, 'Attempt to update if record already exists. Valid values: "match-and-update", "delete-and-replace"');
        $this->addOption('skip-matched', null, 'Skip creating new records when existing one matches (without --update)');
        $this->addOption('skip-unmatched', null, 'Skip creating new records if no existing records match (with --update)');
    }

    protected function handle(): int
    {
        $filename = $this->argument('filename');

        $skipRows = ($this->option('skip-rows')) ? $this->option('skip-rows') : 0;

        $sourceName = ($this->option('source-name'))
            ? $this->option('source-name')
            : false;

        if (false === $fh = fopen($filename, 'rb')) {
            $this->error('You must specify a valid filename');

            return 1;
        }

        // Load taxonomies into variables to avoid use of magic numbers
        $termData = \QubitFlatfileImport::loadTermsFromTaxonomies([
            \QubitTaxonomy::EVENT_TYPE_ID => 'eventTypes',
        ]);

        $subjectTable = 'actor_i18n';
        $subjectKeyColumn = 'authorized_form_of_name';
        $subjectValueColumn = 'eventActorName';
        $subjectIdColumn = 'id';

        $objectTable = 'keymap';
        $objectKeyColumn = 'source_id';
        $objectValueColumn = 'legacyId';
        $objectIdColumn = 'target_id';

        $relationTypeColumn = 'eventType';

        $import = new \QubitFlatfileImport([
            'context' => \sfContext::getInstance(),

            'status' => [
                'sourceName' => $sourceName,
                'eventTypes' => $termData['eventTypes'],
                'subjectTable' => $subjectTable,
                'subjectKeyColumn' => $subjectKeyColumn,
                'subjectValueColumn' => $subjectValueColumn,
                'subjectIdColumn' => $subjectIdColumn,
                'objectTable' => $objectTable,
                'objectKeyColumn' => $objectKeyColumn,
                'objectValueColumn' => $objectValueColumn,
                'objectIdColumn' => $objectIdColumn,
                'relationTypeColumn' => $relationTypeColumn,
                'dataCached' => false,
                'subjectKeys' => [],
                'objectKeys' => [],
                'goodSubjects' => 0,
                'badSubjects' => 0,
                'goodObjects' => 0,
                'badObjects' => 0,
            ],

            'errorLog' => $this->option('error-log'),

            'saveLogic' => function (&$self) {
                if (!$self->status['dataCached']) {
                    // Cache key -> id associations
                    $self->status['subjectKeys'] = self::getNameIdArrayFromTable(
                        $self,
                        $self->status['subjectTable'],
                        $self->status['subjectKeyColumn'],
                        $self->status['subjectIdColumn']
                    );

                    $whereClause = ($self->status['sourceName'] && 'keymap' == $self->status['objectTable'])
                        ? 'source_name = "' . $self->status['sourceName'] . '"'
                        : false;

                    $self->status['objectKeys'] = self::getNameIdArrayFromTable(
                        $self,
                        $self->status['objectTable'],
                        $self->status['objectKeyColumn'],
                        $self->status['objectIdColumn'],
                        $whereClause
                    );

                    $self->status['dataCached'] = true;
                }

                // Attempt to use pre-cached actor ID
                $subjectKey = trim($self->columnValue($self->status['subjectValueColumn']));
                $subjectId = false;
                if ($subjectKey) {
                    if (isset($self->status['subjectKeys'][$subjectKey])) {
                        $subjectId = $self->status['subjectKeys'][$subjectKey];
                    }
                }

                // If actor ID not found, create
                if (!$subjectId) {
                    $actor = $self->createOrFetchActor($subjectKey);
                    $subjectId = $actor->id;
                }

                if ($subjectId) {
                    ++$self->status['goodSubjects'];

                    $objectKey = trim($self->columnValue($self->status['objectValueColumn']));
                    $objectId = false;
                    if ($objectKey) {
                        if (isset($self->status['objectKeys'][$objectKey])) {
                            $objectId = $self->status['objectKeys'][$objectKey];
                        }
                    }

                    if ($objectId) {
                        ++$self->status['goodObjects'];

                        $type = $self->columnValue($self->status['relationTypeColumn']);
                        echo 'Relate ' . $subjectId . ' to ' . $objectId . ' as ' . $type . ".\n";

                        $typeId = array_search($type, $self->status['eventTypes'][$self->columnValue('culture')]);

                        if (!$typeId) {
                            echo "Term does not exist... adding.\n";
                            $term = \QubitTerm::createTerm(
                                \QubitTaxonomy::EVENT_TYPE_ID,
                                $type,
                                $self->columnValue('culture')
                            );
                            $typeId = $term->id;
                            $self->status['eventTypes'][$typeId] = $type;
                        }

                        $event = new \QubitEvent();
                        $event->objectId = $objectId;
                        $event->typeId = $typeId;
                        $event->actorId = $subjectId;
                        $event->save();
                    } else {
                        ++$self->status['badObjects'];
                        echo 'ERROR: object ' . $objectKey . " not found.\n";
                    }
                } else {
                    ++$self->status['badSubjects'];
                    echo 'ERROR: subject ' . $subjectKey . " not found.\n";
                }
            },

            'completeLogic' => function (&$self) {
                echo "Import complete.\n";
                echo 'Good subjects: ' . $self->status['goodSubjects'] . "\n";
                echo 'Bad subjects:  ' . $self->status['badSubjects'] . "\n";
                echo 'Good objects:  ' . $self->status['goodObjects'] . "\n";
                echo 'Bad objects:   ' . $self->status['badObjects'] . "\n";
            },
        ]);

        $import->csv($fh, $skipRows);

        $this->success('Event CSV import complete.');

        return 0;
    }

    /**
     * Get an associative array of name => id from a database table.
     */
    private static function getNameIdArrayFromTable(&$self, string $tableName, string $keyColumn, string $idColumn, $whereClause = false): array
    {
        $names = [];

        $query = 'SELECT ' . $keyColumn . ', ' . $idColumn . ' FROM ' . $tableName;

        $query .= ($whereClause) ? ' WHERE ' . $whereClause : '';

        $statement = $self->sqlQuery($query);

        if (!$statement) {
            echo 'DB error';

            exit;
        }

        while ($subject = $statement->fetch(\PDO::FETCH_OBJ)) {
            $names[$subject->{$keyColumn}] = $subject->{$idColumn};
        }

        return $names;
    }
}
