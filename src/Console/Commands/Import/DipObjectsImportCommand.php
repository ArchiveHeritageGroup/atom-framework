<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Bridges\PropelBridge;
use AtomFramework\Console\BaseCommand;

/**
 * Import digital objects from Archivematica DIP using CSV file.
 */
class DipObjectsImportCommand extends BaseCommand
{
    protected string $name = 'import:dip-objects';
    protected string $description = 'Import DIP objects from Archivematica';
    protected string $detailedDescription = <<<'EOF'
Process a CSV file to import digital objects from an Archivematica DIP to
existing information objects in AtoM.

The CSV file can be named anything, but must have the extension "csv" (lower-case).
The CSV file must start with a header row specifying column order. A "filename"
column must be included. Additionally, either an "identifier" or a "slug"
column must be included (not both).

The undo-log-dir option can be used to log which information objects have
digital objects added to them. The audit option can be used to verify that
all objects specified in a DIP's CSV file were imported.

Examples:
  php bin/atom import:dip-objects /path/to/dip
  php bin/atom import:dip-objects /path/to/dip --audit
  php bin/atom import:dip-objects /path/to/dip --undo-log-dir=/path/to/logs
EOF;

    private array $columnNames = [];
    private array $columnIndexes = [];
    private string $uniqueValueColumnName = '';
    private string $dipDir = '';
    private $conn;

    protected function configure(): void
    {
        $this->addArgument('dip', 'The DIP directory');
        $this->addOption('undo-log-dir', null, 'Directory to write undo logs to');
        $this->addOption('audit', null, 'Audit mode — verify objects were imported');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $dipPath = $this->argument('dip');

        if (empty($dipPath) || !is_dir($dipPath)) {
            $this->error('You must specify a valid DIP directory');

            return 1;
        }

        $undoLogDir = $this->option('undo-log-dir');
        if ($undoLogDir && !is_dir($undoLogDir)) {
            $this->error('Undo log directory does not exist: ' . $undoLogDir);

            return 1;
        }

        // Boot Symfony context for Propel connection
        $configuration = \ProjectConfiguration::getApplicationConfiguration('qubit', 'cli', false);
        \sfContext::createInstance($configuration);
        \QubitSearch::getInstance()->enable();

        $databaseManager = new \sfDatabaseManager($configuration);
        $this->conn = $databaseManager->getDatabase('propel')->getConnection();

        $this->dipDir = $dipPath;
        $auditMode = $this->hasOption('audit');

        $undoLog = null;
        if ($undoLogDir) {
            $undoLog = rtrim($undoLogDir, '/') . '/' . date('Y-m-d') . '-' . basename($this->dipDir) . '.log';
        }

        $objectsPath = rtrim($this->dipDir, '/') . '/objects';
        $this->info("Looking for objects in: {$this->dipDir}");

        // Parse CSV and import
        $fh = $this->openFirstCsvFile($objectsPath);
        if (!$fh) {
            return 1;
        }

        $digitalObjects = $this->parseCsvData($fh, $objectsPath);
        $count = $this->importDigitalObjects($digitalObjects, $auditMode, $undoLog);

        $verb = $auditMode ? 'audited' : 'processed';
        $this->success("Successfully {$verb} {$count} digital objects.");

        return 0;
    }

    private function openFirstCsvFile(string $objectsPath)
    {
        if (!is_dir($objectsPath)) {
            $this->error("Objects path not found: {$objectsPath}");

            return false;
        }

        $csvFiles = glob($objectsPath . '/*.csv');

        if (empty($csvFiles)) {
            $this->error('No CSV files found in: ' . $objectsPath);

            return false;
        }

        $fh = fopen($csvFiles[0], 'rb');
        if (!$fh) {
            $this->error('Could not open CSV file: ' . $csvFiles[0]);

            return false;
        }

        $this->line('Using CSV file: ' . basename($csvFiles[0]));

        return $fh;
    }

    private function parseCsvData($fh, string $objectsPath): array
    {
        $filenames = $this->createFilenameLookup($objectsPath);
        $this->processCsvHeaderRow($fh);

        $digitalObjects = [];

        while ($row = fgetcsv($fh, 1000)) {
            $filename = $this->getRowColumnValue('filename', $row);
            $filepath = $objectsPath . '/' . $filename;

            if (!file_exists($filepath)) {
                $key = null;
                if (preg_match('/(.+)\.(\w{3})$/', $filename, $matches)) {
                    $key = strtolower($matches[1]);
                }

                if (isset($key, $filenames[$key])) {
                    $filepath = str_replace($filename, $filenames[$key], $filepath);
                } else {
                    $this->warning("Could not find file: {$filepath}");

                    continue;
                }
            }

            $uniqueValue = $this->getRowColumnValue($this->uniqueValueColumnName, $row);

            if (!isset($digitalObjects[$uniqueValue])) {
                $digitalObjects[$uniqueValue] = $filepath;
            } elseif (!is_array($digitalObjects[$uniqueValue])) {
                $digitalObjects[$uniqueValue] = [$digitalObjects[$uniqueValue], $filepath];
            } else {
                $digitalObjects[$uniqueValue][] = $filepath;
            }
        }

        return $digitalObjects;
    }

    private function processCsvHeaderRow($fh): void
    {
        $this->columnNames = fgetcsv($fh, 1000);

        $identifierExists = in_array('identifier', $this->columnNames);

        if ($identifierExists && in_array('slug', $this->columnNames)) {
            throw new \RuntimeException('CSV header row includes both "identifier" and "slug" columns. Use only one.');
        }

        if (!$identifierExists && !in_array('slug', $this->columnNames)) {
            throw new \RuntimeException('CSV header row must include either an "identifier" or "slug" column.');
        }

        $this->uniqueValueColumnName = $identifierExists ? 'identifier' : 'slug';
    }

    private function getRowColumnValue(string $column, array $row): string
    {
        if (isset($this->columnIndexes[$column])) {
            return $row[$this->columnIndexes[$column]];
        }

        $columnIndex = array_search($column, $this->columnNames);
        if (is_numeric($columnIndex)) {
            $this->columnIndexes[$column] = $columnIndex;

            return $row[$columnIndex];
        }

        throw new \RuntimeException('Missing column "' . $column . '".');
    }

    private function importDigitalObjects(array $digitalObjects, bool $auditMode, ?string $undoLog): int
    {
        $count = 0;

        foreach ($digitalObjects as $key => $item) {
            $verb = $auditMode ? 'Auditing' : 'Importing to';
            $this->line("{$verb} '{$key}'...");

            if ($auditMode) {
                $items = is_array($item) ? $item : [$item];
                foreach ($items as $filepath) {
                    $this->auditDigitalObject($filepath);
                    ++$count;
                }
            } else {
                $informationObject = $this->getInformationObjectUsingUniqueId($key);

                if (!is_array($item)) {
                    $this->addDigitalObject($informationObject, $item, $undoLog);
                    ++$count;
                } else {
                    foreach ($item as $filepath) {
                        $childIo = new \QubitInformationObject();
                        $childIo->parent = $informationObject;
                        $childIo->title = basename($filepath);
                        $childIo->save($this->conn);

                        $this->addDigitalObject($childIo, $filepath, $undoLog, true);
                        ++$count;
                    }
                }
            }
        }

        return $count;
    }

    private function getInformationObjectUsingUniqueId(string $uniqueValue): object
    {
        $criteria = new \Criteria();

        if ('identifier' === $this->uniqueValueColumnName) {
            $criteria->add(\QubitInformationObject::IDENTIFIER, $uniqueValue);
            $informationObject = \QubitInformationObject::getOne($criteria);

            if (null === $informationObject) {
                throw new \RuntimeException("Invalid information object identifier '{$uniqueValue}'");
            }
        } else {
            $criteria->add(\QubitSlug::SLUG, $uniqueValue);
            $slug = \QubitSlug::getOne($criteria);

            if (null === $slug) {
                throw new \RuntimeException("Invalid information object slug '{$uniqueValue}'");
            }

            $informationObject = \QubitInformationObject::getById($slug->objectId);

            if (null === $informationObject) {
                throw new \RuntimeException("Missing information object for slug '{$uniqueValue}'");
            }
        }

        return $informationObject;
    }

    private function auditDigitalObject(string $filepath): void
    {
        $filename = basename($filepath);
        $statement = \QubitFlatfileImport::sqlQuery(
            'SELECT id FROM digital_object WHERE name=?',
            [$filename]
        );

        if (!$statement->fetchColumn()) {
            $this->warning('Missing: ' . $filename);
        }
    }

    private function addDigitalObject(object $informationObject, string $filepath, ?string $undoLog, bool $container = false): void
    {
        if (null !== $informationObject->getDigitalObject()) {
            $this->comment("Digital object already attached to {$informationObject->identifier} (slug: {$informationObject->slug}). Skipping.");

            return;
        }

        if (!file_exists($filepath)) {
            $this->error("File not found: {$filepath}");

            return;
        }

        $this->line("Importing '{$filepath}'");

        $do = new \QubitDigitalObject();
        $do->usageId = \QubitTerm::MASTER_ID;
        $do->assets[] = new \QubitAsset($filepath);

        $informationObject->digitalObjectsRelatedByobjectId[] = $do;

        // Add DIP UUID as property
        $dipUUID = $this->getUUID(basename($this->dipDir));
        if (null !== $dipUUID) {
            $this->line('Creating property: dip UUID ' . $dipUUID);
            $informationObject->addProperty('aipUUID', $dipUUID);
        }

        // Add object UUID as property
        $objectUUID = $this->getUUID(basename($filepath));
        if (null !== $objectUUID) {
            $this->line('Creating property: object UUID ' . $objectUUID);
            $informationObject->addProperty('objectUUID', $objectUUID);
        }

        $informationObject->save($this->conn);

        if ($undoLog) {
            $logLine = $informationObject->id . "\t" . basename($this->dipDir) . "\t" . $container . "\n";
            file_put_contents($undoLog, $logLine, FILE_APPEND);
        }
    }

    private function getUUID(string $subject): ?string
    {
        preg_match_all('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', $subject, $matches);

        if (empty($matches[0])) {
            return null;
        }

        $this->line('UUID found: ' . $matches[0][0] . ' in ' . $subject);

        return $matches[0][0];
    }

    private function createFilenameLookup(string $objectsPath): array
    {
        $filenames = [];

        foreach (scandir($objectsPath) as $file) {
            if (is_dir($objectsPath . '/' . $file)) {
                continue;
            }

            $pattern = '/^[0-9a-f-]{37}(.+)\.(\w{3})$/';
            if (!preg_match($pattern, strtolower($file), $matches)) {
                continue;
            }

            if ('csv' === $matches[2]) {
                continue;
            }

            $filenames[$matches[1]] = $file;
        }

        return $filenames;
    }
}
