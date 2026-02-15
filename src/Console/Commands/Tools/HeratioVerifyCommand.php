<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Bridges\PropelBridge;
use AtomFramework\Console\BaseCommand;
use AtomFramework\Http\Compatibility\SfConfigShim;
use AtomFramework\Http\Compatibility\SfContextAdapter;
use AtomFramework\Http\Compatibility\SfProjectConfigurationShim;
use AtomFramework\Services\ConfigService;
use AtomFramework\Services\Write\WriteServiceFactory;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Verify the standalone Heratio boot chain.
 *
 * Checks that all shims, database, settings, and plugin configurations
 * are working correctly for standalone (non-Symfony) operation.
 */
class HeratioVerifyCommand extends BaseCommand
{
    protected string $name = 'heratio:verify';
    protected string $description = 'Verify standalone Heratio boot chain readiness';

    private int $passed = 0;
    private int $failed = 0;
    private int $warnings = 0;

    protected function handle(): int
    {
        $this->newline();
        $this->bold('  Heratio Standalone Boot Verification');
        $this->newline();

        $rootDir = $this->getAtomRoot();

        // 1. Config file
        $this->check(
            'config/config.php exists',
            file_exists($rootDir . '/config/config.php')
        );

        // 2. Database connectivity
        $dbOk = false;
        try {
            DB::select('SELECT 1');
            $dbOk = true;
        } catch (\Throwable $e) {
            // fail
        }
        $this->check('Database connectivity', $dbOk);

        // 3. SfConfigShim
        $shimAvailable = class_exists(SfConfigShim::class);
        $this->check('SfConfigShim class available', $shimAvailable);

        // 4. Bootstrap paths
        SfConfigShim::register();
        SfConfigShim::bootstrap($rootDir);
        $this->check('sf_root_dir set', !empty(\sfConfig::get('sf_root_dir')));
        $this->check('sf_plugins_dir set', !empty(\sfConfig::get('sf_plugins_dir')));
        $this->check('sf_upload_dir set', !empty(\sfConfig::get('sf_upload_dir')));
        $this->check('plugins/ directory exists', is_dir($rootDir . '/plugins'));
        $this->check('uploads/ directory exists', is_dir($rootDir . '/uploads'));

        // 5. Settings from DB
        ConfigService::loadFromDatabase('en');
        $siteTitle = \sfConfig::get('app_siteTitle', '');
        $this->check('Settings loaded from DB (siteTitle)', !empty($siteTitle), $siteTitle);

        // 6. PropelBridge (must boot BEFORE app.yml — sfYaml depends on sfCoreAutoload)
        $propelBooted = PropelBridge::isBooted();
        if (!$propelBooted) {
            try {
                PropelBridge::boot($rootDir);
                $propelBooted = PropelBridge::isBooted();
            } catch (\Throwable $e) {
                // fail
            }
        }
        $this->check('PropelBridge booted', $propelBooted);

        // 7. app.yml (after PropelBridge — needs sfYaml from sfCoreAutoload)
        ConfigService::loadFromAppYaml($rootDir);
        $cspHeader = \sfConfig::get('app_csp_response_header', '');
        $this->check('app.yml loaded (CSP config)', !empty($cspHeader), $cspHeader ?: 'not set');

        // 8. CSP nonce generation
        $nonce = bin2hex(random_bytes(16));
        \sfConfig::set('csp_nonce', 'nonce=' . $nonce);
        $this->check('CSP nonce generation', !empty(\sfConfig::get('csp_nonce')));

        // 9. Qubit model stubs — load via compatibility autoloader, then verify
        $compatAutoload = $rootDir . '/atom-framework/src/Compatibility/autoload.php';
        if (file_exists($compatAutoload)) {
            require_once $compatAutoload;
        }
        $this->check('Compatibility autoload.php loaded', file_exists($compatAutoload));

        $stubClasses = [
            'QubitTerm', 'QubitTaxonomy', 'QubitInformationObject', 'QubitActor',
            'QubitRepository', 'QubitDigitalObject', 'QubitObject', 'QubitRelation',
            'QubitPhysicalObject', 'QubitObjectTermRelation', 'QubitAccession',
            'QubitEvent', 'QubitOtherName', 'QubitMenu', 'QubitDonor',
            'QubitContactInformation', 'QubitStaticPage', 'QubitRightsHolder',
        ];
        $stubsLoaded = 0;
        foreach ($stubClasses as $stubClass) {
            if (class_exists($stubClass, false)) {
                $stubsLoaded++;
            }
        }
        $this->check('Qubit model stubs available', $stubsLoaded === count($stubClasses), "{$stubsLoaded}/" . count($stubClasses));

        // 9b. Spot-check critical constants
        $this->check('QubitTerm::MASTER_ID = 140', defined('QubitTerm::MASTER_ID') && \QubitTerm::MASTER_ID === 140);
        $this->check('QubitTerm::ROOT_ID = 110', defined('QubitTerm::ROOT_ID') && \QubitTerm::ROOT_ID === 110);
        $this->check('QubitTaxonomy::ROOT_ID = 30', defined('QubitTaxonomy::ROOT_ID') && \QubitTaxonomy::ROOT_ID === 30);
        $this->check('QubitInformationObject::ROOT_ID = 1', defined('QubitInformationObject::ROOT_ID') && \QubitInformationObject::ROOT_ID === 1);
        $this->check('QubitActor::ROOT_ID = 3', defined('QubitActor::ROOT_ID') && \QubitActor::ROOT_ID === 3);
        $this->check('QubitRepository::ROOT_ID = 6', defined('QubitRepository::ROOT_ID') && \QubitRepository::ROOT_ID === 6);

        // 10. sfPluginConfiguration available
        $sfPluginConfigOk = class_exists('sfPluginConfiguration', true);
        $this->check('sfPluginConfiguration autoloadable', $sfPluginConfigOk);

        // 11. SfProjectConfigurationShim
        if (!class_exists('sfProjectConfiguration', false)) {
            class_alias(SfProjectConfigurationShim::class, 'sfProjectConfiguration');
        }
        $shimActive = is_a('sfProjectConfiguration', SfProjectConfigurationShim::class, true);
        $this->check('sfProjectConfiguration shimmed', $shimActive || class_exists('sfProjectConfiguration', false));

        // 12. Plugin configurations — initialize them (populates sf_enabled_modules, app_b5_theme)
        $enabledCount = 0;
        $configuredCount = 0;
        $initializedCount = 0;
        try {
            $enabledPlugins = DB::table('atom_plugin')
                ->where(function ($q) {
                    $q->where('is_enabled', 1)->orWhere('is_core', 1);
                })
                ->orderBy('load_order')
                ->pluck('name')
                ->toArray();
            $enabledCount = count($enabledPlugins);

            // Initialize sf_enabled_modules as empty array before plugin init
            \sfConfig::set('sf_enabled_modules', \sfConfig::get('sf_enabled_modules', []));

            $projectConfig = \sfProjectConfiguration::getActive();

            foreach ($enabledPlugins as $pluginName) {
                $configFile = $rootDir . '/plugins/' . $pluginName . '/config/'
                    . $pluginName . 'Configuration.class.php';
                if (file_exists($configFile)) {
                    $configuredCount++;

                    // Actually initialize the plugin configuration
                    $className = $pluginName . 'Configuration';
                    if (!class_exists($className, false)) {
                        try {
                            require_once $configFile;
                            if (class_exists($className, false)) {
                                new $className($projectConfig, $rootDir . '/plugins/' . $pluginName);
                                $initializedCount++;
                            }
                        } catch (\Throwable $e) {
                            // Non-fatal — continue
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // fail
        }
        $this->check(
            'Plugin configurations initialized',
            $initializedCount > 0,
            "{$initializedCount}/{$configuredCount} initialized, {$enabledCount} enabled"
        );

        // 13. sf_enabled_modules
        $modules = \sfConfig::get('sf_enabled_modules', []);
        $moduleCount = is_array($modules) ? count($modules) : 0;
        $this->check('sf_enabled_modules populated', $moduleCount > 0, "{$moduleCount} modules");

        // 14. app_b5_theme
        $b5 = \sfConfig::get('app_b5_theme', false);
        $this->check('app_b5_theme set', (bool) $b5, $b5 ? 'true' : 'false');

        // 15. Template helpers — load blade_shims.php
        $shimsFile = $rootDir . '/atom-framework/src/Views/blade_shims.php';
        if (file_exists($shimsFile)) {
            require_once $shimsFile;
        }
        $helpersOk = function_exists('url_for') && function_exists('__')
            && function_exists('link_to') && function_exists('slot')
            && function_exists('get_partial') && function_exists('image_tag');
        $this->check('Template helper functions available', $helpersOk);

        // 16. blade_shims.php loaded
        $this->check('blade_shims.php exists', file_exists($shimsFile));

        // 17. WriteService factory mode detection
        WriteServiceFactory::reset();
        $isStandalone = !class_exists('QubitObject', false) || !method_exists('QubitObject', 'save');
        $settingsService = WriteServiceFactory::settings();
        $expectedClass = $isStandalone
            ? 'AtomFramework\\Services\\Write\\StandaloneSettingsWriteService'
            : 'AtomFramework\\Services\\Write\\PropelSettingsWriteService';
        $this->check(
            'WriteServiceFactory mode detection',
            $settingsService instanceof $expectedClass,
            $isStandalone ? 'standalone' : 'propel'
        );

        // 18. WriteService: settings save + read roundtrip
        $settingsRoundtrip = false;
        $testSettingName = '_heratio_verify_test_' . time();
        try {
            $settingsService->save($testSettingName, 'verify_ok', 'heratio_test');
            $row = DB::table('setting')
                ->where('name', $testSettingName)
                ->where('scope', 'heratio_test')
                ->first();
            if ($row) {
                $i18n = DB::table('setting_i18n')
                    ->where('id', $row->id)
                    ->where('culture', 'en')
                    ->first();
                $settingsRoundtrip = $i18n && 'verify_ok' === $i18n->value;
                // Clean up
                $settingsService->delete($testSettingName, 'heratio_test');
            }
        } catch (\Throwable $e) {
            // fail
        }
        $this->check('WriteService: settings roundtrip', $settingsRoundtrip);

        // 19. WriteService: term create + slug verification
        $termCreateOk = false;
        try {
            $termService = WriteServiceFactory::term();
            // Use a test taxonomy (subject = 35)
            $term = $termService->createTerm(35, '_heratio_verify_term_' . time(), 'en');
            if ($term && $term->id > 0) {
                // Verify slug exists
                $slug = DB::table('slug')->where('object_id', $term->id)->first();
                $termCreateOk = null !== $slug;
                // Clean up
                $termService->deleteTerm($term->id);
            }
        } catch (\Throwable $e) {
            // fail
        }
        $this->check('WriteService: term create + slug', $termCreateOk);

        // 20. WriteService: actor create + i18n verification
        $actorCreateOk = false;
        try {
            $actorService = WriteServiceFactory::actor();
            $actorName = '_heratio_verify_actor_' . time();
            $actorId = $actorService->createActor([
                'authorized_form_of_name' => $actorName,
                'entity_type_id' => 132,
            ], 'en');
            if ($actorId > 0) {
                $i18n = DB::table('actor_i18n')
                    ->where('id', $actorId)
                    ->where('culture', 'en')
                    ->first();
                $actorCreateOk = $i18n && $i18n->authorized_form_of_name === $actorName;
                // Clean up
                DB::table('slug')->where('object_id', $actorId)->delete();
                DB::table('actor_i18n')->where('id', $actorId)->delete();
                DB::table('actor')->where('id', $actorId)->delete();
                DB::table('object')->where('id', $actorId)->delete();
            }
        } catch (\Throwable $e) {
            // fail
        }
        $this->check('WriteService: actor create + i18n', $actorCreateOk);

        // 21. WriteService: all 13 factory methods return correct types
        $factoryMethods = [
            'settings' => 'AtomFramework\\Services\\Write\\SettingsWriteServiceInterface',
            'acl' => 'AtomFramework\\Services\\Write\\AclWriteServiceInterface',
            'digitalObject' => 'AtomFramework\\Services\\Write\\DigitalObjectWriteServiceInterface',
            'term' => 'AtomFramework\\Services\\Write\\TermWriteServiceInterface',
            'accession' => 'AtomFramework\\Services\\Write\\AccessionWriteServiceInterface',
            'import' => 'AtomFramework\\Services\\Write\\ImportWriteServiceInterface',
            'physicalObject' => 'AtomFramework\\Services\\Write\\PhysicalObjectWriteServiceInterface',
            'user' => 'AtomFramework\\Services\\Write\\UserWriteServiceInterface',
            'actor' => 'AtomFramework\\Services\\Write\\ActorWriteServiceInterface',
            'feedback' => 'AtomFramework\\Services\\Write\\FeedbackWriteServiceInterface',
            'requestToPublish' => 'AtomFramework\\Services\\Write\\RequestToPublishWriteServiceInterface',
            'job' => 'AtomFramework\\Services\\Write\\JobWriteServiceInterface',
            'informationObject' => 'AtomFramework\\Services\\Write\\InformationObjectWriteServiceInterface',
        ];
        $factoryOk = 0;
        foreach ($factoryMethods as $method => $interface) {
            try {
                $instance = WriteServiceFactory::$method();
                if ($instance instanceof $interface) {
                    $factoryOk++;
                }
            } catch (\Throwable $e) {
                // fail
            }
        }
        $this->check(
            'WriteService: factory interfaces',
            $factoryOk === count($factoryMethods),
            "{$factoryOk}/" . count($factoryMethods)
        );

        // 22. Standalone compatibility stubs present
        $compatDir = $rootDir . '/atom-framework/src/Compatibility';
        $stubFiles = [
            'sfEvent.php',
            'sfSimpleAutoload.php',
            'sfPluginConfiguration.php',
        ];
        $stubsPresent = 0;
        foreach ($stubFiles as $stubFile) {
            if (file_exists($compatDir . '/' . $stubFile)) {
                $stubsPresent++;
            }
        }
        $this->check(
            'Standalone compatibility stubs present',
            $stubsPresent === count($stubFiles),
            "{$stubsPresent}/" . count($stubFiles)
        );

        // 23. sfEvent stub API compatible
        $sfEventOk = false;
        try {
            // Load the stub if the real class isn't loaded
            if (!class_exists('sfEvent', false)) {
                require_once $compatDir . '/sfEvent.php';
            }
            $testEvent = new \sfEvent($this, 'test.event', ['key' => 'value']);
            $sfEventOk = $testEvent->getSubject() === $this
                && 'test.event' === $testEvent->getName()
                && $testEvent instanceof \ArrayAccess
                && isset($testEvent['key'])
                && 'value' === $testEvent['key'];
        } catch (\Throwable $e) {
            // fail
        }
        $this->check('sfEvent stub API compatible', $sfEventOk);

        // 24. sfPluginConfiguration stub API compatible
        $sfPluginConfigStubOk = false;
        try {
            if (!class_exists('sfPluginConfiguration', false)) {
                require_once $compatDir . '/sfPluginConfiguration.php';
            }
            $ref = new \ReflectionClass('sfPluginConfiguration');
            $sfPluginConfigStubOk = $ref->isAbstract()
                && $ref->hasMethod('initialize')
                && $ref->hasMethod('setup')
                && $ref->hasMethod('configure')
                && $ref->hasMethod('getRootDir')
                && $ref->hasMethod('getName')
                && $ref->hasMethod('initializeAutoload');
        } catch (\Throwable $e) {
            // fail
        }
        $this->check('sfPluginConfiguration stub API compatible', $sfPluginConfigStubOk);

        // 25. ServiceProvider base class available
        $serviceProviderOk = class_exists(\AtomFramework\Http\ServiceProvider::class);
        $this->check('ServiceProvider base class available', $serviceProviderOk);

        // 26. Kernel has standalone mode detection
        $kernelStandaloneOk = false;
        try {
            $ref = new \ReflectionClass(\AtomFramework\Http\Kernel::class);
            $kernelStandaloneOk = $ref->hasMethod('isStandaloneMode')
                && $ref->getMethod('isStandaloneMode')->isPublic();
        } catch (\Throwable $e) {
            // fail
        }
        $this->check('Kernel has standalone mode detection', $kernelStandaloneOk);

        // Summary
        $this->newline();
        $total = $this->passed + $this->failed + $this->warnings;
        $this->bold("  Results: {$this->passed}/{$total} passed");
        if ($this->failed > 0) {
            $this->error("  {$this->failed} FAILED");
        }
        if ($this->warnings > 0) {
            $this->warning("  {$this->warnings} warnings");
        }
        $this->newline();

        return $this->failed > 0 ? 1 : 0;
    }

    private function check(string $label, bool $ok, string $detail = ''): void
    {
        $status = $ok ? "\033[32mPASS\033[0m" : "\033[31mFAIL\033[0m";
        $suffix = $detail ? " ({$detail})" : '';

        if ($ok) {
            $this->passed++;
        } else {
            $this->failed++;
        }

        $this->line("  [{$status}] {$label}{$suffix}");
    }
}
