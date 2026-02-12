<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;

/**
 * Display AtoM and framework version information.
 */
class GetVersionCommand extends BaseCommand
{
    protected string $name = 'tools:get-version';
    protected string $description = 'Show AtoM and framework version information';

    protected function handle(): int
    {
        $atomRoot = $this->getAtomRoot();
        $frameworkRoot = $this->getFrameworkRoot();

        // AtoM version from config/version/version.yml or similar
        $atomVersion = $this->getAtomVersion($atomRoot);
        $frameworkVersion = $this->getFrameworkVersion($frameworkRoot);

        $this->newline();
        $this->bold('  AtoM Heratio Version Information');
        $this->newline();

        $this->table(
            ['Component', 'Version', 'Path'],
            [
                ['AtoM Base', $atomVersion, $atomRoot],
                ['AtoM Framework', $frameworkVersion, $frameworkRoot],
            ]
        );

        $this->newline();

        // PHP version
        $this->info('  PHP: ' . PHP_VERSION);

        // Show database info if verbose
        if ($this->verbose) {
            $this->info('  OS: ' . php_uname('s') . ' ' . php_uname('r'));
            $this->info('  SAPI: ' . php_sapi_name());
        }

        return 0;
    }

    /**
     * Read AtoM base version from config files.
     */
    private function getAtomVersion(string $atomRoot): string
    {
        // Try qubit_dev.yml first
        $versionFile = $atomRoot . '/config/version/qubit_dev.yml';
        if (file_exists($versionFile)) {
            $content = file_get_contents($versionFile);
            if (preg_match('/version:\s*["\']?([^"\'\n]+)/', $content, $matches)) {
                return trim($matches[1]);
            }
        }

        // Try version.yml
        $versionFile = $atomRoot . '/config/version/version.yml';
        if (file_exists($versionFile)) {
            $content = file_get_contents($versionFile);
            if (preg_match('/version:\s*["\']?([^"\'\n]+)/', $content, $matches)) {
                return trim($matches[1]);
            }
        }

        return 'unknown';
    }

    /**
     * Read framework version from composer.json or VERSION file.
     */
    private function getFrameworkVersion(string $frameworkRoot): string
    {
        // Try VERSION file
        $versionFile = $frameworkRoot . '/VERSION';
        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }

        // Try composer.json
        $composerFile = $frameworkRoot . '/composer.json';
        if (file_exists($composerFile)) {
            $data = json_decode(file_get_contents($composerFile), true);
            if (isset($data['version'])) {
                return $data['version'];
            }
        }

        // Try git tag
        $gitDir = $frameworkRoot . '/.git';
        if (is_dir($gitDir)) {
            $output = [];
            exec("cd {$frameworkRoot} && git describe --tags --abbrev=0 2>/dev/null", $output);
            if (!empty($output[0])) {
                return $output[0];
            }
        }

        return 'unknown';
    }
}
