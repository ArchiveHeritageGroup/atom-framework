<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\BaseCommand;

/**
 * Import repositories from CSV.
 *
 * Native implementation of the csv:repository-import Symfony task.
 */
class CsvRepositoryImportCommand extends BaseCommand
{
    protected string $name = 'import:csv-repository';
    protected string $description = 'Import repositories from CSV';

    protected string $detailedDescription = <<<'EOF'
    Import CSV repository data into AtoM. Supports contact information, term
    relations (geographic subregions, thematic areas, types), alternate forms
    of name, description status/detail, keymap entries, and search indexing.
    EOF;

    protected function configure(): void
    {
        $this->addArgument('filename', 'The input file (CSV format)', true);

        $this->addOption('rows-until-update', null, 'Output total rows imported every n rows');
        $this->addOption('skip-rows', null, 'Skip n rows before importing');
        $this->addOption('error-log', null, 'File to log errors to');
        $this->addOption('source-name', null, 'Source name to use when inserting keymap entries');
        $this->addOption('index', null, 'Index for search during import');
        $this->addOption('update', null, 'Attempt to update if repository already exists. Valid values: "match-and-update", "delete-and-replace"');
        $this->addOption('skip-matched', null, 'Skip creating new records when existing one matches (without --update)');
        $this->addOption('skip-unmatched', null, 'Skip creating new records if no existing records match (with --update)');
        $this->addOption('upload-limit', null, 'Set the upload limit for repositories getting imported (default: disable uploads)');
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
            'upload-limit' => $this->option('upload-limit'),
        ];

        $skipRows = ($options['skip-rows']) ? $options['skip-rows'] : 0;

        $sourceName = ($options['source-name'])
            ? $options['source-name']
            : basename($filename);

        if (false === $fh = fopen($filename, 'rb')) {
            $this->error('You must specify a valid filename');

            return 1;
        }

        $this->info('Importing repository objects from CSV to AtoM');

        // Load taxonomies into variables to avoid use of magic numbers
        $termData = \QubitFlatfileImport::loadTermsFromTaxonomies([
            \QubitTaxonomy::DESCRIPTION_STATUS_ID => 'descriptionStatusTypes',
            \QubitTaxonomy::DESCRIPTION_DETAIL_LEVEL_ID => 'levelOfDetailTypes',
        ]);

        // Define import
        $import = new \QubitFlatfileImport([
            'context' => \sfContext::getInstance(),

            'className' => 'QubitRepository',

            'rowsUntilProgressDisplay' => $options['rows-until-update'],

            'errorLog' => $options['error-log'],

            'status' => [
                'options' => $options,
                'sourceName' => $sourceName,
                'descriptionStatusTypes' => $termData['descriptionStatusTypes'],
                'levelOfDetailTypes' => $termData['levelOfDetailTypes'],
            ],

            'standardColumns' => [
                'identifier',
                'uploadLimit',
                'authorizedFormOfName',
                'geoculturalContext',
                'holdings',
                'findingAids',
                'openingTimes',
                'history',
                'mandates',
                'internalStructures',
                'collectingPolicies',
                'buildings',
                'accessConditions',
                'disabledAccess',
                'researchServices',
                'reproductionServices',
                'publicFacilities',
                'culture',
            ],

            'columnMap' => [
                'descriptionIdentifier' => 'descIdentifier',
                'institutionIdentifier' => 'descInstitutionIdentifier',
                'descriptionRules' => 'descRules',
                'descriptionRevisionHistory' => 'descRevisionHistory',
                'descriptionSources' => 'descSources',
            ],

            'termRelations' => [
                'geographicSubregions' => \QubitTaxonomy::GEOGRAPHIC_SUBREGION_ID,
                'thematicAreas' => \QubitTaxonomy::THEMATIC_AREA_ID,
                'types' => \QubitTaxonomy::REPOSITORY_TYPE_ID,
            ],

            'noteMap' => [
                'maintenanceNote' => ['typeId' => \QubitTerm::MAINTENANCE_NOTE_ID],
            ],

            'languageMap' => [
                'language' => 'language',
            ],

            'scriptMap' => [
                'script' => 'script',
            ],

            'variableColumns' => [
                'contactPerson',
                'streetAddress',
                'city',
                'region',
                'country',
                'postalCode',
                'telephone',
                'email',
                'fax',
                'website',
                'notes',
                'descriptionStatus',
                'levelOfDetail',
                'legacyId',
            ],

            'arrayColumns' => [
                'parallelFormsOfName' => '|',
                'otherFormsOfName' => '|',
                'script' => '|',
            ],

            'preSaveLogic' => function (&$self) use ($options) {
                if (isset($options['upload-limit']) && !isset($self->object->uploadLimit)) {
                    $self->object->uploadLimit = $options['upload-limit'];
                }

                // Handle description status
                $self->object->descStatusId = $self->createOrFetchTermIdFromName(
                    'description status',
                    $self->rowStatusVars['descriptionStatus'],
                    $self->columnValue('culture'),
                    $self->status['descriptionStatusTypes'],
                    \QubitTaxonomy::DESCRIPTION_STATUS_ID
                );

                // Handle description detail
                $self->object->descDetailId = $self->createOrFetchTermIdFromName(
                    'description detail',
                    $self->rowStatusVars['levelOfDetail'],
                    $self->columnValue('culture'),
                    $self->status['levelOfDetailTypes'],
                    \QubitTaxonomy::DESCRIPTION_DETAIL_LEVEL_ID
                );
            },

            'postSaveLogic' => function (&$self) {
                CsvImportCommand::importAlternateFormsOfName($self);

                // Check if any contact information data exists
                $addContactInfo = false;
                $contactInfoFields = ['contactPerson', 'streetAddress', 'city', 'region', 'postalCode', 'country', 'telephone', 'email', 'fax', 'website'];
                foreach ($contactInfoFields as $field) {
                    if (!empty($self->rowStatusVars[$field])) {
                        $addContactInfo = true;

                        break;
                    }
                }

                if ($addContactInfo) {
                    // Try to get existing contact information
                    $criteria = new \Criteria();
                    $criteria->add(\QubitContactInformation::ACTOR_ID, $self->object->id);
                    $contactInfo = \QubitContactInformation::getOne($criteria);

                    if (!isset($contactInfo)) {
                        $contactInfo = new \QubitContactInformation();
                        $contactInfo->actorId = $self->object->id;
                    }

                    foreach ($contactInfoFields as $field) {
                        // Don't overwrite/add blank fields
                        if (!empty($self->rowStatusVars[$field])) {
                            if ('country' == $field) {
                                $countryCode = \QubitFlatfileImport::normalizeCountryAsCountryCode($self->rowStatusVars[$field]);
                                if (null === $countryCode) {
                                    echo sprintf("Could not find country or country code matching '%s'\n", $self->rowStatusVars[$field]);
                                } else {
                                    $contactInfo->countryCode = $countryCode;
                                }
                            } else {
                                $contactInfo->{$field} = $self->rowStatusVars[$field];
                            }
                        }
                    }

                    $contactInfo->culture = $self->columnValue('culture');
                    $contactInfo->save();
                }

                // Add keymap entry
                if (!empty($self->rowStatusVars['legacyId'])) {
                    $self->createKeymapEntry($self->getStatus('sourceName'), $self->rowStatusVars['legacyId']);
                }

                // Re-index to add related resources
                if (!$self->searchIndexingDisabled) {
                    \QubitSearch::getInstance()->update($self->object);
                }
            },
        ]);

        // Allow search indexing to be enabled via a CLI option
        $import->searchIndexingDisabled = ($options['index']) ? false : true;

        // Set update, limit and skip options
        $import->setUpdateOptions($options);

        $import->csv($fh, $skipRows);

        $this->success('Imported repositories successfully!');

        return 0;
    }
}
