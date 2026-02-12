<?php

namespace AtomFramework\Console\Commands\I18n;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Consolidate i18n strings from plugin-specific XLIFF directories.
 *
 * Ported from lib/task/i18n/i18nConsolidateTask.class.php.
 */
class ConsolidateCommand extends BaseCommand
{
    protected string $name = 'i18n:consolidate';
    protected string $description = 'Consolidate i18n strings from plugin-specific directories';
    protected string $detailedDescription = <<<'EOF'
Combine all application messages into a single output (XLIFF) file for ease of
use by translators.
EOF;

    protected function configure(): void
    {
        $this->addArgument('culture', 'Message culture', true);
        $this->addArgument('target', 'Target directory', true);
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $culture = $this->argument('culture');
        $target = $this->argument('target');

        if (!file_exists($target)) {
            $this->error('Target directory "' . $target . '" does not exist');

            return 1;
        }

        $this->info(sprintf('Consolidating "%s" i18n messages', $culture));

        $configuration = \sfProjectConfiguration::getActive();
        $i18n = new \sfI18N($configuration, new \sfNoCache(), ['source' => 'XLIFF', 'debug' => false]);
        $extract = new \QubitI18nConsolidatedExtract($i18n, $culture, ['target' => $target]);
        $extract->extract();
        $extract->save();

        $this->success('Consolidation complete');

        return 0;
    }
}
