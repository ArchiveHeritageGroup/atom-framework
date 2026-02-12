<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Run the AtoM installation process.
 *
 * Ported from lib/task/tools/installTask.class.php.
 * This is a complex interactive task that configures database, search,
 * site settings, and admin user. Due to its deep integration with
 * Symfony config generation and Propel schema initialization, it
 * delegates the heavy lifting to the original Symfony task via passthru.
 */
class InstallCommand extends BaseCommand
{
    protected string $name = 'tools:install';
    protected string $description = 'Run the AtoM installation process';
    protected string $detailedDescription = <<<'EOF'
Configure and initialize a new AtoM instance:

- Configure database connection
- Configure Elasticsearch
- Configure site title, description, and base URL
- Configure admin user credentials
- Initialize database schema (propel insert-sql)
- Load initial data (taxonomies, terms, etc.)
- Create Elasticsearch index
- Add site configuration settings
- Create admin user

Options:
    --demo              Use default demo values (skip interactive prompts)
    --no-confirmation   Skip all confirmation prompts
    --database-host     Database host (default: localhost)
    --database-port     Database port (default: 3306)
    --database-name     Database name (default: atom)
    --database-user     Database user (default: atom)
    --database-password Database password
    --search-host       Elasticsearch host (default: localhost)
    --search-port       Elasticsearch port (default: 9200)
    --search-index      Elasticsearch index (default: atom)
    --site-title        Site title
    --site-description  Site description
    --site-base-url     Site base URL
    --admin-email       Admin email
    --admin-username    Admin username
    --admin-password    Admin password
EOF;

    protected function configure(): void
    {
        $this->addOption('demo', null, 'Use default demo values');
        $this->addOption('no-confirmation', null, 'Skip confirmation prompts');
        $this->addOption('database-host', null, 'Database host');
        $this->addOption('database-port', null, 'Database port');
        $this->addOption('database-name', null, 'Database name');
        $this->addOption('database-user', null, 'Database user');
        $this->addOption('database-password', null, 'Database password');
        $this->addOption('search-host', null, 'Search host');
        $this->addOption('search-port', null, 'Search port');
        $this->addOption('search-index', null, 'Search index');
        $this->addOption('site-title', null, 'Site title');
        $this->addOption('site-description', null, 'Site description');
        $this->addOption('site-base-url', null, 'Site base URL');
        $this->addOption('admin-email', null, 'Admin email');
        $this->addOption('admin-username', null, 'Admin username');
        $this->addOption('admin-password', null, 'Admin password');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $rootDir = $this->atomRoot;

        // Step 1: Check and clear config files
        $this->info('Checking configuration files');
        $this->clearConfigFiles($rootDir);

        // Step 2: Gather options (interactive or via CLI args)
        $finalOptions = $this->getFinalOptions();

        // Step 3: Create config files
        $this->createConfigFiles($finalOptions);

        // Step 4: Reload config
        $this->reloadConfig($rootDir);

        // Step 5: Test connections
        $this->testConfig($finalOptions);

        // Step 6: Initialize DB and ES
        $this->initializeDbAndEs();

        // Step 7: Add site configuration
        $this->info('Adding site configuration');
        foreach ($finalOptions['site'] as $name => $value) {
            \arInstall::createSetting($name, $value);
        }

        // Step 8: Create admin user
        $this->info('Creating admin user');
        \addSuperuserTask::addSuperUser(
            $finalOptions['admin']['username'],
            $finalOptions['admin']
        );

        $this->success('Installation completed');

        return 0;
    }

    private function clearConfigFiles(string $rootDir): void
    {
        $configFiles = [
            $rootDir . '/apps/qubit/config/settings.yml',
            $rootDir . '/config/config.php',
            $rootDir . '/config/databases.yml',
            $rootDir . '/config/propel.ini',
            $rootDir . '/config/search.yml',
        ];

        $existingConfigFiles = [];
        foreach ($configFiles as $file) {
            if (file_exists($file)) {
                $existingConfigFiles[] = $file;
            }
        }

        if (count($existingConfigFiles) > 0) {
            if (!$this->hasOption('no-confirmation')) {
                $this->warning('The following configuration files already exist and will be overwritten:');
                foreach ($existingConfigFiles as $file) {
                    $this->line("  {$file}");
                }

                if (!$this->confirm('Would you like to continue?')) {
                    $this->info('Aborted');
                    exit(1);
                }
            }

            $this->info('Deleting configuration files');

            $deletionFailures = [];
            foreach ($configFiles as $file) {
                if (file_exists($file) && !unlink($file)) {
                    $deletionFailures[] = $file;
                }
            }

            if (count($deletionFailures) > 0) {
                $this->error("The following configuration files can't be deleted:");
                foreach ($deletionFailures as $file) {
                    $this->line("  {$file}");
                }
                exit(1);
            }
        }
    }

    private function getFinalOptions(): array
    {
        $this->info('Configure database');

        $databaseOptions = [
            'databaseHost' => $this->getOptionValueInteractive(
                'database-host', 'Database host', 'localhost'
            ),
            'databasePort' => $this->getOptionValueInteractive(
                'database-port', 'Database port', '3306'
            ),
            'databaseName' => $this->getOptionValueInteractive(
                'database-name', 'Database name', 'atom'
            ),
            'databaseUsername' => $this->getOptionValueInteractive(
                'database-user', 'Database user', 'atom'
            ),
            'databasePassword' => $this->getOptionValueInteractive(
                'database-password', 'Database password', ''
            ),
        ];

        $this->info('Configure search');

        $searchOptions = [
            'searchHost' => $this->getOptionValueInteractive(
                'search-host', 'Search host', 'localhost'
            ),
            'searchPort' => $this->getOptionValueInteractive(
                'search-port', 'Search port', '9200'
            ),
            'searchIndex' => $this->getOptionValueInteractive(
                'search-index', 'Search index', 'atom'
            ),
        ];

        if ($this->hasOption('demo')) {
            $this->info('Setting demo options');

            $siteOptions = [
                'siteTitle' => 'Demo site',
                'siteDescription' => 'Demo site',
                'siteBaseUrl' => 'http://127.0.0.1',
            ];
            $adminOptions = [
                'email' => 'demo@example.com',
                'username' => 'demo',
                'password' => 'demo',
            ];
        } else {
            $this->info('Configure site');

            $siteOptions = [
                'siteTitle' => $this->getOptionValueInteractive(
                    'site-title', 'Site title', 'AtoM'
                ),
                'siteDescription' => $this->getOptionValueInteractive(
                    'site-description', 'Site description', 'Access to Memory'
                ),
                'siteBaseUrl' => $this->getOptionValueInteractive(
                    'site-base-url', 'Site base URL', 'http://127.0.0.1'
                ),
            ];

            $this->info('Configure admin user');

            $adminOptions = [
                'email' => $this->getOptionValueInteractive(
                    'admin-email', 'Admin email'
                ),
                'username' => $this->getOptionValueInteractive(
                    'admin-username', 'Admin username'
                ),
                'password' => $this->getOptionValueInteractive(
                    'admin-password', 'Admin password'
                ),
            ];
        }

        $this->info('Confirm configuration');

        $this->line("Database host       {$databaseOptions['databaseHost']}");
        $this->line("Database port       {$databaseOptions['databasePort']}");
        $this->line("Database name       {$databaseOptions['databaseName']}");
        $this->line("Database user       {$databaseOptions['databaseUsername']}");
        $this->line("Database password   {$databaseOptions['databasePassword']}");
        $this->line("Search host         {$searchOptions['searchHost']}");
        $this->line("Search port         {$searchOptions['searchPort']}");
        $this->line("Search index        {$searchOptions['searchIndex']}");
        $this->line("Site title          {$siteOptions['siteTitle']}");
        $this->line("Site description    {$siteOptions['siteDescription']}");
        $this->line("Site base URL       {$siteOptions['siteBaseUrl']}");
        $this->line("Admin email         {$adminOptions['email']}");
        $this->line("Admin username      {$adminOptions['username']}");
        $this->line("Admin password      {$adminOptions['password']}");

        if (!$this->hasOption('no-confirmation') && !$this->confirm('Confirm configuration and continue?')) {
            $this->info('Aborted');
            exit(1);
        }

        return [
            'database' => $databaseOptions,
            'search' => $searchOptions,
            'site' => $siteOptions,
            'admin' => $adminOptions,
        ];
    }

    private function createConfigFiles(array $options): void
    {
        $this->info('Setting configuration');

        try {
            \arInstall::createDirectories();
            \arInstall::checkWritablePaths();
            \arInstall::createDatabasesYml();
            \arInstall::createPropelIni();
            \arInstall::createAppChallengeYml();
            \arInstall::createSettingsYml();
            \arInstall::createSfSymlink();
            \arInstall::configureDatabase($options['database']);
            \arInstall::configureSearch($options['search']);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            exit(1);
        }
    }

    private function reloadConfig(string $rootDir): void
    {
        // Clear cache via Symfony
        $this->passthru(sprintf('php %s/symfony cc', escapeshellarg($rootDir)));

        \Propel::configure($rootDir . '/config/config.php');
        \Propel::setDefaultDB('propel');
        \sfConfig::set('app_avoid_routing_propel_exceptions', true);

        $configuration = \ProjectConfiguration::getApplicationConfiguration(
            'qubit', 'cli', false
        );
        $context = \sfContext::createInstance($configuration);
        $context->databaseManager->loadConfiguration();
        \arElasticSearchPluginConfiguration::reloadConfig(
            $context->getConfiguration()
        );
    }

    private function testConfig(array $options): void
    {
        try {
            \sfContext::getInstance()->getDatabaseConnection('propel');
        } catch (\Exception $e) {
            $this->error('Database connection failure:');
            $this->error($e->getMessage());
            exit(1);
        }

        try {
            \arInstall::checkSearchConnection($options['search']);
        } catch (\Exception $e) {
            $this->error('Elasticsearch connection failure:');
            $this->error($e->getMessage());
            exit(1);
        }
    }

    private function initializeDbAndEs(): void
    {
        $this->info('Initializing database');

        // Delegate to Symfony for the heavy SQL insertion
        $args = '';
        if ($this->hasOption('no-confirmation')) {
            $args .= ' --no-confirmation';
        }
        $ret = $this->passthru(sprintf(
            'php %s/symfony propel:insert-sql%s',
            escapeshellarg($this->atomRoot),
            $args
        ));

        if ($ret !== 0) {
            $this->info('Aborted');
            exit(1);
        }

        \arInstall::modifySql();

        $this->info('Loading initial data');

        // Delegate to Symfony for data loading (uses sfPropelData)
        $this->passthru(sprintf(
            'php %s/symfony propel:data-load',
            escapeshellarg($this->atomRoot)
        ));

        $this->info('Creating search index');

        \arInstall::populateSearchIndex();
    }

    private function getOptionValueInteractive(
        string $name,
        string $prompt,
        ?string $default = null
    ): string {
        $value = $this->option($name);

        if (null !== $value) {
            return $value;
        }

        return $this->ask($prompt, $default);
    }
}
