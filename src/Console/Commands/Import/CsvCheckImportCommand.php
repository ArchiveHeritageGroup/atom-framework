<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Console\BaseCommand;

/**
 * Check CSV import file, providing diagnostic info.
 *
 * Native implementation of the csv:check-import Symfony task.
 */
class CsvCheckImportCommand extends BaseCommand
{
    protected string $name = 'import:csv-check';
    protected string $description = 'Check CSV import file';

    protected string $detailedDescription = <<<'EOF'
    Check CSV data, providing information about it. Validates CSV files or
    directories of CSV files against AtoM import requirements. Supports
    specifying object class type, source name for parentId validation,
    specific test classes, and digital object path validation.
    EOF;

    protected function configure(): void
    {
        $this->addArgument('filename', 'The input file (CSV format) or directory of CSV files', true);

        $this->addOption('verbose-output', 'i', 'Provide detailed information regarding each test');
        $this->addOption('source', null, 'Source name for validating parentId matching against previous imports');
        $this->addOption('class-name', null, 'Qubit object type contained in CSV', 'QubitInformationObject');
        $this->addOption('specific-tests', null, 'Specific test classes to run');
        $this->addOption('path-to-digital-objects', null, 'Path to root of digital object folder that will match digitalObjectPath in CSV');
    }

    protected function handle(): int
    {
        $filename = $this->argument('filename');
        $verboseOutput = $this->hasOption('verbose-output');

        $validatorOptions = $this->setOptions();

        $filenames = $this->setCsvValidatorFilenames($filename);

        $validator = new \CsvImportValidator(
            \sfContext::getInstance(),
            \Propel::getConnection(),
            $validatorOptions
        );

        $validator->setShowDisplayProgress(true);
        $validator->setFilenames($filenames);
        $results = $validator->validate();
        $output = $results->renderResultsAsText($verboseOutput);
        echo $output;

        unset($validator);

        $this->success('CSV check complete.');

        return 0;
    }

    private function setCsvValidatorFilenames(string $filenameString): array
    {
        // If a directory's provided return an array of file paths
        if (is_dir($filenameString) && is_readable($filenameString)) {
            return $this->getFilePathsFromDirectory($filenameString);
        }

        $filenames = [];

        // Could be a comma separated list of filenames or just one.
        foreach (explode(',', $filenameString) as $filename) {
            \CsvImportValidator::validateFileName($filename);
            // The validator expects an associative array of files
            // where displayname => filename
            $filenames[$filename] = $filename;
        }

        return $filenames;
    }

    private function getFilePathsFromDirectory(string $directory): array
    {
        $filePaths = [];

        if (!is_dir($directory) || !is_readable($directory)) {
            throw new \UnexpectedValueException(sprintf('%s requires a readable directory.', __FUNCTION__));
        }

        $files = array_diff(scandir($directory), ['..', '.']);

        foreach ($files as $file) {
            $filePath = $directory . DIRECTORY_SEPARATOR . $file;

            if (is_file($filePath) && is_readable($filePath)) {
                $filePaths[basename($filePath)] = $filePath;
            }
        }

        natsort($filePaths);

        return $filePaths;
    }

    private function setOptions(): array
    {
        $opts = [];

        $keymap = [
            'source' => 'source',
            'class-name' => 'className',
            'specific-tests' => 'specificTests',
            'path-to-digital-objects' => 'pathToDigitalObjects',
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
