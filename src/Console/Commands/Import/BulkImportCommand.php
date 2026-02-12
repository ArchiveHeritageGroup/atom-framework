<?php

namespace AtomFramework\Console\Commands\Import;

use AtomFramework\Bridges\PropelBridge;
use AtomFramework\Console\BaseCommand;

/**
 * Bulk import multiple XML/CSV files at once.
 */
class BulkImportCommand extends BaseCommand
{
    protected string $name = 'import:bulk';
    protected string $description = 'Bulk XML/CSV import';
    protected string $detailedDescription = <<<'EOF'
Bulk import multiple XML/CSV files at once.

Examples:
  php bin/atom import:bulk /path/to/import/folder
  php bin/atom import:bulk /path/to/file.xml
  php bin/atom import:bulk /path/to/folder --index
  php bin/atom import:bulk /path/to/folder --schema=isad --update=match-and-update
  php bin/atom import:bulk /path/to/folder --completed-dir=/path/to/done
  php bin/atom import:bulk /path/to/folder --output=results.csv --verbose
EOF;

    protected function configure(): void
    {
        $this->addArgument('folder', 'The import folder or file');
        $this->addOption('index', null, 'Enable indexing on imported objects');
        $this->addOption('taxonomy', null, 'Taxonomy ID for SKOS concepts');
        $this->addOption('completed-dir', null, 'Directory to move completed files into');
        $this->addOption('schema', null, 'Schema for CSV import');
        $this->addOption('output', null, 'Filename to output results in CSV format');
        $this->addOption('update', null, 'Update mode: match-and-update or delete-and-replace');
        $this->addOption('skip-matched', null, 'Skip creating new records when an existing one matches');
        $this->addOption('skip-unmatched', null, 'Skip creating new records if no existing records match');
        $this->addOption('limit', null, 'Limit --update matching to under a specified slug');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $folder = $this->argument('folder');

        if (empty($folder) || !file_exists($folder)) {
            $this->error('You must specify a valid import folder or file');

            return 1;
        }

        // Set indexing preference
        if (!$this->hasOption('index')) {
            \QubitSearch::disable();
        }

        if (is_dir($folder)) {
            $files = $this->dirTree(rtrim($folder, '/'));
        } else {
            $files = [realpath($folder)];
        }

        $this->info('Importing ' . count($files) . ' files from ' . $folder . ' (indexing is ' . ($this->hasOption('index') ? 'ENABLED' : 'DISABLED') . ')');

        $count = 0;
        $total = count($files);
        $startTotal = microtime(true);
        $rows = [];

        $options = [
            'index' => $this->hasOption('index'),
            'taxonomy' => $this->option('taxonomy'),
            'schema' => $this->option('schema'),
            'update' => $this->option('update'),
            'skip-matched' => $this->hasOption('skip-matched'),
            'skip-unmatched' => $this->hasOption('skip-unmatched'),
            'limit' => $this->option('limit'),
        ];

        foreach ($files as $file) {
            $start = microtime(true);
            $importer = null;

            if ($this->verbose) {
                $this->line('Importing: ' . $file);
            }

            $ext = pathinfo($file, PATHINFO_EXTENSION);

            if ('csv' === $ext) {
                $importer = new \QubitCsvImport();
                $importer->indexDuringImport = $this->hasOption('index');
                $importer->import($file, $options);
            } elseif ('xml' === $ext) {
                $importer = new \QubitXmlImport();
                $importer->includeClassesAndHelpers();
                $options['strictXmlParsing'] = false;
                $importer->import($file, $options);
            } else {
                continue;
            }

            $completedDir = $this->option('completed-dir');
            if ($completedDir && null !== $importer) {
                $pathInfo = pathinfo($file);
                $moveSource = $pathInfo['dirname'] . '/' . $pathInfo['basename'];
                $moveDestination = $completedDir . '/' . $pathInfo['basename'];
                rename($moveSource, $moveDestination);
            }

            if ($importer->hasErrors()) {
                foreach ($importer->getErrors() as $message) {
                    $this->warning('(' . $file . '): ' . $message);
                }
            }

            unset($importer);
            ++$count;
            $split = round(microtime(true) - $start, 2);

            $outputFile = $this->option('output');
            if ($outputFile) {
                $rows[] = [$file, $split . 's', memory_get_usage() . 'B'];
            }

            if ($this->verbose) {
                $this->line(basename($file) . ' imported (' . $split . ' s) (' . $count . '/' . $total . ')');
            } else {
                $this->line('.', false);
            }
        }

        // Write output CSV if specified
        $outputFile = $this->option('output');
        if ($outputFile) {
            $fh = fopen($outputFile, 'w+');
            fputcsv($fh, ['File', 'Time elapsed (secs)', 'Memory used']);
            foreach ($rows as $row) {
                fputcsv($fh, $row);
            }
            $elapsed = round(microtime(true) - $startTotal, 2);
            fputcsv($fh, []);
            fputcsv($fh, ['Total time elapsed:', $elapsed . 's']);
            fputcsv($fh, ['Peak memory usage:', round(memory_get_peak_usage() / 1048576, 2) . 'MB']);
            fclose($fh);
        }

        // Optimize index if enabled
        if ($this->hasOption('index')) {
            \QubitSearch::getInstance()->optimize();
        }

        $elapsed = round(microtime(true) - $startTotal, 2);
        $this->newline();
        $this->success("Imported {$count} XML/CSV files in {$elapsed}s. " . memory_get_peak_usage() . ' bytes used.');

        return 0;
    }

    private function dirTree(string $dir): array
    {
        $path = [];
        $stack = [$dir];

        while ($stack) {
            $thisdir = array_pop($stack);

            if ($dircont = scandir($thisdir)) {
                $i = 0;
                while (isset($dircont[$i])) {
                    if ('.' !== $dircont[$i] && '..' !== $dircont[$i] && !preg_match('/^\..*/', $dircont[$i])) {
                        $currentFile = "{$thisdir}/{$dircont[$i]}";
                        if (is_file($currentFile)) {
                            $path[] = $currentFile;
                        } elseif (is_dir($currentFile)) {
                            $stack[] = $currentFile;
                        }
                    }
                    ++$i;
                }
            }
        }

        return $path;
    }
}
