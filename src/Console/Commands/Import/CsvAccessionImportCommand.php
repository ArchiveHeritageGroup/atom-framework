<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\BaseCommand;

/**
 * Import accessions from CSV.
 *
 * Native implementation of the csv:accession-import Symfony task.
 */
class CsvAccessionImportCommand extends BaseCommand
{
    protected string $name = 'import:csv-accession';
    protected string $description = 'Import accessions from CSV';

    protected string $detailedDescription = <<<'EOF'
    Import CSV accession data into AtoM. Supports accession numbers, donor info,
    alternative identifiers, accession events, physical objects, and search indexing.
    EOF;

    protected function configure(): void
    {
        $this->addArgument('filename', 'The input file (CSV format)', true);

        $this->addOption('rows-until-update', null, 'Output total rows imported every n rows');
        $this->addOption('skip-rows', null, 'Skip n rows before importing');
        $this->addOption('error-log', null, 'File to log errors to');
        $this->addOption('source-name', null, 'Source name to use when inserting keymap entries');
        $this->addOption('index', null, 'Index for search during import');
        $this->addOption('assign-id', null, 'Assign identifier based on mask and counter if no accession number specified');
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
            'assign-id' => $this->hasOption('assign-id'),
        ];

        $this->validateImportOptions($options);

        $skipRows = ($options['skip-rows']) ? $options['skip-rows'] : 0;

        $sourceName = ($options['source-name'])
            ? $options['source-name']
            : basename($filename);

        if (false === $fh = fopen($filename, 'rb')) {
            $this->error('You must specify a valid filename');

            return 1;
        }

        // Load taxonomies into variables to avoid use of magic numbers
        $termData = \QubitFlatfileImport::loadTermsFromTaxonomies([
            \QubitTaxonomy::ACCESSION_ACQUISITION_TYPE_ID => 'acquisitionTypes',
            \QubitTaxonomy::ACCESSION_RESOURCE_TYPE_ID => 'resourceTypes',
            \QubitTaxonomy::ACCESSION_PROCESSING_STATUS_ID => 'processingStatus',
            \QubitTaxonomy::ACCESSION_PROCESSING_PRIORITY_ID => 'processingPriority',
            \QubitTaxonomy::ACCESSION_ALTERNATIVE_IDENTIFIER_TYPE_ID => 'alternativeIdentifierTypes',
            \QubitTaxonomy::PHYSICAL_OBJECT_TYPE_ID => 'physicalObjectTypes',
            \QubitTaxonomy::ACCESSION_EVENT_TYPE_ID => 'accessionEventTypes',
        ]);

        // Define import
        $import = new \QubitFlatfileImport([
            'context' => \sfContext::getInstance(),

            'rowsUntilProgressDisplay' => $options['rows-until-update'],
            'errorLog' => $options['error-log'],

            'status' => [
                'sourceName' => $sourceName,
                'acquisitionTypes' => $termData['acquisitionTypes'],
                'resourceTypes' => $termData['resourceTypes'],
                'physicalObjectTypes' => $termData['physicalObjectTypes'],
                'processingStatus' => $termData['processingStatus'],
                'processingPriority' => $termData['processingPriority'],
                'alternativeIdentifierTypes' => $termData['alternativeIdentifierTypes'],
                'accessionEventTypes' => $termData['accessionEventTypes'],
                'assignId' => $options['assign-id'],
            ],

            'standardColumns' => [
                'appraisal',
                'archivalHistory',
                'acquisitionDate',
                'locationInformation',
                'processingNotes',
                'receivedExtentUnits',
                'scopeAndContent',
                'sourceOfAcquisition',
                'title',
            ],

            'arrayColumns' => [
                'alternativeIdentifiers' => '|',
                'alternativeIdentifierTypes' => '|',
                'alternativeIdentifierNotes' => '|',

                'eventActors' => '|',
                'eventActorHistories' => '|',
                'eventTypes' => '|',
                'eventPlaces' => '|',
                'eventDates' => '|',
                'eventStartDates' => '|',
                'eventEndDates' => '|',
                'eventDescriptions' => '|',

                'accessionEventTypes' => '|',
                'accessionEventDates' => '|',
                'accessionEventAgents' => '|',
                'accessionEventNotes' => '|',

                'creators' => '|',
                'creatorHistories' => '|',
                'creatorDates' => '|',
                'creatorDatesStart' => '|',
                'creatorDatesEnd' => '|',
                'creatorDateNotes' => '|',
                'creationDates' => '|',
                'creationDatesStart' => '|',
                'creationDatesEnd' => '|',
                'creationDateNotes' => '|',
                'creationDatesType' => '|',
            ],

            'columnMap' => [
                'physicalCondition' => 'physicalCharacteristics',
            ],

            'variableColumns' => [
                'accessionNumber',
                'acquisitionType',
                'resourceType',
                'physicalObjectName',
                'physicalObjectLocation',
                'physicalObjectType',
                'donorName',
                'donorStreetAddress',
                'donorCity',
                'donorRegion',
                'donorCountry',
                'donorPostalCode',
                'donorCountry',
                'donorTelephone',
                'donorFax',
                'donorContactPerson',
                'donorEmail',
                'donorNote',
                'qubitParentSlug',
            ],

            'rowInitLogic' => function (&$self) {
                $accessionNumber = $self->rowStatusVars['accessionNumber'];

                $statement = $self->sqlQuery(
                    'SELECT id FROM accession WHERE identifier=?',
                    $params = [$accessionNumber]
                );

                $result = $statement->fetch(\PDO::FETCH_OBJ);
                if ($result) {
                    echo $self->logError(sprintf('Found accession ID %d with identifier %s', $result->id, $accessionNumber));
                    $self->object = \QubitAccession::getById($result->id);
                } elseif (!empty($accessionNumber)) {
                    echo $self->logError(sprintf('Could not find accession # %s, creating.', $accessionNumber));
                    $self->object = new \QubitAccession();
                    $self->object->identifier = $accessionNumber;
                } elseif ($self->getStatus('assignId')) {
                    $identifier = \QubitAccession::nextAvailableIdentifier();
                    echo $self->logError(sprintf('No accession number, creating accession with identifier %s', $identifier));
                    $self->object = new \QubitAccession();
                    $self->object->identifier = $identifier;
                } else {
                    echo $self->logError('No accession number, skipping');
                }
            },

            'saveLogic' => function (&$self) {
                if (isset($self->object) && $self->object instanceof \QubitAccession) {
                    $self->object->save();
                }
            },

            'postSaveLogic' => function (&$self) {
                if (isset($self->object) && $self->object instanceof \QubitAccession && isset($self->object->id)) {
                    // Add creators
                    if (
                        isset($self->rowStatusVars['creators'])
                        && $self->rowStatusVars['creators']
                    ) {
                        foreach ($self->rowStatusVars['creators'] as $creator) {
                            $actor = $self->createOrFetchActor($creator);
                            $self->createRelation($actor->id, $self->object->id, \QubitTerm::CREATION_ID);
                        }
                    }

                    // Add alternative identifiers
                    $identifiers = $self->rowStatusVars['alternativeIdentifiers'];
                    $identifierNotes = $self->rowStatusVars['alternativeIdentifierNotes'];

                    if (!empty($identifiers) || !empty($identifierNotes)) {
                        $identifierTypes = $self->rowStatusVars['alternativeIdentifierTypes'];

                        $identifierCount = empty($identifiers) ? 0 : count($identifiers);
                        $identifierNotesCount = empty($identifierNotes) ? 0 : count($identifierNotes);
                        for ($index = 0; $index < max($identifierCount, $identifierNotesCount); ++$index) {
                            $identifier = (empty($identifiers[$index])) ? null : $identifiers[$index];

                            if (!empty($identifier) || !empty($identifierNotes[$index])) {
                                $otherName = new \QubitOtherName();
                                $otherName->object = $self->object;
                                $otherName->name = $identifier;

                                $otherName->typeId = \QubitTerm::ACCESSION_ALTERNATIVE_IDENTIFIER_DEFAULT_TYPE_ID;
                                if (!empty($typeName = $identifierTypes[$index])) {
                                    if (empty($typeId = CsvImportCommand::arraySearchCaseInsensitive($typeName, $self->status['alternativeIdentifierTypes'][$self->columnValue('culture')]))) {
                                        $term = new \QubitTerm();
                                        $term->parentId = \QubitTerm::ROOT_ID;
                                        $term->taxonomyId = \QubitTaxonomy::ACCESSION_ALTERNATIVE_IDENTIFIER_TYPE_ID;
                                        $term->setName($typeName, ['culture' => $self->columnValue('culture')]);
                                        $term->sourceCulture = $self->columnValue('culture');
                                        $term->save();

                                        $self->status['alternativeIdentifierTypes'][$self->columnValue('culture')][$term->id] = $typeName;

                                        $typeId = $term->id;
                                    }

                                    $otherName->typeId = $typeId;
                                }

                                if (!empty($note = $identifierNotes[$index])) {
                                    $otherName->setNote($note, ['culture' => $self->columnValue('culture')]);
                                }

                                $otherName->culture = $self->columnValue('culture');
                                $otherName->save();
                            }
                        }
                    }

                    // Add physical objects
                    CsvImportCommand::importPhysicalObjects($self);

                    // Add events
                    CsvImportCommand::importEvents($self);

                    // Add accession events
                    $eventTypes = $self->rowStatusVars['accessionEventTypes'];
                    $eventDates = $self->rowStatusVars['accessionEventDates'];

                    if (!empty($eventTypes) || !empty($eventDates)) {
                        $eventAgents = $self->rowStatusVars['accessionEventAgents'];
                        $eventNotes = $self->rowStatusVars['accessionEventNotes'];

                        for ($index = 0; $index < count($eventTypes); ++$index) {
                            $eventType = (empty($eventTypes[$index])) ? null : $eventTypes[$index];
                            $eventDate = (empty($eventDates[$index])) ? null : $eventDates[$index];

                            if (!empty($eventType) && !empty($eventDate)) {
                                if (empty($typeId = CsvImportCommand::arraySearchCaseInsensitive($eventType, $self->status['accessionEventTypes'][$self->columnValue('culture')]))) {
                                    $term = new \QubitTerm();
                                    $term->parentId = \QubitTerm::ROOT_ID;
                                    $term->taxonomyId = \QubitTaxonomy::ACCESSION_EVENT_TYPE_ID;
                                    $term->setName($eventType, ['culture' => $self->columnValue('culture')]);
                                    $term->sourceCulture = $self->columnValue('culture');
                                    $term->save();

                                    $self->status['accessionEventTypes'][$self->columnValue('culture')][$term->id] = $eventType;

                                    $typeId = $term->id;
                                }

                                $eventAgent = (empty($eventAgents[$index])) ? null : $eventAgents[$index];
                                $eventNoteText = (empty($eventNotes[$index])) ? null : $eventNotes[$index];

                                $event = new \QubitAccessionEvent();
                                $event->accessionId = $self->object->id;
                                $event->typeId = $typeId;
                                $event->date = $eventDate;
                                $event->agent = $eventAgent;
                                $event->save();

                                if (!empty($eventNoteText)) {
                                    $note = new \QubitNote();
                                    $note->objectId = $event->id;
                                    $note->typeId = \QubitTerm::ACCESSION_EVENT_NOTE_ID;
                                    $note->setContent($eventNoteText, ['culture' => $self->columnValue('culture')]);
                                    $note->save();
                                }
                            }
                        }
                    }

                    if (
                        isset($self->rowStatusVars['donorName'])
                        && $self->rowStatusVars['donorName']
                    ) {
                        $donor = $self->createOrFetchDonor($self->rowStatusVars['donorName']);

                        $columnToProperty = [
                            'donorEmail' => 'email',
                            'donorTelephone' => 'telephone',
                            'donorFax' => 'fax',
                            'donorStreetAddress' => 'streetAddress',
                            'donorCity' => 'city',
                            'donorRegion' => 'region',
                            'donorPostalCode' => 'postalCode',
                            'donorNote' => 'note',
                            'donorContactPerson' => 'contactPerson',
                        ];

                        $contactData = [];
                        foreach ($columnToProperty as $column => $property) {
                            if (isset($self->rowStatusVars[$column])) {
                                $contactData[$property] = $self->rowStatusVars[$column];
                            }
                        }

                        if (!empty($self->rowStatusVars['donorCountry'])) {
                            $countryCode = \QubitFlatfileImport::normalizeCountryAsCountryCode($self->rowStatusVars['donorCountry']);
                            if (null === $countryCode) {
                                echo sprintf("Could not find country or country code matching '%s'\n", $self->rowStatusVars['donorCountry']);
                            } else {
                                $contactData['countryCode'] = $countryCode;
                            }
                        }

                        $self->createOrFetchContactInformation($donor->id, $contactData);
                        $self->createRelation($self->object->id, $donor->id, \QubitTerm::DONOR_ID);
                    }

                    // Link accession to existing description
                    if (
                        isset($self->rowStatusVars['qubitParentSlug'])
                        && $self->rowStatusVars['qubitParentSlug']
                    ) {
                        $query = 'SELECT object_id FROM slug WHERE slug=?';
                        $statement = \QubitFlatfileImport::sqlQuery($query, [$self->rowStatusVars['qubitParentSlug']]);
                        $result = $statement->fetch(\PDO::FETCH_OBJ);
                        if ($result) {
                            $self->createRelation($result->object_id, $self->object->id, \QubitTerm::ACCESSION_ID);
                        } else {
                            throw new \sfException('Could not find information object matching slug "' . $self->rowStatusVars['qubitParentSlug'] . '"');
                        }
                    }
                }

                // Add keymap entry
                if (!empty($self->rowStatusVars['accessionNumber'])) {
                    $self->createKeymapEntry($self->getStatus('sourceName'), $self->rowStatusVars['accessionNumber']);
                }

                // Re-index to add related resources
                if (!$self->searchIndexingDisabled) {
                    \QubitSearch::getInstance()->update($self->object);
                }
            },
        ]);

        $import->addColumnHandler('acquisitionDate', function ($self, $data) {
            if ($data) {
                if (isset($self->object) && is_object($self->object)) {
                    $parsedDate = \Qubit::parseDate($data);
                    if ($parsedDate) {
                        $self->object->date = $parsedDate;
                    } else {
                        $self->logError('Could not parse date: ' . $data);
                    }
                }
            }
        });

        $import->addColumnHandler('resourceType', function ($self, $data) {
            if ($data && isset($self->object) && $self->object instanceof \QubitAccession) {
                $self->object->resourceTypeId = $self->createOrFetchTermIdFromName(
                    'resource type',
                    trim($data),
                    $self->columnValue('culture'),
                    $self->status['resourceTypes'],
                    \QubitTaxonomy::ACCESSION_RESOURCE_TYPE_ID
                );
            }
        });

        $import->addColumnHandler('acquisitionType', function ($self, $data) {
            if ($data && isset($self->object) && $self->object instanceof \QubitAccession) {
                $self->object->acquisitionTypeId = $self->createOrFetchTermIdFromName(
                    'acquisition type',
                    trim($data),
                    $self->columnValue('culture'),
                    $self->status['acquisitionTypes'],
                    \QubitTaxonomy::ACCESSION_ACQUISITION_TYPE_ID
                );
            }
        });

        $import->addColumnHandler('processingStatus', function ($self, $data) {
            if ($data && isset($self->object) && $self->object instanceof \QubitAccession) {
                $self->object->processingStatusId = $self->createOrFetchTermIdFromName(
                    'processing status',
                    trim($data),
                    $self->columnValue('culture'),
                    $self->status['processingStatus'],
                    \QubitTaxonomy::ACCESSION_PROCESSING_STATUS_ID
                );
            }
        });

        $import->addColumnHandler('processingPriority', function ($self, $data) {
            if ($data && isset($self->object) && $self->object instanceof \QubitAccession) {
                $self->object->processingPriorityId = $self->createOrFetchTermIdFromName(
                    'processing priority',
                    trim($data),
                    $self->columnValue('culture'),
                    $self->status['processingPriority'],
                    \QubitTaxonomy::ACCESSION_PROCESSING_PRIORITY_ID
                );
            }
        });

        $import->searchIndexingDisabled = ($options['index']) ? false : true;

        $import->csv($fh, $skipRows);

        $this->success('Accession CSV import complete.');

        return 0;
    }

    private function validateImportOptions(array $options): void
    {
        $numericOptions = ['rows-until-update', 'skip-rows'];

        foreach ($numericOptions as $option) {
            if ($options[$option] && !is_numeric($options[$option])) {
                throw new \sfException($option . ' must be an integer');
            }
        }

        if ($options['error-log'] && !is_dir(dirname($options['error-log']))) {
            throw new \sfException('Path to error log is invalid.');
        }
    }
}
