<?php

namespace AtomFramework\Console\Commands\I18n;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Rectify existing i18n strings for the application.
 *
 * Ported from lib/task/i18n/i18nRectifyTask.class.php.
 * Copies i18n target messages from application source to plugin source,
 * preventing loss of translated strings during source fragmentation.
 */
class RectifyCommand extends BaseCommand
{
    protected string $name = 'i18n:rectify';
    protected string $description = 'Rectify existing i18n strings for the application';
    protected string $detailedDescription = <<<'EOF'
Copy i18n target messages from application source to plugin source.
This prevents losing translated strings in the fragmentation of
application message source into multiple plugin message sources.
EOF;

    protected function configure(): void
    {
        $this->addArgument('culture', 'The target culture', true);
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $culture = $this->argument('culture');

        $this->info(sprintf('Rectifying existing i18n strings'));

        // Get i18n configuration from factories.yml
        $configuration = \sfProjectConfiguration::getActive();
        $config = \sfFactoryConfigHandler::getConfiguration($configuration->getConfigPaths('config/factories.yml'));

        $class = $config['i18n']['class'];
        $params = $config['i18n']['param'];
        unset($params['cache']);

        // Get current (saved) messages from ALL sources (app and plugin)
        $i18n = new $class($configuration, new \sfNoCache(), $params);
        $i18n->getMessageSource()->setCulture($culture);
        $i18n->getMessageSource()->load();

        $currentMessages = [];
        foreach ($i18n->getMessageSource()->read() as $catalogue => $translations) {
            foreach ($translations as $key => $value) {
                // Use first message that has a valid translation
                if (0 < strlen(trim($value[0])) && !isset($currentMessages[$key][0])) {
                    $currentMessages[$key] = $value;
                }
            }
        }

        // Loop through plugins
        $pluginNames = \sfFinder::type('dir')->maxdepth(0)->relative()->not_name('.')->in(\sfConfig::get('sf_plugins_dir'));
        foreach ($pluginNames as $pluginName) {
            $this->info(sprintf('Rectifying %s plugin strings', $pluginName));

            $messageSource = \sfMessageSource::factory(
                $config['i18n']['param']['source'],
                \sfConfig::get('sf_plugins_dir') . '/' . $pluginName . '/i18n'
            );
            $messageSource->setCulture($culture);
            $messageSource->load();

            // If the current plugin source *doesn't* have a translation, then try
            // and get translated value from $currentMessages
            foreach ($messageSource->read() as $catalogue => $translations) {
                foreach ($translations as $key => &$value) {
                    if (isset($currentMessages[$key])) {
                        $messageSource->update($key, $currentMessages[$key][0], $value[2]);
                    }
                }
            }

            $messageSource->save();
        }

        $this->success('Rectification complete');

        return 0;
    }
}
