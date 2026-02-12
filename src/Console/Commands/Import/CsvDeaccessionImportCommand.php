<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\BaseCommand;

/**
 * Import deaccessions from CSV.
 *
 * Native implementation of the csv:deaccession-import Symfony task.
 */
class CsvDeaccessionImportCommand extends BaseCommand
{
    protected string $name = 'import:csv-deaccession';
    protected string $description = 'Import deaccessions from CSV';

    protected string $detailedDescription = <<<'EOF'
    Import CSV deaccession data into AtoM. Links deaccession records to existing
    accessions by accession number. Supports duplicate detection.
    EOF;

    protected function configure(): void
    {
        $this->addArgument('filename', 'The input file (CSV format)', true);

        $this->addOption('rows-until-update', null, 'Output total rows imported every n rows');
        $this->addOption('skip-rows', null, 'Skip n rows before importing');
        $this->addOption('error-log', null, 'File to log errors to');
        $this->addOption('ignore-duplicates', null, 'Load all records from CSV, including duplicates');
    }

    protected function handle(): int
    {
        $filename = $this->argument('filename');
        $options = [
            'rows-until-update' => $this->option('rows-until-update'),
            'skip-rows' => $this->option('skip-rows'),
            'error-log' => $this->option('error-log'),
            'ignore-duplicates' => $this->hasOption('ignore-duplicates'),
        ];

        $skipRows = ($options['skip-rows']) ? $options['skip-rows'] : 0;

        if (false === $fh = fopen($filename, 'rb')) {
            $this->error('You must specify a valid filename');

            return 1;
        }

        $termData = \QubitFlatfileImport::loadTermsFromTaxonomies([
            \QubitTaxonomy::DEACCESSION_SCOPE_ID => 'scopeTypes',
        ]);

        $import = new \QubitFlatfileImport([
            'context' => \sfContext::getInstance(),
            'rowsUntilProgressDisplay' => $options['rows-until-update'],
            'errorLog' => $options['error-log'],

            'status' => [
                'scopeTypes' => $termData['scopeTypes'],
                'options' => $options,
            ],

            'standardColumns' => [
                'date',
                'description',
                'extent',
                'reason',
            ],

            'columnMap' => [
                'deaccessionNumber' => 'identifier',
            ],

            'variableColumns' => [
                'scope',
                'accessionNumber',
            ],

            'rowInitLogic' => function (&$self) {
                $accessionIdentifier = $self->rowStatusVars['accessionNumber'];

                $accessionQueryStatement = $self->sqlQuery(
                    'SELECT id FROM accession WHERE identifier=?',
                    $params = [$accessionIdentifier]
                );

                $result = $accessionQueryStatement->fetch(\PDO::FETCH_OBJ);

                if ($result) {
                    $self->object = new \QubitDeaccession();
                    $self->object->accessionId = $result->id;
                } else {
                    $error = 'Skipping. Match not found for accession number: ' . $accessionIdentifier;
                    echo $self->logError($error);
                }
            },

            'preSaveLogic' => function (&$self) {
                $ignoreDuplicates = ($self->status['options']['ignore-duplicates']) ? true : false;
                $createDeaccession = $ignoreDuplicates;

                if (!$ignoreDuplicates) {
                    $deaccessionQueryStatement = $self->sqlQuery(
                        'SELECT deaccession.id FROM deaccession'
                        . ' JOIN deaccession_i18n ON deaccession_i18n.id = deaccession.id'
                        . ' WHERE deaccession.identifier=?'
                        . ' AND deaccession.date=?'
                        . ' AND deaccession.scope_id=?'
                        . ' AND deaccession_i18n.description=?'
                        . ' AND deaccession_i18n.extent=?'
                        . ' AND deaccession_i18n.reason=?'
                        . ' AND deaccession.source_culture=?',
                        $params = [
                            $self->object->identifier,
                            $self->object->date,
                            $self->object->scopeId,
                            $self->object->description,
                            $self->object->extent,
                            $self->object->reason,
                            $self->object->culture,
                        ]
                    );
                    $deaccessionResult = $deaccessionQueryStatement->fetch(\PDO::FETCH_OBJ);

                    if (!$deaccessionResult) {
                        $createDeaccession = true;
                    }
                }

                if (!$createDeaccession) {
                    $self->object = null;
                    $error = 'Skipping duplicate deaccession: ' . $self->rowStatusVars['accessionNumber'];
                    echo $self->logError($error);
                }
            },

            'saveLogic' => function (&$self) {
                if ('QubitDeaccession' == get_class($self->object) && isset($self->object) && is_object($self->object)) {
                    $self->object->save();
                }
            },
        ]);

        $import->addColumnHandler('scope', function ($self, $data) {
            if ($data && isset($self->object) && $self->object instanceof \QubitDeaccession) {
                $self->object->scopeId = $self->createOrFetchTermIdFromName(
                    'scope type',
                    trim($data),
                    $self->columnValue('culture'),
                    $self->status['scopeTypes'],
                    \QubitTaxonomy::DEACCESSION_SCOPE_ID
                );
            }
        });

        $import->csv($fh, $skipRows);

        $this->success('Deaccession CSV import complete.');

        return 0;
    }
}
