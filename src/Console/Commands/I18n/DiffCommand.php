<?php

namespace AtomFramework\Console\Commands\I18n;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Output a diff of removed and added i18n messages.
 *
 * Ported from lib/task/i18n/i18nDiffTask.class.php.
 */
class DiffCommand extends BaseCommand
{
    protected string $name = 'i18n:diff';
    protected string $description = 'Output a list of removed and added i18n messages for auditing';
    protected string $detailedDescription = <<<'EOF'
Compares existing XLIFF strings to new i18n strings extracted from PHP files
for the given application and target culture.

  php bin/atom i18n:diff qubit fr

By default, the task outputs to STDOUT. To specify a destination file
use the --file option:

  php bin/atom i18n:diff --file=french_diff.csv qubit fr

By default, the task outputs the differences in CSV format. To specify an
alternate file format use the --format option:

  php bin/atom i18n:diff --format=tab qubit fr

Possible --format values are "csv" and "tab".
EOF;

    private $i18n;

    protected function configure(): void
    {
        $this->addArgument('application', 'The application name', true);
        $this->addArgument('culture', 'The target culture', true);
        $this->addOption('file', 'f', 'Destination filename for writing output', 'stdout');
        $this->addOption('format', 'o', 'Output format (csv or tab)', 'csv');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $application = $this->argument('application');
        $culture = $this->argument('culture');
        $file = $this->option('file') ?? 'stdout';
        $format = $this->option('format') ?? 'csv';

        $output = '';

        if ('stdout' != strtolower($file)) {
            $this->info(sprintf('Diff i18n strings for the "%s" application', $application));
        }

        // Get i18n configuration from factories.yml
        $configuration = \sfProjectConfiguration::getActive();
        $config = \sfFactoryConfigHandler::getConfiguration($configuration->getConfigPaths('config/factories.yml'));

        $class = $config['i18n']['class'];
        $params = $config['i18n']['param'];
        unset($params['cache']);

        $this->i18n = new $class($configuration, new \sfNoCache(), $params);
        $extract = new \sfI18nApplicationExtract($this->i18n, $culture);
        $extract->extract();

        if ('stdout' != strtolower($file)) {
            $this->info(sprintf('found "%d" new i18n strings', count($extract->getNewMessages())));
            $this->info(sprintf('found "%d" old i18n strings', count($extract->getOldMessages())));
        }

        // Column headers
        $rows[0] = ['Action', 'Source', 'Target'];

        // Old messages
        foreach ($this->getOldTranslations($extract) as $source => $target) {
            $rows[] = ['Removed', $source, $target];
        }

        // New messages
        foreach ($extract->getNewMessages() as $message) {
            $rows[] = ['Added', $message];
        }

        // Choose output format
        switch (strtolower($format)) {
            case 'csv':
                foreach ($rows as $row) {
                    $output .= '"' . implode('","', array_map('addslashes', $row)) . "\"\n";
                }
                break;

            case 'tab':
                foreach ($rows as $row) {
                    $output .= implode("\t", $row) . "\n";
                }
                break;
        }

        // Output file
        if ('stdout' != strtolower($file)) {
            $filename = ('=' == substr($file, 0, 1)) ? substr($file, 1) : $file;
            file_put_contents($filename, $output);
            $this->success('Output written to ' . $filename);
        } else {
            echo $output;
        }

        return 0;
    }

    private function getOldTranslations($extract): array
    {
        $oldMessages = array_diff($extract->getCurrentMessages(), $extract->getAllSeenMessages());
        $allTranslations = [];

        foreach ($this->i18n->getMessageSource()->read() as $catalogue => $translations) {
            foreach ($translations as $key => $value) {
                $allTranslations[$key] = $value[0];
            }
        }

        $oldTranslations = [];
        foreach ($oldMessages as $message) {
            $oldTranslations[$message] = $allTranslations[$message] ?? '';
        }

        return $oldTranslations;
    }
}
