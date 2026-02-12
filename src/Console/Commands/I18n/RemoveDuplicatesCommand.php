<?php

namespace AtomFramework\Console\Commands\I18n;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Remove duplicate i18n source strings across plugins.
 *
 * Ported from lib/task/i18n/i18nRemoveDuplicatesTask.class.php.
 */
class RemoveDuplicatesCommand extends BaseCommand
{
    protected string $name = 'i18n:remove-duplicates';
    protected string $description = 'Remove duplicate i18n source strings across plugins';
    protected string $detailedDescription = <<<'EOF'
Delete duplicate source messages from XLIFF files across all plugin i18n directories.
EOF;

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $this->info('Removing duplicate i18n sources');

        // Loop through plugins
        $pluginNames = \sfFinder::type('dir')->maxdepth(0)->relative()->not_name('.')->in(\sfConfig::get('sf_plugins_dir'));
        foreach ($pluginNames as $pluginName) {
            $this->info(sprintf('Removing %s duplicates', $pluginName));

            foreach (\sfFinder::type('files')->in(\sfConfig::get('sf_plugins_dir') . '/' . $pluginName . '/i18n') as $file) {
                $this->deleteDuplicateSource($file);
            }
        }

        $this->success('Duplicate removal complete');

        return 0;
    }

    private function deleteDuplicateSource(string $filename): void
    {
        $modified = false;
        $sourceStrings = [];

        // Create a new DOM, import the existing XML
        $doc = new \DOMDocument();
        $doc->formatOutput = true;
        $doc->preserveWhiteSpace = false;
        $doc->load($filename);

        $xpath = new \DOMXPath($doc);

        foreach ($xpath->query('//trans-unit') as $unit) {
            $target = null;
            foreach ($xpath->query('./target', $unit) as $t) {
                $target = $t;
                break;
            }

            foreach ($xpath->query('./source', $unit) as $source) {
                // If this is a duplicate source key, then delete it
                if (isset($sourceStrings[$source->nodeValue])) {
                    // If original target string is null, but *this* node has a valid translation
                    if (
                        null !== $target
                        && 0 == strlen($sourceStrings[$source->nodeValue]->nodeValue)
                        && 0 < strlen($target->nodeValue)
                    ) {
                        // Copy this translated string to the trans-unit node we are keeping
                        $sourceStrings[$source->nodeValue]->nodeValue = $target->nodeValue;
                    }

                    // Remove duplicate
                    $unit->parentNode->removeChild($unit);
                    $modified = true;
                } else {
                    $sourceStrings[$source->nodeValue] = $target;
                }

                break; // Only one source
            }
        }

        // Update XLIFF file if modified
        if ($modified) {
            $fileNode = $xpath->query('//file')->item(0);
            $fileNode->setAttribute('date', @date('Y-m-d\TH:i:s\Z'));

            $doc->save($filename);
        }
    }
}
