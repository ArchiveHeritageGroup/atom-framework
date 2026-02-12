<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\BaseCommand;

/**
 * Audit CSV import data.
 *
 * Native implementation of the csv:audit-import Symfony task.
 */
class CsvAuditImportCommand extends BaseCommand
{
    protected string $name = 'import:csv-audit';
    protected string $description = 'Audit CSV import data';

    protected string $detailedDescription = <<<'EOF'
    Audit CSV import by checking to make sure a keymap has been created for each
    row. Compares CSV source data against keymap entries to verify import
    completeness. Uses the CsvImportAuditer service class.
    EOF;

    protected function configure(): void
    {
        $this->addArgument('sourcename', 'The source name of the previous import', true);
        $this->addArgument('filename', 'The input file (CSV format)', true);

        $this->addOption('target-name', null, 'Keymap target name');
        $this->addOption('id-column-name', null, 'Name of the ID column in the source CSV file (default: "legacyId")');
    }

    protected function handle(): int
    {
        $sourcename = $this->argument('sourcename');
        $filename = $this->argument('filename');

        $auditOptions = $this->setAuditOptions();

        $auditer = new \CsvImportAuditer($auditOptions);
        $auditer->setSourceName($sourcename);
        $auditer->setFilename($filename);

        $targetName = $this->option('target-name');
        if (!empty($targetName)) {
            $auditer->setTargetName($targetName);
        }

        $this->info(sprintf(
            'Auditing import data from %s...',
            $auditer->getFilename()
        ));

        $auditer->doAudit();

        $this->line(sprintf(
            'Done! Audited %u rows.',
            $auditer->countRowsTotal()
        ));

        $this->success('CSV audit complete.');

        return 0;
    }

    private function setAuditOptions(): array
    {
        $opts = [];

        $keymap = [
            'id-column-name' => 'idColumnName',
        ];

        foreach ($keymap as $oldkey => $newkey) {
            $value = $this->option($oldkey);
            if (empty($value)) {
                continue;
            }

            $opts[$newkey] = $value;
        }

        return $opts;
    }
}
