<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\BaseCommand;

/**
 * Import CSV information object data.
 *
 * Native implementation of the csv:import Symfony task.
 * Uses Propel classes via PropelBridge for object hydration and persistence.
 */
class CsvImportCommand extends BaseCommand
{
    protected string $name = 'import:csv';
    protected string $description = 'Import CSV information object data';

    protected string $detailedDescription = <<<'EOF'
    Import CSV data into AtoM as information objects. Supports creation of new
    records and updating existing records via --update option. Handles digital
    objects, access points, events, physical objects, copyright, and accessions.
    EOF;

    protected function configure(): void
    {
        $this->addArgument('filename', 'The input file (CSV format)', true);

        // Base CSV import options
        $this->addOption('rows-until-update', null, 'Output total rows imported every n rows');
        $this->addOption('skip-rows', null, 'Skip n rows before importing');
        $this->addOption('error-log', null, 'File to log errors to');
        $this->addOption('source-name', null, 'Source name to use when inserting keymap entries');
        $this->addOption('default-parent-slug', null, 'Parent slug under which imported items with no parent specified will be added');
        $this->addOption('default-legacy-parent-id', null, 'Legacy parent ID under which imported items with no parent specified will be added');
        $this->addOption('skip-nested-set-build', null, 'Do not build the nested set upon import completion');
        $this->addOption('index', null, 'Index for search during import');
        $this->addOption('update', null, 'Attempt to update if description has already been imported. Valid values: "match-and-update", "delete-and-replace"');
        $this->addOption('skip-matched', null, 'Skip creating new records when an existing one matches (without --update)');
        $this->addOption('skip-unmatched', null, 'Skip creating new records if no existing records match (with --update)');
        $this->addOption('skip-derivatives', null, 'Skip creation of digital object derivatives');
        $this->addOption('limit', null, 'Limit --update matching to under a specified top level description or repository via slug');
        $this->addOption('user-id', null, 'User ID to run import as');
        $this->addOption('keep-digital-objects', null, 'Skip deletion of existing digital objects when using --update with "match-and-update"');
        $this->addOption('roundtrip', null, 'Treat legacy IDs as internal IDs');
        $this->addOption('no-confirmation', null, 'Do not ask for confirmation');
        $this->addOption('quiet', 'q', 'Suppress progress output');
    }

    protected function handle(): int
    {
        $filename = $this->argument('filename');

        if (!file_exists($filename) || !is_readable($filename)) {
            $this->error('You must specify a valid filename');

            return 1;
        }

        // Initialize search
        \QubitSearch::getInstance();

        // Build options array for compatibility with the task closures
        $options = $this->buildOptionsArray();

        // Validate options
        $this->validateImportOptions($options);

        if (false === $fh = fopen($filename, 'rb')) {
            $this->error('You must specify a valid filename');

            return 1;
        }

        if (!empty($options['user-id']) && (null !== $user = \QubitUser::getById($options['user-id']))) {
            \sfContext::getInstance()->getUser()->signIn($user);
        }

        $skipRows = $options['skip-rows'] ?: 0;
        $sourceName = $options['source-name'] ?: basename($filename);
        $defaultStatusId = \sfConfig::get('app_defaultPubStatus', \QubitTerm::PUBLICATION_STATUS_PUBLISHED_ID);

        // Load taxonomies into variables to avoid use of magic numbers
        $termData = \QubitFlatfileImport::loadTermsFromTaxonomies([
            \QubitTaxonomy::DESCRIPTION_STATUS_ID => 'descriptionStatusTypes',
            \QubitTaxonomy::PUBLICATION_STATUS_ID => 'pubStatusTypes',
            \QubitTaxonomy::DESCRIPTION_DETAIL_LEVEL_ID => 'levelOfDetailTypes',
            \QubitTaxonomy::NOTE_TYPE_ID => 'noteTypes',
            \QubitTaxonomy::RAD_NOTE_ID => 'radNoteTypes',
            \QubitTaxonomy::RAD_TITLE_NOTE_ID => 'titleNoteTypes',
            \QubitTaxonomy::MATERIAL_TYPE_ID => 'materialTypes',
            \QubitTaxonomy::RIGHT_ACT_ID => 'copyrightActTypes',
            \QubitTaxonomy::COPYRIGHT_STATUS_ID => 'copyrightStatusTypes',
            \QubitTaxonomy::PHYSICAL_OBJECT_TYPE_ID => 'physicalObjectTypes',
        ]);

        if (
            $options['roundtrip']
            && !$options['no-confirmation']
            && !$this->confirm(
                'WARNING: In round trip mode legacy IDs will be treated as internal IDs. '
                . 'Please back-up your database manually before you proceed. '
                . 'Have you done a manual backup and wish to proceed?'
            )
        ) {
            $this->info('Task aborted.');

            return 1;
        }

        $defaultParentId = $this->getDefaultParentId($sourceName, $options);
        $self = $this;

        // Define import
        $import = new \QubitFlatfileImport([
            // Pass context
            'context' => \sfContext::getInstance(),

            // What type of object are we importing?
            'className' => 'QubitInformationObject',

            // Allow silencing of progress info
            'displayProgress' => ($options['quiet']) ? false : true,

            // How many rows should import until we display an import status update?
            'rowsUntilProgressDisplay' => $options['rows-until-update'],

            // Where to log errors to
            'errorLog' => $options['error-log'],

            // The status array is a place to put data that should be accessible
            // from closure logic using the getStatus method
            'status' => [
                'options' => $options,
                'sourceName' => $sourceName,
                'defaultParentId' => $defaultParentId,
                'copyrightStatusTypes' => $termData['copyrightStatusTypes'],
                'copyrightActTypes' => $termData['copyrightActTypes'],
                'defaultStatusId' => $defaultStatusId,
                'descriptionStatusTypes' => $termData['descriptionStatusTypes'],
                'pubStatusTypes' => $termData['pubStatusTypes'],
                'levelOfDetailTypes' => $termData['levelOfDetailTypes'],
                'materialTypes' => $termData['materialTypes'],
                'physicalObjectTypes' => $termData['physicalObjectTypes'],
            ],

            // Import columns that map directly to QubitInformationObject properties
            'standardColumns' => [
                'updatedAt',
                'createdAt',
                'accessConditions',
                'accruals',
                'acquisition',
                'alternateTitle',
                'appraisal',
                'archivalHistory',
                'arrangement',
                'culture',
                'descriptionIdentifier',
                'extentAndMedium',
                'findingAids',
                'identifier',
                'locationOfCopies',
                'locationOfOriginals',
                'physicalCharacteristics',
                'relatedUnitsOfDescription',
                'reproductionConditions',
                'revisionHistory',
                'rules',
                'scopeAndContent',
                'sources',
                'title',
            ],

            'columnMap' => [
                'radEdition' => 'edition',
                'institutionIdentifier' => 'institutionResponsibleIdentifier',
            ],

            // Import columns that can be added using the
            // QubitInformationObject::addProperty method
            'propertyMap' => [
                'radOtherTitleInformation' => 'otherTitleInformation',
                'radTitleStatementOfResponsibility' => 'titleStatementOfResponsibility',
                'radStatementOfProjection' => 'statementOfProjection',
                'radStatementOfCoordinates' => 'statementOfCoordinates',
                'radStatementOfScaleArchitectural' => 'statementOfScaleArchitectural',
                'radStatementOfScaleCartographic' => 'statementOfScaleCartographic',
                'radPublishersSeriesNote' => 'noteOnPublishersSeries',
                'radIssuingJurisdiction' => 'issuingJurisdictionAndDenomination',
                'radEditionStatementOfResponsibility' => 'editionStatementOfResponsibility',
                'radTitleProperOfPublishersSeries' => 'titleProperOfPublishersSeries',
                'radParallelTitlesOfPublishersSeries' => 'parallelTitleOfPublishersSeries',
                'radOtherTitleInformationOfPublishersSeries' => 'otherTitleInformationOfPublishersSeries',
                'radStatementOfResponsibilityRelatingToPublishersSeries' => 'statementOfResponsibilityRelatingToPublishersSeries',
                'radNumberingWithinPublishersSeries' => 'numberingWithinPublishersSeries',
                'radStandardNumber' => 'standardNumber',
            ],

            // Import columns that can be added as QubitNote objects
            'noteMap' => [
                'languageNote' => [
                    'typeId' => array_search('Language note', $termData['noteTypes']['en']),
                ],
                'publicationNote' => [
                    'typeId' => array_search('Publication note', $termData['noteTypes']['en']),
                ],
                'generalNote' => [
                    'typeId' => array_search('General note', $termData['noteTypes']['en']),
                ],
                'archivistNote' => [
                    'typeId' => array_search("Archivist's note", $termData['noteTypes']['en']),
                ],
                'radNoteCast' => [
                    'typeId' => array_search('Cast note', $termData['radNoteTypes']['en']),
                ],
                'radNoteCredits' => [
                    'typeId' => array_search('Credits note', $termData['radNoteTypes']['en']),
                ],
                'radNoteSignaturesInscriptions' => [
                    'typeId' => array_search('Signatures note', $termData['radNoteTypes']['en']),
                ],
                'radNoteConservation' => [
                    'typeId' => array_search('Conservation', $termData['radNoteTypes']['en']),
                ],
                'radNoteGeneral' => [
                    'typeId' => array_search('General note', $termData['noteTypes']['en']),
                ],
                'radNotePhysicalDescription' => [
                    'typeId' => array_search('Physical description', $termData['radNoteTypes']['en']),
                ],
                'radNotePublishersSeries' => [
                    'typeId' => array_search("Publisher's series", $termData['radNoteTypes']['en']),
                ],
                'radNoteRights' => [
                    'typeId' => array_search('Rights', $termData['radNoteTypes']['en']),
                ],
                'radNoteAccompanyingMaterial' => [
                    'typeId' => array_search('Accompanying material', $termData['radNoteTypes']['en']),
                ],
                'radNoteAlphaNumericDesignation' => [
                    'typeId' => array_search('Alpha-numeric designations', $termData['radNoteTypes']['en']),
                ],
                'radNoteEdition' => [
                    'typeId' => array_search('Edition', $termData['radNoteTypes']['en']),
                ],
                'radTitleStatementOfResponsibilityNote' => [
                    'typeId' => array_search('Statements of responsibility', $termData['titleNoteTypes']['en']),
                ],
                'radTitleParallelTitles' => [
                    'typeId' => array_search('Parallel titles and other title information', $termData['titleNoteTypes']['en']),
                ],
                'radTitleSourceOfTitleProper' => [
                    'typeId' => array_search('Source of title proper', $termData['titleNoteTypes']['en']),
                ],
                'radTitleVariationsInTitle' => [
                    'typeId' => array_search('Variations in title', $termData['titleNoteTypes']['en']),
                ],
                'radTitleAttributionsAndConjectures' => [
                    'typeId' => array_search('Attributions and conjectures', $termData['titleNoteTypes']['en']),
                ],
                'radTitleContinues' => [
                    'typeId' => array_search('Continuation of title', $termData['titleNoteTypes']['en']),
                ],
                'radTitleNoteContinuationOfTitle' => [
                    'typeId' => array_search('Continuation of title', $termData['titleNoteTypes']['en']),
                ],
            ],

            // Import columns with values that should be serialized/added as a language property
            'languageMap' => [
                'language' => 'language',
                'languageOfDescription' => 'languageOfDescription',
            ],

            // Import columns with values that should be serialized/added as a script property
            'scriptMap' => [
                'script' => 'script',
                'scriptOfDescription' => 'scriptOfDescription',
            ],

            // These values get stored to the rowStatusVars array
            'variableColumns' => [
                'legacyId',
                'parentId',
                'copyrightStatus',
                'copyrightExpires',
                'copyrightHolder',
                'qubitParentSlug',
                'descriptionStatus',
                'publicationStatus',
                'levelOfDetail',
                'repository',
                'physicalObjectName',
                'physicalObjectLocation',
                'physicalObjectType',
                'physicalStorageLocation',
                'digitalObjectPath',
                'digitalObjectURI',
                'digitalObjectChecksum',
            ],

            // These values get exploded and stored to the rowStatusVars array
            'arrayColumns' => [
                'accessionNumber' => '|',
                'alternativeIdentifiers' => '|',
                'alternativeIdentifierLabels' => '|',

                'nameAccessPoints' => '|',
                'nameAccessPointHistories' => '|',
                'placeAccessPoints' => '|',
                'placeAccessPointHistories' => '|',
                'subjectAccessPoints' => '|',
                'subjectAccessPointScopes' => '|',
                'genreAccessPoints' => '|',

                'eventActors' => '|',
                'eventActorHistories' => '|',
                'eventTypes' => '|',
                'eventPlaces' => '|',
                'eventDates' => '|',
                'eventStartDates' => '|',
                'eventEndDates' => '|',
                'eventDescriptions' => '|',

                // These columns are for backwards compatibility
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
            ],

            'updatePreparationLogic' => function (&$self) use ($options) {
                $this->deleteDigitalObjectIfUpdatingAndNotKeeping($self);
            },

            // Import logic to execute before saving information object
            'preSaveLogic' => function (&$self) use ($options) {
                $notImportingTranslation = $self->object instanceof \QubitInformationObject;

                // If importing a translation, warn of values in inappropriate columns and don't import related data
                if (!$notImportingTranslation) {
                    // Determine which possible columns are allowable
                    $translationObjectProperties = [];
                    $dbMap = \Propel::getDatabaseMap(\QubitInformationObjectI18n::DATABASE_NAME);
                    $translationTable = $dbMap->getTable(\QubitInformationObjectI18n::TABLE_NAME);
                    $columns = $translationTable->getColumns();

                    foreach ($columns as $column) {
                        array_push($translationObjectProperties, $column->getPhpName());
                    }

                    // Determine which columns being used should be ignored
                    $allowedColumns = ['legacyId'] + $translationObjectProperties;
                    $ignoredColumns = [];

                    foreach ($self->rowStatusVars as $columnName => $value) {
                        if (!empty($value) && false === array_search($columnName, $allowedColumns)) {
                            array_push($ignoredColumns, $columnName);
                        }
                    }

                    // Show warning about ignored columns
                    if (count($ignoredColumns)) {
                        $errorMessage = 'Ignoring values in column(s) incompatible with translation rows: ';
                        $errorMessage .= implode(' ', $ignoredColumns);
                        echo $self->logError($errorMessage);
                    }

                    return;
                }

                // Set repository if not importing an QubitInformationObjectI18n translation row
                if ($notImportingTranslation && isset($self->rowStatusVars['repository']) && $self->rowStatusVars['repository']) {
                    $repository = $self->createOrFetchRepository($self->rowStatusVars['repository']);
                    $self->object->repositoryId = $repository->id;
                }

                // Set level of detail
                if (isset($self->rowStatusVars['levelOfDetail']) && 0 < strlen($self->rowStatusVars['levelOfDetail'])) {
                    $levelOfDetail = trim($self->rowStatusVars['levelOfDetail']);

                    $levelOfDetailTermId = self::arraySearchCaseInsensitive($levelOfDetail, $self->status['levelOfDetailTypes'][$self->columnValue('culture')]);
                    if (false === $levelOfDetailTermId) {
                        echo "\nTerm {$levelOfDetail} not found in description details level taxonomy, creating it...\n";

                        $newTerm = \QubitTerm::createTerm(
                            \QubitTaxonomy::DESCRIPTION_DETAIL_LEVEL_ID,
                            $levelOfDetail,
                            $self->columnValue('culture')
                        );

                        $levelOfDetailTermId = $newTerm->id;
                        $self->status['levelOfDetailTypes'] = self::refreshTaxonomyTerms(\QubitTaxonomy::DESCRIPTION_DETAIL_LEVEL_ID);
                    }

                    $self->object->descriptionDetailId = $levelOfDetailTermId;
                }

                // Add alternative identifiers
                if (
                    array_key_exists('alternativeIdentifiers', $self->rowStatusVars)
                    && array_key_exists('alternativeIdentifierLabels', $self->rowStatusVars)
                ) {
                    self::setAlternativeIdentifiers(
                        $self->object,
                        $self->rowStatusVars['alternativeIdentifiers'],
                        $self->rowStatusVars['alternativeIdentifierLabels']
                    );
                }

                // Set description status
                if (isset($self->rowStatusVars['descriptionStatus']) && 0 < strlen($self->rowStatusVars['descriptionStatus'])) {
                    $descStatus = trim($self->rowStatusVars['descriptionStatus']);
                    $statusTermId = self::arraySearchCaseInsensitive($descStatus, $self->status['descriptionStatusTypes'][$self->columnValue('culture')]);

                    if (false !== $statusTermId) {
                        $self->object->descriptionStatusId = $statusTermId;
                    } else {
                        echo "\nTerm {$descStatus} not found in description status taxonomy, creating it...\n";

                        $newTerm = \QubitTerm::createTerm(\QubitTaxonomy::DESCRIPTION_STATUS_ID, $descStatus, $self->columnValue('culture'));
                        $self->status['descriptionStatusTypes'] = self::refreshTaxonomyTerms(\QubitTaxonomy::DESCRIPTION_STATUS_ID);

                        $self->object->descriptionStatusId = $newTerm->id;
                    }
                }

                // Set publication status
                if (isset($self->rowStatusVars['publicationStatus']) && 0 < strlen($self->rowStatusVars['publicationStatus'])) {
                    $pubStatusTermId = self::arraySearchCaseInsensitive(
                        $self->rowStatusVars['publicationStatus'],
                        $self->status['pubStatusTypes'][trim($self->columnValue('culture'))]
                    );

                    if (!$pubStatusTermId) {
                        echo "\nPublication status: '" . $self->rowStatusVars['publicationStatus'] . "' is invalid. Using default.\n";
                        $pubStatusTermId = $self->status['defaultStatusId'];
                    }
                } else {
                    $pubStatusTermId = $self->status['defaultStatusId'];
                }

                $self->object->setPublicationStatus($pubStatusTermId);

                if (isset($self->rowStatusVars['qubitParentSlug']) && $self->rowStatusVars['qubitParentSlug']) {
                    $parentId = $self->getIdCorrespondingToSlug($self->rowStatusVars['qubitParentSlug']);
                } else {
                    if (!isset($self->rowStatusVars['parentId']) || !$self->rowStatusVars['parentId']) {
                        // Don't overwrite valid parentId when importing an QubitInformationObjectI18n translation row
                        if ($notImportingTranslation && !isset($self->object->parentId)) {
                            $parentId = $self->status['defaultParentId'];
                        }
                    } else {
                        if (
                            $mapEntry = $self->fetchKeymapEntryBySourceAndTargetName(
                                $self->rowStatusVars['parentId'],
                                $self->getStatus('sourceName'),
                                'information_object'
                            )
                        ) {
                            $parentId = $mapEntry->target_id;
                        } elseif (null !== \QubitInformationObject::getById($self->rowStatusVars['parentId'])) {
                            $parentId = $self->rowStatusVars['parentId'];
                        } else {
                            $parentId = $self->status['defaultParentId'];

                            $error = sprintf(
                                'legacyId %s: could not find parentId %s in key_map table or existing data. Setting parent to default...',
                                $self->rowStatusVars['legacyId'],
                                $self->rowStatusVars['parentId']
                            );

                            echo $self->logError($error);
                        }
                    }
                }

                if (isset($parentId) && $notImportingTranslation) {
                    $self->object->parentId = $parentId;
                }

                $self->object->indexOnSave = false;
            },

            // Import logic to execute after saving information object
            'postSaveLogic' => function (&$self) use ($options) {
                if (!$self->object->id) {
                    throw new \sfException('Information object save failed');
                }

                // If importing a translation row, don't deal with related data
                if ($self->object instanceof \QubitInformationObjectI18n) {
                    return;
                }

                // Add keymap entry if not in round trip mode
                if (!$self->roundtrip) {
                    $self->createKeymapEntry($self->getStatus('sourceName'), $self->rowStatusVars['legacyId']);
                }

                // Inherit repository, instead of duplicating the association to it, if applicable
                if ($self->object instanceof \QubitInformationObject && $self->object->canInheritRepository($self->object->repositoryId)) {
                    // Use raw SQL since we don't want an entire save() here.
                    $sql = 'UPDATE information_object SET repository_id = NULL WHERE id = ?';
                    \QubitPdo::prepareAndExecute($sql, [$self->object->id]);

                    $self->object->repositoryId = null;
                }

                // Add physical objects
                self::importPhysicalObjects($self);

                // Add subject access points
                $accessPointColumns = [
                    'subjectAccessPoints' => \QubitTaxonomy::SUBJECT_ID,
                    'placeAccessPoints' => \QubitTaxonomy::PLACE_ID,
                    'genreAccessPoints' => \QubitTaxonomy::GENRE_ID,
                ];

                foreach ($accessPointColumns as $columnName => $taxonomyId) {
                    if (isset($self->rowStatusVars[$columnName])) {
                        // Create/relate terms from array of term names.
                        $self->createOrFetchTermAndAddRelation($taxonomyId, $self->rowStatusVars[$columnName]);

                        $index = 0;
                        foreach ($self->rowStatusVars[$columnName] as $subject) {
                            if ($subject) {
                                $scope = false;
                                if (isset($self->rowStatusVars['subjectAccessPointScopes'][$index])) {
                                    $scope = $self->rowStatusVars['subjectAccessPointScopes'][$index];
                                }

                                if ($scope) {
                                    // Get term ID
                                    $query = "SELECT t.id FROM term t \r
                                        INNER JOIN term_i18n i ON t.id=i.id \r
                                        WHERE i.name=? AND t.taxonomy_id=? AND culture='en'";

                                    $statement = \QubitFlatfileImport::sqlQuery(
                                        $query,
                                        [$subject, $taxonomyId]
                                    );

                                    $result = $statement->fetch(\PDO::FETCH_OBJ);

                                    if ($result) {
                                        $termId = $result->id;

                                        // Check if a scope note already exists for this term
                                        $query = 'SELECT n.id FROM note n INNER JOIN note_i18n i ON n.id=i.id WHERE n.object_id=? AND n.type_id=?';

                                        $statement = \QubitFlatfileImport::sqlQuery(
                                            $query,
                                            [$termId, \QubitTerm::SCOPE_NOTE_ID]
                                        );

                                        $result = $statement->fetch(\PDO::FETCH_OBJ);

                                        if (!$result) {
                                            // Add scope note if it doesn't exist
                                            $note = new \QubitNote();
                                            $note->objectId = $termId;
                                            $note->typeId = \QubitTerm::SCOPE_NOTE_ID;
                                            $note->content = $self->content($scope);
                                            $note->scope = 'QubitTerm';
                                            $note->save();
                                        }
                                    } else {
                                        throw new \sfException('Could not find term "' . $subject . '"');
                                    }
                                }
                            }
                            ++$index;
                        }
                    }
                }

                // Add name access points
                if (isset($self->rowStatusVars['nameAccessPoints'])) {
                    $index = 0;
                    foreach ($self->rowStatusVars['nameAccessPoints'] as $name) {
                        // Skip blank names
                        if ($name) {
                            $actorOptions = [];
                            if (isset($self->rowStatusVars['nameAccessPointHistories'][$index])) {
                                $actorOptions['history'] = $self->rowStatusVars['nameAccessPointHistories'][$index];
                            }

                            if (null !== $repo = $self->object->getRepository(['inherit' => true])) {
                                $actorOptions['repositoryId'] = $repo->id;
                            }

                            $actor = $self->createOrFetchAndUpdateActorForIo($name, $actorOptions);
                            $self->createRelation($self->object->id, $actor->id, \QubitTerm::NAME_ACCESS_POINT_ID);
                        }

                        ++$index;
                    }
                }

                // Add accessions
                if (
                    isset($self->rowStatusVars['accessionNumber'])
                    && count($self->rowStatusVars['accessionNumber'])
                ) {
                    foreach ($self->rowStatusVars['accessionNumber'] as $accessionNumber) {
                        // Attempt to fetch keymap entry
                        $accessionMapEntry = $self->fetchKeymapEntryBySourceAndTargetName(
                            $accessionNumber,
                            $self->getStatus('sourceName'),
                            'accession'
                        );

                        // If no entry found, create accession and entry
                        if (!$accessionMapEntry) {
                            $criteria = new \Criteria();
                            $criteria->add(\QubitAccession::IDENTIFIER, $accessionNumber);

                            if (null === $accession = \QubitAccession::getone($criteria)) {
                                echo "\nCreating accession # " . $accessionNumber . "\n";

                                // Create new accession
                                $accession = new \QubitAccession();
                                $accession->identifier = $accessionNumber;
                                $accession->save();

                                // Create keymap entry for accession
                                $self->createKeymapEntry($self->getStatus('sourceName'), $accessionNumber, $accession);
                            }

                            $accessionId = $accession->id;
                        } else {
                            $accessionId = $accessionMapEntry->target_id;
                        }

                        echo "\nAssociating accession # " . $accessionNumber . ' with ' . $self->object->title . "\n";

                        // Add relationship between information object and accession
                        $self->createRelation($self->object->id, $accessionId, \QubitTerm::ACCESSION_ID);
                    }
                }

                // Add material-related term relation
                if (isset($self->rowStatusVars['radGeneralMaterialDesignation'])) {
                    foreach ($self->rowStatusVars['radGeneralMaterialDesignation'] as $material) {
                        $self->createObjectTermRelation($self->object->id, $material);
                    }
                }

                // Add copyright info
                if (isset($self->rowStatusVars['copyrightStatus']) && $self->rowStatusVars['copyrightStatus']) {
                    switch (strtolower($self->rowStatusVars['copyrightStatus'])) {
                        case 'under copyright':
                            print 'Adding rights for ' . $self->object->title . "...\n";
                            $rightsHolderId = false;
                            $rightsHolderNames = explode('|', $self->rowStatusVars['copyrightHolder']);

                            if ($self->rowStatusVars['copyrightExpires']) {
                                $endDates = explode('|', $self->rowStatusVars['copyrightExpires']);
                            }

                            foreach ($rightsHolderNames as $index => $rightsHolderName) {
                                $rightsHolderName = ($rightsHolderName) ? $rightsHolderName : 'Unknown';
                                $rightsHolder = $self->createOrFetchRightsHolder($rightsHolderName);
                                $rightsHolderId = $rightsHolder->id;

                                $rightsHolderName = trim(strtolower($rightsHolderName));
                                if ('city of vancouver' == $rightsHolderName || 0 === strpos($rightsHolderName, 'city of vancouver')) {
                                    $restriction = 1;
                                } else {
                                    $restriction = 0;
                                }

                                $rightAndRelation = [
                                    'restriction' => $restriction,
                                    'basisId' => \QubitTerm::RIGHT_BASIS_COPYRIGHT_ID,
                                    'actId' => array_search(
                                        'Replicate',
                                        $self->getStatus('copyrightActTypes')
                                    ),
                                    'copyrightStatusId' => array_search(
                                        'Under copyright',
                                        $self->getStatus('copyrightStatusTypes')
                                    ),
                                ];

                                if (isset($endDates)) {
                                    $rightAndRelation['endDate'] = (count($endDates) == count($rightsHolderNames))
                                        ? $endDates[$index]
                                        : $endDates[0];

                                    if (!is_numeric($rightAndRelation['endDate'])) {
                                        throw new \sfException(
                                            'Copyright expiry ' . $rightAndRelation['endDate'] . ' is invalid.'
                                        );
                                    }
                                }

                                if ($rightsHolderId) {
                                    $rightAndRelation['rightsHolderId'] = $rightsHolderId;
                                }

                                $self->createRightAndRelation($rightAndRelation);
                            }

                            break;

                        case 'unknown':
                            $rightsHolder = $self->createOrFetchRightsHolder('Unknown');
                            $rightsHolderId = $rightsHolder->id;

                            $rightAndRelation = [
                                'rightsHolderId' => $rightsHolderId,
                                'restriction' => 0,
                                'basisId' => \QubitTerm::RIGHT_BASIS_COPYRIGHT_ID,
                                'actId' => array_search(
                                    'Replicate',
                                    $self->getStatus('copyrightActTypes')
                                ),
                                'copyrightStatusId' => array_search(
                                    'Unknown',
                                    $self->getStatus('copyrightStatusTypes')
                                ),
                            ];

                            if ($self->rowStatusVars['copyrightExpires']) {
                                $rightAndRelation['endDate'] = $self->rowStatusVars['copyrightExpires'];
                            }

                            $self->createRightAndRelation($rightAndRelation);

                            break;

                        case 'public domain':
                            $rightAndRelation = [
                                'restriction' => 1,
                                'basisId' => \QubitTerm::RIGHT_BASIS_COPYRIGHT_ID,
                                'actId' => array_search(
                                    'Replicate',
                                    $self->getStatus('copyrightActTypes')
                                ),
                                'copyrightStatusId' => array_search(
                                    'Public domain',
                                    $self->getStatus('copyrightStatusTypes')
                                ),
                            ];

                            if ($self->rowStatusVars['copyrightExpires']) {
                                $rightAndRelation['endDate'] = $self->rowStatusVars['copyrightExpires'];
                            }

                            $self->createRightAndRelation($rightAndRelation);

                            break;

                        default:
                            throw new \sfException(
                                'Copyright status "'
                                . $self->rowStatusVars['copyrightStatus']
                                . '" not handled: adjust script or import data'
                            );
                    }
                }

                // Add events
                self::importEvents($self);

                // Import digital object
                $this->importDigitalObject($self);

                // Re-index to add translations and related resources
                if (!$self->searchIndexingDisabled) {
                    $node = new \arElasticSearchInformationObjectPdo($self->object->id);
                    \QubitSearch::getInstance()->addDocument($node->serialize(), 'QubitInformationObject');
                }

                // Reduce memory usage
                \Qubit::clearClassCaches();
            },
        ]);

        $import->searchIndexingDisabled = ($options['index']) ? false : true;

        // Disable nested set update per row
        $import->disableNestedSetUpdating = true;

        $import->setUpdateOptions($options);

        // Convert content with | characters to a bulleted list
        $import->contentFilterLogic = function ($text) {
            return (substr_count($text, '|')) ? '* ' . str_replace('|', "\n* ", $text) : $text;
        };

        $import->addColumnHandler('levelOfDescription', function ($self, $data) {
            $self->object->setLevelOfDescriptionByName(trim($data));
        });

        // Map value to taxonomy term name and take note of taxonomy term's ID
        $import->addColumnHandler('radGeneralMaterialDesignation', function ($self, $data) {
            if ($data) {
                $data = array_map('trim', explode('|', $data));

                foreach ($data as $value) {
                    $value = trim($value);
                    $materialTypeId = self::arraySearchCaseInsensitive($value, $self->status['materialTypes'][$self->columnValue('culture')]);

                    if (false !== $materialTypeId) {
                        $self->rowStatusVars['radGeneralMaterialDesignation'][] = $materialTypeId;
                    } else {
                        echo "\nTerm {$value} not found in material type taxonomy, creating it...\n";

                        $newTerm = \QubitTerm::createTerm(\QubitTaxonomy::MATERIAL_TYPE_ID, $value, $self->columnValue('culture'));
                        $self->status['materialTypes'] = self::refreshTaxonomyTerms(\QubitTaxonomy::MATERIAL_TYPE_ID);

                        $self->rowStatusVars['radGeneralMaterialDesignation'][] = $newTerm->id;
                    }
                }
            }
        });

        $import->csv($fh, $skipRows);

        // Rebuild entire nested set for IOs
        if (!$options['skip-nested-set-build']) {
            $this->updateIosNestedSet();
        }

        $this->success('CSV import complete.');

        return 0;
    }

    // ─── Helper methods from csvImportBaseTask ───────────────────────

    /**
     * If updating, delete existing digital object if a path or URI has been specified
     * and not keeping digital objects.
     */
    protected function deleteDigitalObjectIfUpdatingAndNotKeeping($self): void
    {
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
    }

    /**
     * If getDigitalObject() is null, and a digital object URI or path is specified,
     * attempt to import a single digital object. If both are provided, URI is preferred.
     */
    protected function importDigitalObject($self): void
    {
        if (null === $self->object->getDigitalObject()) {
            if ($uri = $self->rowStatusVars['digitalObjectURI']) {
                $this->addDigitalObjectFromURI($self, $uri);
            } elseif ($path = $self->rowStatusVars['digitalObjectPath']) {
                $this->addDigitalObjectFromPath($self, $path);
            }
        }
    }

    protected function addDigitalObjectFromURI($self, $uri): void
    {
        $do = new \QubitDigitalObject();
        $do->object = $self->object;
        $do->indexOnSave = false;

        $options = [];
        if ($self->status['options']['skip-derivatives']) {
            $do->createDerivatives = false;
        } else {
            $options = ['downloadRetries' => 2];
        }

        try {
            $do->importFromURI($uri, $options);
            $do->save();
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    protected function addDigitalObjectFromPath($self, $path): void
    {
        if (!is_readable($path)) {
            $this->warning("Cannot read digital object path. Skipping creation of digital object ({$path})");

            return;
        }

        $do = new \QubitDigitalObject();
        $do->usageId = \QubitTerm::MASTER_ID;
        $do->object = $self->object;
        $do->indexOnSave = false;

        if ($self->status['options']['skip-derivatives']) {
            $do->createDerivatives = false;
        }

        $do->assets[] = new \QubitAsset($path);

        try {
            $do->save();
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    public static function importAlternateFormsOfName($self): void
    {
        $typeIds = [
            'parallel' => \QubitTerm::PARALLEL_FORM_OF_NAME_ID,
            'standardized' => \QubitTerm::STANDARDIZED_FORM_OF_NAME_ID,
            'other' => \QubitTerm::OTHER_FORM_OF_NAME_ID,
        ];

        foreach ($typeIds as $typeName => $typeId) {
            $columnName = $typeName . 'FormsOfName';

            if (!empty($self->arrayColumns[$columnName])) {
                $aliases = $self->rowStatusVars[$columnName];

                foreach ($aliases as $alias) {
                    $otherName = new \QubitOtherName();
                    $otherName->objectId = $self->object->id;
                    $otherName->name = $alias;
                    $otherName->typeId = $typeId;
                    $otherName->culture = $self->columnValue('culture');
                    $otherName->save();
                }
            }
        }
    }

    public static function importPhysicalObjects($self): void
    {
        if (isset($self->rowStatusVars['physicalObjectName'])
            && $self->rowStatusVars['physicalObjectName']) {
            $names = explode('|', $self->rowStatusVars['physicalObjectName']);
            $locations = explode('|', $self->rowStatusVars['physicalObjectLocation']);
            $types = (isset($self->rowStatusVars['physicalObjectType']))
                ? explode('|', $self->rowStatusVars['physicalObjectType'])
                : [];

            foreach ($names as $index => $name) {
                if ($self->rowStatusVars['physicalObjectLocation']) {
                    if (isset($locations[$index])) {
                        $location = $locations[$index];
                    } else {
                        $location = $locations[0];
                    }
                } else {
                    $location = '';
                }

                if ($self->rowStatusVars['physicalObjectType']) {
                    if (isset($types[$index])) {
                        $type = $types[$index];
                    } else {
                        $type = $types[0];
                    }
                } else {
                    $type = 'Box';
                }

                $physicalObjectTypeId = self::arraySearchCaseInsensitive($type, $self->status['physicalObjectTypes'][$self->columnValue('culture')]);

                if (false === $physicalObjectTypeId) {
                    echo "\nTerm {$type} not found in physical object type taxonomy, creating it...\n";

                    $newTerm = \QubitTerm::createTerm(\QubitTaxonomy::PHYSICAL_OBJECT_TYPE_ID, $type, $self->columnValue('culture'));
                    $self->status['physicalObjectTypes'] = self::refreshTaxonomyTerms(\QubitTaxonomy::PHYSICAL_OBJECT_TYPE_ID);

                    $physicalObjectTypeId = $newTerm->id;
                }

                $container = $self->createOrFetchPhysicalObject($name, $location, $physicalObjectTypeId);

                $self->createRelation($container->id, $self->object->id, \QubitTerm::HAS_PHYSICAL_OBJECT_ID);
            }
        }
    }

    public static function importEvents(&$import): void
    {
        $events = [];

        foreach (
            [
                '2.1' => [
                    'actorName' => 'creators',
                    'actorHistory' => 'creatorHistories',
                    'date' => 'creatorDates',
                    'startDate' => 'creatorDatesStart',
                    'endDate' => 'creatorDatesEnd',
                    'description' => 'creatorDateNotes',
                    'type' => '-',
                    'place' => '-',
                ],
                '2.2' => [
                    'actorName' => 'creators',
                    'actorHistory' => 'creatorHistories',
                    'date' => 'creationDates',
                    'startDate' => 'creationDatesStart',
                    'endDate' => 'creationDatesEnd',
                    'description' => 'creationDateNotes',
                    'type' => 'creationDatesType',
                    'place' => '-',
                ],
                '2.3' => [
                    'actorName' => 'eventActors',
                    'actorHistory' => 'eventActorHistories',
                    'date' => 'eventDates',
                    'startDate' => 'eventStartDates',
                    'endDate' => 'eventEndDates',
                    'description' => 'eventDescriptions',
                    'type' => 'eventTypes',
                    'place' => 'eventPlaces',
                ],
            ] as $version => $propertyColumns
        ) {
            $index = 0;
            while (
                isset($import->rowStatusVars[$propertyColumns['actorName']][$index])
                || isset($import->rowStatusVars[$propertyColumns['actorHistory']][$index])
                || isset($import->rowStatusVars[$propertyColumns['date']][$index])
                || isset($import->rowStatusVars[$propertyColumns['startDate']][$index])
                || isset($import->rowStatusVars[$propertyColumns['endDate']][$index])
                || isset($import->rowStatusVars[$propertyColumns['description']][$index])
                || isset($import->rowStatusVars[$propertyColumns['type']][$index])
                || isset($import->rowStatusVars[$propertyColumns['place']][$index])
            ) {
                if (
                    '2.1' == $version
                    && !isset($import->rowStatusVars[$propertyColumns['date']][$index])
                    && !isset($import->rowStatusVars[$propertyColumns['startDate']][$index])
                    && !isset($import->rowStatusVars[$propertyColumns['endDate']][$index])
                    && !isset($import->rowStatusVars[$propertyColumns['description']][$index])
                ) {
                    ++$index;

                    continue;
                }

                $eventData = [];
                foreach ($propertyColumns as $property => $column) {
                    if (
                        isset($import->rowStatusVars[$column][$index])
                        && 'NULL' != $import->rowStatusVars[$column][$index]
                    ) {
                        $eventData[$property] = $import->rowStatusVars[$column][$index];
                        $import->rowStatusVars[$column][$index] = 'NULL';
                    }
                }

                if (count($eventData)) {
                    $events[] = $eventData;
                }

                ++$index;
            }
        }

        // Create events
        foreach ($events as $eventData) {
            if (!isset($eventData['type'])) {
                $eventTypeId = (string) \QubitTerm::CREATION_ID;
            } else {
                $typeTerm = $import->createOrFetchTerm(\QubitTaxonomy::EVENT_TYPE_ID, $eventData['type'], $import->columnValue('culture'));
                $eventTypeId = $typeTerm->id;

                unset($eventData['type']);
            }

            if (
                $import->matchAndUpdate
                && null !== $event = self::matchExistingEvent($import->object->id, $eventTypeId, $eventData['actorName'])
            ) {
                $eventData['eventId'] = $event->id;
            }

            $eventData['culture'] = $import->columnValue('culture');

            $import->createOrUpdateEvent($eventTypeId, $eventData);
        }
    }

    public static function matchExistingEvent($objectId, $typeId, $actorName)
    {
        $criteria = new \Criteria();
        $criteria->add(\QubitEvent::TYPE_ID, $typeId);
        $criteria->add(\QubitEvent::OBJECT_ID, $objectId);

        if (!isset($actorName)) {
            $criteria->add(\QubitEvent::ACTOR_ID, null, \Criteria::ISNULL);
        } else {
            if (null !== $actor = \QubitActor::getByAuthorizedFormOfName($actorName)) {
                $criteria->add(\QubitEvent::ACTOR_ID, $actor->id);
            } else {
                return;
            }
        }

        if (null !== $event = \QubitEvent::getOne($criteria)) {
            return $event;
        }
    }

    public static function arraySearchCaseInsensitive($search, $array)
    {
        return array_search(strtolower($search), array_map('strtolower', $array));
    }

    public static function setAlternativeIdentifiers($io, $altIds, $altIdLabels): void
    {
        if (count($altIdLabels) !== count($altIds)) {
            throw new \sfException('Number of alternative ids does not match number of alt id labels');
        }

        for ($i = 0; $i < count($altIds); ++$i) {
            $io->addProperty($altIdLabels[$i], $altIds[$i], ['scope' => 'alternativeIdentifiers']);
        }
    }

    public static function refreshTaxonomyTerms($taxonomyId)
    {
        $result = \QubitFlatfileImport::loadTermsFromTaxonomies([$taxonomyId => 'terms']);

        return $result['terms'];
    }

    // ─── Private helpers ─────────────────────────────────────────────

    private function getDefaultParentId(string $sourceName, array $options)
    {
        if ($options['default-parent-slug']) {
            $parentId = \QubitFlatfileImport::getIdCorrespondingToSlug($options['default-parent-slug']);

            if (!$options['quiet']) {
                $this->info("Parent ID of slug {$options['default-parent-slug']} is {$parentId}");
            }
        } elseif ($options['default-legacy-parent-id']) {
            if (
                false === $keyMapEntry = \QubitFlatfileImport::fetchKeymapEntryBySourceAndTargetName(
                    $options['default-legacy-parent-id'],
                    $sourceName,
                    'information_object'
                )
            ) {
                throw new \sfException(
                    'Could not find keymap entry for default legacy parent ID '
                    . $options['default-legacy-parent-id']
                );
            }

            $parentId = $keyMapEntry->target_id;
            $this->info("Using default parent ID {$parentId} (legacy parent ID {$options['default-legacy-parent-id']})");
        } else {
            $parentId = \QubitInformationObject::ROOT_ID;
        }

        return $parentId;
    }

    private function updateIosNestedSet(int $retryCount = 0): void
    {
        try {
            $this->info('Rebuilding nested set for information objects...');
            $cmd = sprintf(
                'php %s/symfony propel:build-nested-set --exclude-tables=term,menu --index',
                escapeshellarg($this->atomRoot)
            );
            $this->passthru($cmd);
        } catch (\PDOException $e) {
            if (1213 == $e->errorInfo[1] && $retryCount < 3) {
                $this->updateIosNestedSet(++$retryCount);
            }

            throw $e;
        }
    }

    private function buildOptionsArray(): array
    {
        return [
            'rows-until-update' => $this->option('rows-until-update'),
            'skip-rows' => $this->option('skip-rows'),
            'error-log' => $this->option('error-log'),
            'source-name' => $this->option('source-name'),
            'default-parent-slug' => $this->option('default-parent-slug'),
            'default-legacy-parent-id' => $this->option('default-legacy-parent-id'),
            'skip-nested-set-build' => $this->hasOption('skip-nested-set-build'),
            'index' => $this->hasOption('index'),
            'update' => $this->option('update'),
            'skip-matched' => $this->hasOption('skip-matched'),
            'skip-unmatched' => $this->hasOption('skip-unmatched'),
            'skip-derivatives' => $this->hasOption('skip-derivatives'),
            'limit' => $this->option('limit'),
            'user-id' => $this->option('user-id'),
            'keep-digital-objects' => $this->hasOption('keep-digital-objects'),
            'roundtrip' => $this->hasOption('roundtrip'),
            'no-confirmation' => $this->hasOption('no-confirmation'),
            'quiet' => $this->hasOption('quiet'),
        ];
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

        if (!$options['source-name']) {
            $this->warning("If you're importing multiple CSV files as part of the same import it's advisable to use the --source-name option to specify a source name.");
        }

        if ($options['limit'] && !$options['update']) {
            throw new \sfException('The --limit option requires the --update option to be present.');
        }

        if ($options['keep-digital-objects'] && 'match-and-update' != trim($options['update'])) {
            throw new \sfException('The --keep-digital-objects option can only be used when --update="match-and-update" is present.');
        }

        if ($options['update']) {
            $validParams = ['match-and-update', 'delete-and-replace'];

            if (!in_array(trim($options['update']), $validParams)) {
                $msg = sprintf('Parameter "%s" is not valid for --update option. ', $options['update']);
                $msg .= sprintf('Valid options are: %s', implode(', ', $validParams));

                throw new \sfException($msg);
            }
        }
    }
}
