<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\BaseCommand;

/**
 * Import authority records from CSV.
 *
 * Native implementation of the csv:authority-import Symfony task.
 */
class CsvAuthorityRecordImportCommand extends BaseCommand
{
    protected string $name = 'import:csv-authority-record';
    protected string $description = 'Import authority records from CSV';

    protected string $detailedDescription = <<<'EOF'
    Import CSV authority record (actor) data into AtoM. Supports entity types,
    contact information, occupations, digital objects, access points, and search indexing.
    EOF;

    protected function configure(): void
    {
        $this->addArgument('filename', 'The input file (CSV format)', true);

        $this->addOption('rows-until-update', null, 'Output total rows imported every n rows');
        $this->addOption('skip-rows', null, 'Skip n rows before importing');
        $this->addOption('error-log', null, 'File to log errors to');
        $this->addOption('source-name', null, 'Source name to use when inserting keymap entries');
        $this->addOption('index', null, 'Index for search during import');
        $this->addOption('update', null, 'Attempt to update if actor already exists. Valid values: "match-and-update", "delete-and-replace"');
        $this->addOption('skip-matched', null, 'Skip creating new records when existing one matches (without --update)');
        $this->addOption('skip-unmatched', null, 'Skip creating new records if no existing records match (with --update)');
        $this->addOption('skip-derivatives', null, 'Skip creation of digital object derivatives');
        $this->addOption('keep-digital-objects', null, 'Skip deletion of existing digital objects when using --update with "match-and-update"');
        $this->addOption('limit', null, 'Limit --update matching to under a specified maintaining repository via slug');
    }

    protected function handle(): int
    {
        $filename = $this->argument('filename');
        $options = [
            'rows-until-update' => $this->option('rows-until-update'),
            'skip-rows' => $this->option('skip-rows'),
            'error-log' => $this->option('error-log'),
            'source-name' => $this->option('source-name'),
            'index' => $this->hasOption('index'),
            'update' => $this->option('update'),
            'skip-matched' => $this->hasOption('skip-matched'),
            'skip-unmatched' => $this->hasOption('skip-unmatched'),
            'skip-derivatives' => $this->hasOption('skip-derivatives'),
            'keep-digital-objects' => $this->hasOption('keep-digital-objects'),
            'limit' => $this->option('limit'),
        ];

        $skipRows = ($options['skip-rows']) ? $options['skip-rows'] : 0;
        $sourceName = ($options['source-name']) ? $options['source-name'] : basename($filename);

        if (false === $fh = fopen($filename, 'rb')) {
            $this->error('You must specify a valid filename');

            return 1;
        }

        $termData = \QubitFlatfileImport::loadTermsFromTaxonomies([
            \QubitTaxonomy::NOTE_TYPE_ID => 'noteTypes',
            \QubitTaxonomy::ACTOR_ENTITY_TYPE_ID => 'actorTypes',
            \QubitTaxonomy::ACTOR_RELATION_TYPE_ID => 'actorRelationTypes',
            \QubitTaxonomy::DESCRIPTION_STATUS_ID => 'descriptionStatusTypes',
            \QubitTaxonomy::DESCRIPTION_DETAIL_LEVEL_ID => 'detailLevelTypes',
        ]);

        $relateTermFunction = function ($import, $column, $taxonomyId) {
            if (empty($import->rowStatusVars[$column])) {
                return;
            }

            $terms = explode('|', $import->rowStatusVars[$column]);

            for ($i = 0; $i < count($terms); ++$i) {
                if (empty($terms[$i])) {
                    continue;
                }

                $relation = \QubitActor::setTermRelationByName(
                    $terms[$i],
                    [
                        'taxonomyId' => $taxonomyId,
                        'culture' => $import->columnValue('culture'),
                    ]
                );

                if (null === $relation) {
                    continue;
                }

                $relationAlreadyExists = \QubitFlatfileImport::objectTermRelationExists($import->object->id, $relation->termId);

                if (!$relationAlreadyExists) {
                    $relation->object = $import->object;
                    $relation->save();
                }
            }
        };

        $import = new \QubitFlatfileImport([
            'context' => \sfContext::getInstance(),
            'className' => 'QubitActor',
            'rowsUntilProgressDisplay' => $options['rows-until-update'],
            'errorLog' => $options['error-log'],

            'status' => [
                'sourceName' => $sourceName,
                'actorTypes' => $termData['actorTypes'],
                'descriptionStatusTypes' => $termData['descriptionStatusTypes'],
                'detailLevelTypes' => $termData['detailLevelTypes'],
                'actorNames' => [],
                'relateTermFunction' => $relateTermFunction,
            ],

            'standardColumns' => [
                'authorizedFormOfName',
                'corporateBodyIdentifiers',
                'datesOfExistence',
                'history',
                'places',
                'legalStatus',
                'functions',
                'mandates',
                'internalStructures',
                'generalContext',
                'descriptionIdentifier',
                'rules',
                'revisionHistory',
                'sources',
            ],

            'columnMap' => [
                'institutionIdentifier' => 'institutionResponsibleIdentifier',
            ],

            'noteMap' => [
                'maintenanceNotes' => [
                    'typeId' => array_search('Maintenance note', $termData['noteTypes']['en']),
                ],
            ],

            'variableColumns' => [
                'typeOfEntity',
                'status',
                'levelOfDetail',
                'email',
                'notes',
                'countryCode',
                'fax',
                'telephone',
                'postalCode',
                'streetAddress',
                'region',
                'actorOccupations',
                'actorOccupationNotes',
                'placeAccessPoints',
                'subjectAccessPoints',
                'digitalObjectPath',
                'digitalObjectURI',
                'digitalObjectChecksum',
            ],

            'arrayColumns' => [
                'parallelFormsOfName' => '|',
                'standardizedFormsOfName' => '|',
                'otherFormsOfName' => '|',
                'script' => '|',
            ],

            'updatePreparationLogic' => function (&$self) use ($options) {
                if (
                    (!empty($self->rowStatusVars['digitalObjectPath']) || !empty($self->rowStatusVars['digitalObjectURI']))
                    && !$self->keepDigitalObjects
                ) {
                    $do = $self->object->getDigitalObject();
                    if (null !== $do) {
                        $deleteDigitalObject = true;
                        if ($self->isUpdating()) {
                            if (
                                !empty($self->rowStatusVars['digitalObjectChecksum'])
                                && $self->rowStatusVars['digitalObjectChecksum'] === $do->getChecksum()
                            ) {
                                $deleteDigitalObject = false;
                            }
                        }
                        if ($deleteDigitalObject) {
                            $this->info('Deleting existing digital object.');
                            $do->delete();
                        }
                    }
                }
            },

            'preSaveLogic' => function (&$self) {
                if ($self->object) {
                    if (
                        $self->columnExists('descriptionIdentifier')
                        && !empty($identifier = $self->columnValue('descriptionIdentifier'))
                        && \QubitValidatorActorDescriptionIdentifier::identifierUsedByAnotherActor($identifier, $self->object)
                    ) {
                        $error = \sfContext::getInstance()
                            ->i18n
                            ->__(
                                '%1% identifier "%2%" not unique.',
                                [
                                    '%1%' => \sfConfig::get('app_ui_label_actor'),
                                    '%2%' => $identifier,
                                ]
                            );

                        if (\sfConfig::get('app_prevent_duplicate_actor_identifiers', false)) {
                            $error .= \sfContext::getInstance()->i18n->__(' Import aborted.');
                            throw new \sfException($error);
                        }

                        echo $self->logError($error);
                    }

                    $self->object->entityTypeId = $self->createOrFetchTermIdFromName(
                        'actor entity type',
                        $self->rowStatusVars['typeOfEntity'],
                        $self->columnValue('culture'),
                        $self->status['actorTypes'],
                        \QubitTaxonomy::ACTOR_ENTITY_TYPE_ID
                    );

                    $self->object->descriptionStatusId = $self->createOrFetchTermIdFromName(
                        'description status',
                        $self->rowStatusVars['status'],
                        $self->columnValue('culture'),
                        $self->status['descriptionStatusTypes'],
                        \QubitTaxonomy::DESCRIPTION_STATUS_ID
                    );

                    $self->object->descriptionDetailId = $self->createOrFetchTermIdFromName(
                        'description detail levels',
                        $self->rowStatusVars['levelOfDetail'],
                        $self->columnValue('culture'),
                        $self->status['detailLevelTypes'],
                        \QubitTaxonomy::DESCRIPTION_DETAIL_LEVEL_ID
                    );
                }
            },

            'postSaveLogic' => function (&$self) use ($options) {
                if ($self->object) {
                    $self->status['actorNames'][$self->object->id] = $self->object->authorizedFormOfName;

                    CsvImportCommand::importAlternateFormsOfName($self);

                    $contactVariables = [
                        'email', 'notes', 'countryCode', 'fax', 'telephone',
                        'postalCode', 'streetAddress', 'region',
                    ];

                    $hasContactInfo = false;
                    foreach (array_keys($self->rowStatusVars) as $name) {
                        if (in_array($name, $contactVariables)) {
                            $hasContactInfo = true;
                        }
                    }

                    if ($hasContactInfo) {
                        $info = new \QubitContactInformation();
                        $info->actorId = $self->object->id;

                        foreach ($contactVariables as $property) {
                            if ($self->rowStatusVars[$property]) {
                                $info->{$property} = $self->rowStatusVars[$property];
                            }
                        }

                        $info->save();
                    }

                    $self->status['relateTermFunction']($self, 'placeAccessPoints', \QubitTaxonomy::PLACE_ID);
                    $self->status['relateTermFunction']($self, 'subjectAccessPoints', \QubitTaxonomy::SUBJECT_ID);

                    if (!empty($self->rowStatusVars['actorOccupations'])) {
                        $occupations = explode('|', $self->rowStatusVars['actorOccupations']);
                        $occupationNotes = [];

                        if (!empty($self->rowStatusVars['actorOccupationNotes'])) {
                            $occupationNotes = explode('|', $self->rowStatusVars['actorOccupationNotes']);
                        }

                        for ($i = 0; $i < count($occupations); ++$i) {
                            if (empty($occupations[$i])) {
                                continue;
                            }

                            if (null !== $relation = \QubitActor::setTermRelationByName($occupations[$i], $opts = ['taxonomyId' => \QubitTaxonomy::ACTOR_OCCUPATION_ID, 'culture' => $self->columnValue('culture')])) {
                                $relation->object = $self->object;
                                $relation->save();

                                if (!empty($occupationNotes[$i]) && 'NULL' !== $occupationNotes[$i]) {
                                    $note = new \QubitNote();
                                    $note->typeId = \QubitTerm::ACTOR_OCCUPATION_NOTE_ID;
                                    $note->content = $occupationNotes[$i];
                                    $note->object = $relation;
                                    $note->save();
                                }
                            }
                        }
                    }

                    // Add digital object
                    if (null === $self->object->getDigitalObject()) {
                        if ($uri = $self->rowStatusVars['digitalObjectURI']) {
                            $do = new \QubitDigitalObject();
                            $do->object = $self->object;
                            $do->indexOnSave = false;
                            $doOptions = [];
                            if ($options['skip-derivatives']) {
                                $do->createDerivatives = false;
                            } else {
                                $doOptions = ['downloadRetries' => 2];
                            }
                            try {
                                $do->importFromURI($uri, $doOptions);
                                $do->save();
                            } catch (\Exception $e) {
                                echo $e->getMessage() . "\n";
                            }
                        } elseif ($path = $self->rowStatusVars['digitalObjectPath']) {
                            if (is_readable($path)) {
                                $do = new \QubitDigitalObject();
                                $do->usageId = \QubitTerm::MASTER_ID;
                                $do->object = $self->object;
                                $do->indexOnSave = false;
                                if ($options['skip-derivatives']) {
                                    $do->createDerivatives = false;
                                }
                                $do->assets[] = new \QubitAsset($path);
                                try {
                                    $do->save();
                                } catch (\Exception $e) {
                                    echo $e->getMessage() . "\n";
                                }
                            }
                        }
                    }

                    if (!$self->searchIndexingDisabled) {
                        \QubitSearch::getInstance()->update($self->object);
                    }

                    \Qubit::clearClassCaches();
                }
            },
        ]);

        $import->searchIndexingDisabled = ($options['index']) ? false : true;
        $import->setUpdateOptions($options);
        $import->csv($fh, $skipRows);

        $this->success('Authority record CSV import complete.');

        return 0;
    }
}
