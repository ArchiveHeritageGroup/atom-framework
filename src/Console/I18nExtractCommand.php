<?php

declare(strict_types=1);

namespace AtomFramework\Console;

/**
 * Extracts translatable strings from the AHG plugins into XLIFF catalogues.
 *
 * WHY
 *
 * The plugins call __() around 35,000 times across about 13,000 distinct
 * strings, and shipped with no i18n directory at all. Symfony loads plugin
 * catalogues from plugins/<name>/i18n/<culture>/messages.xml, the same way base
 * AtoM's own plugins do, so the mechanism was always there and simply had
 * nothing to read. Every install was English regardless of the user's culture.
 *
 * Hand-writing those catalogues is not the job. Producing the source list a
 * translator works from is, and that is what this does.
 *
 * RE-RUNNING IS SAFE
 *
 * Existing translations are read back and preserved. A string still present
 * keeps its target, a new string arrives with an empty one, and a string that
 * has disappeared from the code is dropped. Extraction that discards a
 * translator's work the second time it runs is worse than no extraction, so the
 * merge is the part worth being careful about.
 */
final class I18nExtractCommand
{
    private string $rootDir;

    public function __construct(string $rootDir)
    {
        $this->rootDir = $rootDir;
    }

    public function run(array $args): int
    {
        $culture = 'template';
        $only = null;

        foreach ($args as $arg) {
            if (0 === strpos($arg, '--culture=')) {
                $culture = substr($arg, 10);
            } elseif (0 === strpos($arg, '--plugin=')) {
                $only = substr($arg, 9);
            } elseif ('--help' === $arg || '-h' === $arg) {
                echo "Usage: atom i18n:extract [--culture=af] [--plugin=ahgFooPlugin]\n\n";
                echo "  No --culture writes i18n/template/messages.xml, the source list\n";
                echo "  translators work from. --culture=af writes i18n/af/messages.xml,\n";
                echo "  preserving any translations already there.\n";

                return 0;
            }
        }

        $pluginsDir = $this->rootDir.'/atom-ahg-plugins';

        if (!is_dir($pluginsDir)) {
            echo "No atom-ahg-plugins directory at {$pluginsDir}\n";

            return 1;
        }

        $plugins = glob($pluginsDir.'/ahg*Plugin', GLOB_ONLYDIR) ?: [];
        $totalStrings = 0;
        $totalPlugins = 0;
        $totalKept = 0;

        foreach ($plugins as $pluginPath) {
            $name = basename($pluginPath);

            if (null !== $only && $name !== $only) {
                continue;
            }

            $strings = $this->extract($pluginPath);

            if ($strings === []) {
                continue;
            }

            $target = $pluginPath.'/i18n/'.$culture;
            $file = $target.'/messages.xml';

            $existing = is_file($file) ? $this->readCatalogue($file) : [];

            if (!is_dir($target) && !mkdir($target, 0o755, true) && !is_dir($target)) {
                echo "  could not create {$target}\n";

                continue;
            }

            $kept = 0;
            foreach ($strings as $source) {
                if (!empty($existing[$source])) {
                    ++$kept;
                }
            }

            file_put_contents($file, $this->buildCatalogue($strings, $existing, $culture));

            printf("  %-42s %5d strings%s\n", $name, count($strings), $kept ? sprintf(', %d translations kept', $kept) : '');

            $totalStrings += count($strings);
            $totalKept += $kept;
            ++$totalPlugins;
        }

        echo "\n{$totalPlugins} plugins, {$totalStrings} strings";
        echo $totalKept ? ", {$totalKept} existing translations preserved" : '';
        echo ".\nCulture: {$culture}\n";

        return 0;
    }

    /**
     * Every distinct string passed to __() in a plugin.
     *
     * Only literal single- and double-quoted arguments are taken. A call built
     * from a variable or a concatenation cannot be extracted statically, and
     * guessing at one would put a string in the catalogue that never matches at
     * runtime.
     */
    private function extract(string $pluginPath): array
    {
        $found = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pluginPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $extension = strtolower($file->getExtension());

            if (!in_array($extension, ['php', 'inc'], true)) {
                continue;
            }

            // Skip anything we generate or vendor in, so third-party strings do
            // not end up in a catalogue we ask people to translate.
            $path = $file->getPathname();

            if (false !== strpos($path, '/vendor/') || false !== strpos($path, '/i18n/')) {
                continue;
            }

            $contents = (string) file_get_contents($path);

            if (false === strpos($contents, '__(')) {
                continue;
            }

            if (preg_match_all('/__\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'/', $contents, $matches)) {
                foreach ($matches[1] as $match) {
                    $found[stripcslashes($match)] = true;
                }
            }

            if (preg_match_all('/__\(\s*"((?:[^"\\\\$]|\\\\.)*)"/', $contents, $matches)) {
                foreach ($matches[1] as $match) {
                    $found[stripcslashes($match)] = true;
                }
            }
        }

        // Cast: PHP turns a numeric string key such as '1' into an integer, and
        // under strict_types trim() then rejects it outright.
        $strings = array_map('strval', array_keys($found));

        // Drop anything that is not really a message: empty, or a bare token
        // that is almost always a variable placeholder rather than prose.
        $strings = array_values(array_filter($strings, static function (string $s) {
            return '' !== trim($s) && '%' !== substr(trim($s), 0, 1);
        }));

        sort($strings, SORT_NATURAL | SORT_FLAG_CASE);

        return $strings;
    }

    /**
     * Read source/target pairs out of an existing catalogue.
     */
    private function readCatalogue(string $file): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($file);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (false === $xml || !isset($xml->file->body)) {
            return [];
        }

        $pairs = [];

        foreach ($xml->file->body->{'trans-unit'} as $unit) {
            $source = (string) $unit->source;
            $target = (string) $unit->target;

            if ('' !== $source) {
                $pairs[$source] = $target;
            }
        }

        return $pairs;
    }

    private function buildCatalogue(array $strings, array $existing, string $culture): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $xliff = $document->createElement('xliff');
        $xliff->setAttribute('version', '1.0');
        $document->appendChild($xliff);

        $file = $document->createElement('file');
        $file->setAttribute('source-language', 'EN');
        $file->setAttribute('target-language', 'template' === $culture ? 'EN' : $culture);
        $file->setAttribute('datatype', 'plaintext');
        $file->setAttribute('original', 'messages');
        $file->setAttribute('product-name', 'messages');
        $xliff->appendChild($file);

        $file->appendChild($document->createElement('header'));

        $body = $document->createElement('body');
        $file->appendChild($body);

        $id = 0;
        foreach ($strings as $source) {
            $unit = $document->createElement('trans-unit');
            $unit->setAttribute('id', (string) ++$id);

            $sourceNode = $document->createElement('source');
            $sourceNode->appendChild($document->createTextNode($source));
            $unit->appendChild($sourceNode);

            $targetNode = $document->createElement('target');
            $targetNode->appendChild($document->createTextNode($existing[$source] ?? ''));
            $unit->appendChild($targetNode);

            $body->appendChild($unit);
        }

        return $document->saveXML();
    }
}
