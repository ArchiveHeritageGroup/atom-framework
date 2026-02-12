<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\BaseCommand;

/**
 * Check digital object paths in CSV data.
 *
 * Native implementation of the csv:digital-object-path-check Symfony task.
 */
class CsvDigitalObjectPathsCheckCommand extends BaseCommand
{
    protected string $name = 'import:csv-digital-object-paths-check';
    protected string $description = 'Check digital object paths in CSV';

    protected string $detailedDescription = <<<'EOF'
    Compare digital object-related files in a directory to data in a CSV file's
    column (digitalObjectPath by default) and display a report. Determines which
    files are not referenced in the CSV data, which are referenced but missing,
    and which files are referenced more than once.
    EOF;

    protected function configure(): void
    {
        $this->addArgument('path-to-images', 'Path to directory containing images', true);
        $this->addArgument('path-to-csv-file', 'Path to CSV file', true);

        $this->addOption('csv-column-name', 'n', 'CSV column name containing digital object paths');
    }

    protected function handle(): int
    {
        $pathToImages = $this->argument('path-to-images');
        $pathToCsvFile = $this->argument('path-to-csv-file');

        if (!is_dir($pathToImages)) {
            $this->error("Images directory doesn't exist.");

            return 1;
        }

        if (!file_exists($pathToCsvFile)) {
            $this->error("CSV file doesn't exist.");

            return 1;
        }

        $csvFilePathColumnName = ($this->option('csv-column-name')) ? $this->option('csv-column-name') : 'digitalObjectPath';

        $this->info("Checking {$csvFilePathColumnName} column.");

        $this->printImageUsageInfo($pathToImages, $pathToCsvFile, $csvFilePathColumnName);

        return 0;
    }

    private function printImageUsageInfo(string $pathToImages, string $csvFilePath, string $csvFilePathColumnName): void
    {
        $imageFiles = $this->getImageFiles($pathToImages);
        $columnValues = $this->getCsvColumnValues($csvFilePath, $csvFilePathColumnName);
        $imageUses = $this->summarizeImageUsage($columnValues);

        $this->printImageUses($imageUses);
        $this->printUnusedFiles($imageFiles, $imageUses);
        $this->printMissingFiles($imageUses, $pathToImages);
    }

    private function getImageFiles(string $pathToImages): array
    {
        $imageFiles = [];
        $pathToImages = realpath($pathToImages);
        $objects = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pathToImages));

        foreach ($objects as $filePath => $object) {
            if (!is_dir($filePath)) {
                $relativeFilePath = substr($filePath, strlen($pathToImages) + 1, strlen($filePath));
                array_push($imageFiles, $relativeFilePath);
            }
        }

        return $imageFiles;
    }

    private function getCsvColumnValues(string $filepath, string $columnName): array
    {
        $values = [];
        $fh = fopen($filepath, 'r');

        $header = fgetcsv($fh, 60000);
        if (false === $imageColumnIndex = array_search($columnName, $header)) {
            throw new \sfException('Column name not found in header.');
        }

        while ($row = fgetcsv($fh, 60000)) {
            array_push($values, basename($row[$imageColumnIndex]));
        }

        return $values;
    }

    private function summarizeImageUsage(array $columnValues): array
    {
        $imageUses = [];
        foreach ($columnValues as $columnValue) {
            $imageUses[$columnValue] = (!isset($imageUses[$columnValue])) ? 1 : $imageUses[$columnValue] + 1;
        }

        return $imageUses;
    }

    private function printImageUses(array $imageUses): void
    {
        $usedMoreThanOnce = [];
        foreach ($imageUses as $image => $uses) {
            if ($uses > 1) {
                array_push($usedMoreThanOnce, $image);
            }
        }

        $this->printListOfItemsIfNotEmpty($usedMoreThanOnce, 'Used more than once in CSV:');
    }

    private function printUnusedFiles(array $imageFiles, array $imageUses): void
    {
        $unusedFiles = [];
        foreach ($imageFiles as $imageFile) {
            if (!isset($imageUses[$imageFile])) {
                array_push($unusedFiles, $imageFile);
            }
        }

        $this->printListOfItemsIfNotEmpty($unusedFiles, 'Unused files:');
    }

    private function printMissingFiles(array $imageUses, string $pathToImages): void
    {
        $missingFiles = [];
        foreach ($imageUses as $image => $uses) {
            if (!file_exists($pathToImages . '/' . $image)) {
                array_push($missingFiles, $image);
            }
        }

        $this->printListOfItemsIfNotEmpty($missingFiles, 'Files referenced in CSV that are missing:');
    }

    private function printListOfItemsIfNotEmpty(array $list, string $listHeader): void
    {
        if (count($list)) {
            $this->line($listHeader);
            foreach ($list as $item) {
                $this->line('* ' . $item);
            }
            $this->newline();
        }
    }
}
