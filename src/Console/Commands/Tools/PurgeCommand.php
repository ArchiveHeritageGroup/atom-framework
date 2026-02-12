<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Purge all data from AtoM and reinitialize.
 *
 * Ported from lib/task/tools/purgeTask.class.php.
 * Uses Propel for the destructive purge operation that truncates tables,
 * rebuilds the schema, and recreates an admin user.
 */
class PurgeCommand extends BaseCommand
{
    protected string $name = 'tools:purge';
    protected string $description = 'Purge all data from AtoM (DESTRUCTIVE)';
    protected string $detailedDescription = <<<'EOF'
Purge all data from AtoM and reinitialize with fresh schema.

WARNING: This is a destructive operation that will delete all data!

Options:
    --demo              Use default demo values, do not ask for confirmation
    --no-confirmation   Skip confirmation prompts
    --title             Desired site title
    --description       Desired site description
    --url               Desired site base URL
    --username          Desired admin username
    --email             Desired admin email address
    --password          Desired admin password
    --culture           Desired culture (e.g. "fr") for site title and description
    --use-gitconfig     Get username and email from $HOME/.gitconfig
EOF;

    protected function configure(): void
    {
        $this->addOption('use-gitconfig', null, 'Get username and email from $HOME/.gitconfig');
        $this->addOption('title', null, 'Desired site title');
        $this->addOption('description', null, 'Desired site description');
        $this->addOption('url', null, 'Desired site base URL');
        $this->addOption('username', null, 'Desired admin username');
        $this->addOption('email', null, 'Desired admin email address');
        $this->addOption('password', null, 'Desired admin password');
        $this->addOption('no-confirmation', null, 'Do not ask for confirmation');
        $this->addOption('demo', null, 'Use default demo values, do not ask for confirmation');
        $this->addOption('culture', null, 'Desired culture (e.g. "fr") for site title and description');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        \sfConfig::set('app_avoid_routing_propel_exceptions', true);

        $configuration = \ProjectConfiguration::getApplicationConfiguration(
            'qubit', 'cli', false
        );
        \sfContext::createInstance($configuration);

        // Validate options
        if (!$this->hasOption('demo') && !function_exists('readline')) {
            $needed = ['title', 'description', 'url', 'email', 'username', 'password'];
            $missing = false;
            foreach ($needed as $key) {
                if (!$this->option($key)) {
                    $missing = true;
                    break;
                }
            }
            if ($missing) {
                throw new \RuntimeException(
                    'At least one of the following command line options is missing: '
                    . 'title, description, url, email, username and/or password.'
                );
            }
        }

        // Set demo options if requested
        if ($this->hasOption('demo')) {
            $title = 'Demo site';
            $siteDescription = 'Demo site';
            $email = 'demo@example.com';
            $username = 'demo';
            $password = 'demo';
            $url = 'http://127.0.0.1';
            $culture = 'en';
            $noConfirmation = true;
        } else {
            $title = $this->option('title');
            $siteDescription = $this->option('description');
            $email = $this->option('email');
            $username = $this->option('username');
            $password = $this->option('password');
            $url = $this->option('url');
            $culture = $this->option('culture', \sfConfig::get('sf_default_culture', 'en'));
            $noConfirmation = $this->hasOption('no-confirmation');
        }

        // Initialize database and Elasticsearch
        $this->info('Initializing database');

        $args = '';
        if ($noConfirmation) {
            $args .= ' --no-confirmation';
        }
        $ret = $this->passthru(sprintf(
            'php %s/symfony propel:insert-sql%s',
            escapeshellarg($this->atomRoot),
            $args
        ));

        if ($ret !== 0) {
            $this->info('Aborted');
            return 1;
        }

        \arInstall::modifySql();

        $this->info('Loading initial data');

        $this->passthru(sprintf(
            'php %s/symfony propel:data-load',
            escapeshellarg($this->atomRoot)
        ));

        $this->info('Creating search index');

        \arInstall::populateSearchIndex();

        // Add site configuration
        $this->info('Adding site configuration');

        $cultureOptions = ['sourceCulture' => $culture, 'culture' => $culture];

        $siteTitle = $this->getConfigValue($title, sprintf('(%s) Site title', $culture), 'AtoM');
        $siteDesc = $this->getConfigValue($siteDescription, sprintf('(%s) Site description', $culture), 'Test site');
        $siteBaseUrl = $this->getConfigValue($url, 'Site base URL', 'http://127.0.0.1');

        \arInstall::createSetting('siteTitle', $siteTitle, $cultureOptions);
        \arInstall::createSetting('siteDescription', $siteDesc, $cultureOptions);
        \arInstall::createSetting('siteBaseUrl', $siteBaseUrl);

        // Create admin user
        $this->info('Creating admin user');

        $adminOptions = [
            'email' => $email ?: $this->ask('Admin email'),
            'username' => $username ?: $this->ask('Admin username'),
            'password' => $password ?: $this->ask('Admin password'),
        ];

        \addSuperuserTask::addSuperUser($adminOptions['username'], $adminOptions);

        $this->success('Purge completed');

        return 0;
    }

    private function getConfigValue(?string $value, string $prompt, string $default): string
    {
        if (!empty($value)) {
            return $value;
        }

        return $this->ask($prompt, $default);
    }
}
