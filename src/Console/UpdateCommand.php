<?php

namespace AtomFramework\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command
{
    protected static $defaultName = 'update';
    protected static $defaultDescription = 'Update framework and plugins from GitHub';

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addOption('framework', 'f', InputOption::VALUE_NONE, 'Update framework only')
            ->addOption('plugins', 'p', InputOption::VALUE_NONE, 'Update plugins only')
            ->setHelp('Pulls latest code from GitHub for framework and plugins, clears cache.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $frameworkPath = dirname(__DIR__, 2);
        $atomRoot = dirname($frameworkPath);
        $pluginsPath = $atomRoot . '/atom-ahg-plugins';
        
        $frameworkOnly = $input->getOption('framework');
        $pluginsOnly = $input->getOption('plugins');
        $updateBoth = !$frameworkOnly && !$pluginsOnly;

        $output->writeln('<info>AHG Update</info>');
        $output->writeln('');

        // Update framework
        if ($updateBoth || $frameworkOnly) {
            $output->writeln('<comment>Updating framework...</comment>');
            if (is_dir($frameworkPath . '/.git')) {
                $result = $this->runGit($frameworkPath, 'pull origin main');
                $output->writeln("  " . trim($result));
            } else {
                $output->writeln('  <error>Not a git repository</error>');
            }
        }

        // Update plugins
        if ($updateBoth || $pluginsOnly) {
            $output->writeln('<comment>Updating plugins...</comment>');
            if (is_dir($pluginsPath . '/.git')) {
                $result = $this->runGit($pluginsPath, 'pull origin main');
                $output->writeln("  " . trim($result));
            } else {
                $output->writeln('  <error>Not a git repository: ' . $pluginsPath . '</error>');
            }
        }

        // Clear cache
        $output->writeln('<comment>Clearing cache...</comment>');
        $cacheDir = $atomRoot . '/cache';
        if (is_dir($cacheDir)) {
            array_map('unlink', glob($cacheDir . '/*') ?: []);
            $output->writeln('  <info>✓</info> Cache cleared');
        }

        $output->writeln('');
        $output->writeln('<info>Update complete.</info>');
        $output->writeln('  Restart PHP-FPM: <comment>sudo systemctl restart php8.3-fpm</comment>');

        return Command::SUCCESS;
    }

    private function runGit(string $path, string $command): string
    {
        $fullCommand = sprintf('cd %s && git %s 2>&1', escapeshellarg($path), $command);
        return shell_exec($fullCommand) ?? 'Error running git command';
    }
}
