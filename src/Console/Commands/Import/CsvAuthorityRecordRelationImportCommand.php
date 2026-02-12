<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\BaseCommand;

/**
 * Import authority record relations from CSV.
 *
 * Native implementation of the csv:authority-relation-import Symfony task.
 */
class CsvAuthorityRecordRelationImportCommand extends BaseCommand
{
    protected string $name = 'import:csv-authority-record-relation';
    protected string $description = 'Import authority record relations from CSV';

    protected string $detailedDescription = <<<'EOF'
    Import authority record relations using CSV data. Creates or updates
    relationships between existing actor records based on CSV data.
    EOF;

    private $import;
    private $newlyAdded = [];

    protected function configure(): void
    {
        $this->addArgument('filename', 'The input file (CSV format)', true);

        $this->addOption('index', null, 'Index for search during import');
        $this->addOption('update', null, 'Attempt to update if relation already exists. Valid values: "match-and-update", "delete-and-replace"');
    }

    protected function handle(): int
    {
        $filename = $this->argument('filename');
        $indexDuringImport = $this->hasOption('index');
        $updateMode = $this->option('update');

        // Validate update options
        if ($updateMode) {
            $validParams = ['match-and-update', 'delete-and-replace'];
            if (!in_array(trim($updateMode), $validParams)) {
                $msg = sprintf('Parameter "%s" is not valid for --update option. ', $updateMode);
                $msg .= sprintf('Valid options are: %s', implode(', ', $validParams));
                $this->error($msg);

                return 1;
            }
        }

        $this->info('Importing relations...');
        $this->doImport($filename, $indexDuringImport, $updateMode);
        $this->success('Done.');

        return 0;
    }

    private function doImport(string $filepath, bool $indexDuringImport = false, $updateMode = false): void
    {
        if (false === $fh = fopen($filepath, 'rb')) {
            throw new \sfException('You must specify a valid filename');
        }

        $termData = \QubitFlatfileImport::loadTermsFromTaxonomies([
            \QubitTaxonomy::ACTOR_RELATION_TYPE_ID => 'actorRelationTypes',
        ]);

        $this->import = new \QubitFlatfileImport([
            'context' => \sfContext::getInstance(),

            'status' => [
                'updateMode' => $updateMode,
                'actorRelationTypes' => $termData['actorRelationTypes'],
                'actorIds' => [],
            ],

            'variableColumns' => [
                'objectAuthorizedFormOfName',
                'subjectAuthorizedFormOfName',
                'relationType',
                'description',
                'date',
                'startDate',
                'endDate',
            ],

            'saveLogic' => function ($self) {
                $sourceActor = \QubitActor::getByAuthorizedFormOfName(
                    $self->columnValue('objectAuthorizedFormOfName'),
                    ['culture' => $self->columnValue('culture')]
                );
                $targetActor = \QubitActor::getByAuthorizedFormOfName(
                    $self->columnValue('subjectAuthorizedFormOfName'),
                    ['culture' => $self->columnValue('culture')]
                );

                $relationTypeId = CsvImportCommand::arraySearchCaseInsensitive(
                    $self->columnValue('relationType'),
                    $self->status['actorRelationTypes'][$self->columnValue('culture')]
                );

                if (!$relationTypeId) {
                    $error = sprintf('Unknown relationship type "%s"... skipping row.', $self->columnValue('relationType'));
                    echo $self->logError($error);
                } else {
                    if (empty($sourceActor) || empty($targetActor)) {
                        $badActor = (empty($sourceActor))
                            ? $self->columnValue('objectAuthorizedFormOfName')
                            : $self->columnValue('subjectAuthorizedFormOfName');

                        $error = sprintf('Actor "%s" does not exist... skipping row.', $badActor);
                        echo $self->logError($error);
                    } else {
                        $this->importRow($sourceActor->id, $targetActor->id, $relationTypeId);
                    }
                }
            },
        ]);

        $this->import->searchIndexingDisabled = !$indexDuringImport;
        $this->import->csv($fh);

        if ($indexDuringImport) {
            $this->info('Updating Elasticsearch actor relation data...');

            foreach ($this->import->status['actorIds'] as $actorId) {
                $actor = \QubitActor::getById($actorId);
                \arUpdateEsActorRelationsJob::updateActorRelationships($actor);
                \Qubit::clearClassCaches();
            }
        }
    }

    private function importRow($sourceActorId, $targetActorId, $relationTypeId): void
    {
        $updateMode = !empty($this->import->status['updateMode'])
            ? $this->import->status['updateMode']
            : false;

        if ($updateMode) {
            if ('delete-and-replace' == $updateMode) {
                $relations = $this->getRelations($targetActorId, $sourceActorId);
                $relationsAlternate = $this->getRelations($sourceActorId, $targetActorId);

                foreach (array_unique(array_merge($relations, $relationsAlternate)) as $relationId) {
                    if (!in_array($relationId, $this->newlyAdded)) {
                        $relation = \QubitRelation::getById($relationId);
                        $relation->delete();
                    }
                }
            } elseif ($relationId = $this->getRelationByType($sourceActorId, $targetActorId, $relationTypeId)) {
                $this->updateRelation($relationId, $sourceActorId, $targetActorId, $relationTypeId);

                return;
            }
        } elseif (!empty($this->getRelationByType($sourceActorId, $targetActorId, $relationTypeId))) {
            echo $this->import->logError('Skipping row as relationship already exists');

            return;
        }

        $relation = $this->addRelation($sourceActorId, $targetActorId, $relationTypeId);

        if ('delete-and-replace' == $updateMode) {
            $this->newlyAdded[] = $relation->id;
        }
    }

    private function getRelationByType($sourceActorId, $targetActorId, $relationTypeId)
    {
        $sql = 'SELECT id FROM relation
            WHERE subject_id = :subject_id
            AND object_id = :object_id
            AND type_id = :type_id
            LIMIT 1';

        $params = [
            ':subject_id' => $sourceActorId,
            ':object_id' => $targetActorId,
            ':type_id' => $relationTypeId,
        ];

        $paramsVariant = [
            ':subject_id' => $targetActorId,
            ':object_id' => $sourceActorId,
            ':type_id' => $relationTypeId,
        ];

        if ($relationId = \QubitPdo::fetchColumn($sql, $params)) {
            return $relationId;
        }

        return \QubitPdo::fetchColumn($sql, $paramsVariant);
    }

    private function getRelations($sourceActorId, $targetActorId): array
    {
        $sql = 'SELECT id FROM relation
            WHERE subject_id = :subject_id
            AND object_id = :object_id';

        $results = \QubitPdo::fetchAll(
            $sql,
            [':subject_id' => $sourceActorId, ':object_id' => $targetActorId],
            ['fetchMode' => \PDO::FETCH_ASSOC]
        );

        return array_column($results, 'id');
    }

    private function addRelation($sourceActorId, $targetActorId, $relationTypeId)
    {
        $relation = new \QubitRelation();
        $this->setRelationFields($relation, $sourceActorId, $targetActorId, $relationTypeId);
        $relation->save();

        $this->addUpdatedActorIds([$sourceActorId, $targetActorId]);

        return $relation;
    }

    private function updateRelation($relationId, $sourceActorId, $targetActorId, $relationTypeId): void
    {
        $relation = \QubitRelation::getById($relationId);
        $this->setRelationFields($relation, $sourceActorId, $targetActorId, $relationTypeId);
        $relation->save();

        $this->addUpdatedActorIds([$sourceActorId, $targetActorId]);
    }

    private function setRelationFields(&$relation, $sourceActorId, $targetActorId, $relationTypeId): void
    {
        $relation->objectId = $targetActorId;
        $relation->subjectId = $sourceActorId;
        $relation->typeId = $relationTypeId;

        foreach (['date', 'startDate', 'endDate', 'description'] as $property) {
            if (!empty($this->import->columnValue($property))) {
                $relation->{$property} = $this->import->columnValue($property);
            }
        }
    }

    private function addUpdatedActorIds(array $actorIds): void
    {
        foreach ($actorIds as $id) {
            if (!in_array($id, $this->import->status['actorIds'])) {
                $this->import->status['actorIds'][] = $id;
            }
        }
    }
}
