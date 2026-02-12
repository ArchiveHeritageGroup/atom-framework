<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Generate an XML sitemap.
 *
 * Ported from lib/task/tools/sitemapTask.class.php.
 * Uses Propel for iterating over all public descriptions, actors,
 * and static pages to build the sitemap.
 */
class SitemapCommand extends BaseCommand
{
    protected string $name = 'tools:sitemap';
    protected string $description = 'Generate an XML sitemap';
    protected string $detailedDescription = <<<'EOF'
Write a Sitemap XML file that lists the URLs of the current site.

By default, the sitemap is stored in the AtoM root directory. Its final
location can be defined using --output-directory.

The URLs included in the sitemap will be based on the Site base URL,
which can be defined under the application settings in the web interface
or using --base-url.

Optionally, you can submit the sitemap to Google and Bing with --ping.
EOF;

    private static array $urls = [
        'Google' => 'http://www.google.com/webmasters/sitemaps/ping?sitemap=%s',
        'Bing' => 'http://www.bing.com/webmaster/ping.aspx?siteMap=%s',
    ];

    protected function configure(): void
    {
        $this->addOption('output-directory', 'O', 'Location of the sitemap file(s)');
        $this->addOption('base-url', null, 'Base URL for the sitemap');
        $this->addOption('indent', null, 'Indent XML output', '1');
        $this->addOption('no-compress', null, 'Do not compress XML output with Gzip');
        $this->addOption('no-confirmation', 'B', 'Avoid prompting the user');
        $this->addOption('ping', null, 'Submit sitemap to Google and Bing');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        // Create sfContext for URL generation
        $configuration = \ProjectConfiguration::getApplicationConfiguration('qubit', 'cli', false);
        \sfContext::createInstance($configuration);

        $baseUrl = $this->option('base-url');
        if (null === $baseUrl) {
            $setting = \QubitSetting::getByName('siteBaseUrl');
            if (null !== $setting) {
                $baseUrl = $setting->getValue();
            } else {
                $baseUrl = 'http://127.0.0.1';
            }
        }

        $outputDirectory = $this->option('output-directory', $this->atomRoot);

        // Check if the given directory exists
        if (!is_dir($outputDirectory)) {
            throw new \RuntimeException('The given directory cannot be found');
        }

        // Delete existing sitemap(s)
        $files = glob($outputDirectory . '/sitemap*.xml');
        $gzFiles = glob($outputDirectory . '/sitemap*.xml.gz');
        $allFiles = array_merge($files ?: [], $gzFiles ?: []);

        if (count($allFiles) > 0) {
            if (!$this->hasOption('no-confirmation')) {
                if (!$this->confirm('Do you want to delete the previous sitemap(s)?', true)) {
                    $this->info('Quitting');
                    return 0;
                }
            }

            natsort($allFiles);
            foreach ($allFiles as $file) {
                $this->line('Deleting ' . $file);
                unlink($file);
            }
        }

        // Write XML
        $indent = (bool) $this->option('indent', '1');
        $compress = !$this->hasOption('no-compress');

        $writer = new \SitemapWriter($outputDirectory, $baseUrl, $indent, $compress);

        $this->info('Indexing information objects');
        $writer->addSet(new \SitemapInformationObjectSet());

        $this->info('Indexing actors');
        $writer->addSet(new \SitemapActorSet());

        $this->info('Indexing static pages');
        $writer->addSet(new \SitemapStaticPageSet());

        $writer->end();

        // Sitemap submission
        if ($this->hasOption('ping')) {
            $location = $baseUrl . '/sitemap.xml';

            $client = new \sfWebBrowser();
            foreach (self::$urls as $sName => $sUrl) {
                $url = sprintf($sUrl, $location);
                $this->line(sprintf('[%s] Submitting - %s', $sName, $url));

                $client->get($url);
                $this->line(sprintf('[%s] Response code: %s', $sName, $client->getResponseCode()));
            }
        }

        $this->success('Done!');

        return 0;
    }
}
