<?php

namespace AtomFramework\Console\Commands\I18n;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Update i18n fixtures from XLIFF translation files.
 *
 * Ported from lib/task/i18n/i18nUpdateFixturesTask.class.php.
 */
class UpdateFixturesCommand extends BaseCommand
{
    protected string $name = 'i18n:update-fixtures';
    protected string $description = 'Update i18n fixtures from XLIFF translation files';
    protected string $detailedDescription = <<<'EOF'
Reads XLIFF files from the specified path and merges translations to database
fixture files.
EOF;

    protected function configure(): void
    {
        $this->addArgument('path', 'Path for XLIFF files', true);
        $this->addOption('filename', 'f', 'Name of XLIFF files', 'messages.xml');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $path = $this->argument('path');
        $filename = $this->option('filename') ?? 'messages.xml';

        // Extract translation strings from XLIFF files
        $translations = $this->extractTranslations($path, $filename);

        if (null === $translations) {
            return 1;
        }

        $this->updateFixtures($translations);

        $this->success('Fixture update complete');

        return 0;
    }

    private function extractTranslations(string $path, string $filename): ?array
    {
        $translations = [];

        $this->info(sprintf('Find XLIFF files named "%s"', $filename));

        // Search for XLIFF files
        $files = \sfFinder::type('file')->name($filename)->in($path);

        if (0 == count($files)) {
            $this->error('No valid files found. Please check path and filename');

            return null;
        }

        // Extract translation strings
        foreach ($files as $file) {
            $culture = self::getTargetCulture($file);
            $xliff = new \sfMessageSource_XLIFF(substr($file, 0, strrpos($file, '/')));

            if (!($messages = $xliff->loadData($file))) {
                continue;
            }

            // Build list of translations, keyed on source value
            foreach ($messages as $source => $message) {
                if (0 < strlen($message[0])) {
                    $translations[$source][$culture] = trim($message[0]);
                }
            }
        }

        return $translations;
    }

    private function updateFixtures(array $translations): void
    {
        $this->info('Writing new translations to fixtures...');

        $configuration = \sfProjectConfiguration::getActive();

        // Search for YAML files
        $fixturesDirs = array_merge(
            [\sfConfig::get('sf_data_dir') . '/fixtures'],
            $configuration->getPluginSubPaths('/data/fixtures')
        );
        $files = \sfFinder::type('file')->name('*.yml')->in($fixturesDirs);

        if (0 == count($files)) {
            $this->error('Could not find any fixture files to write.');

            return;
        }

        // Merge translations to YAML files in data/fixtures
        foreach ($files as $file) {
            $modified = false;
            $yaml = new \sfYaml();
            $fixtures = $yaml->load($file);

            // Descend through fixtures hierarchy
            foreach ($fixtures as $classname => &$fixture) {
                foreach ($fixture as $key => &$columns) {
                    foreach ($columns as $column => &$value) {
                        if (is_array($value) && isset($value['en'])) {
                            if (isset($translations[$value['en']])) {
                                $value = array_merge($value, $translations[$value['en']]);
                                ksort($value);
                                $modified = true;
                            }
                        }
                    }
                }
            }

            if ($modified) {
                $this->info(sprintf('Updating %s...', $file));

                $contents = $yaml->dump($fixtures, 4);

                if (0 < strlen($contents)) {
                    file_put_contents($file, $contents);
                }
            }
        }
    }

    private static function getTargetCulture(string $filename): ?string
    {
        libxml_use_internal_errors(true);
        if (!$xml = simplexml_load_file($filename)) {
            return null;
        }
        libxml_use_internal_errors(false);

        $code = (string) $xml->file['target-language'];

        // Transifex may leave the target-language property empty;
        // extract target from the path of the file as fallback.
        if (empty($code)) {
            if (1 === preg_match('/\\/(?P<code>[a-zA-Z_@]+)\\/messages\\.xml$/m', $filename, $matches)) {
                if (isset($matches['code'])) {
                    $code = $matches['code'];
                }
            }
        }

        return $code ?: null;
    }
}
