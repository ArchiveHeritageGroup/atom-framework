<?php

/**
 * Pure-logic checks for the plugin-manager guards.
 *
 * No database and no framework bootstrap: findPluginDirectory() and canEnable()
 * touch only the filesystem, which is the whole point of extracting them. Run:
 *
 *   php tests/extension_protection_test.php
 *
 * These exist because enabling a plugin whose files are absent took PSIS down on
 * 4 September 2026, and the guard that prevents it must not quietly stop working.
 */

require_once __DIR__ . '/../src/Extensions/ExtensionProtection.php';

use AtomFramework\Extensions\ExtensionProtection;

$passed = 0;
$failed = 0;

function check(string $label, $got, $want): void
{
    global $passed, $failed;
    if ($got === $want) {
        ++$passed;
        printf("  PASS  %s\n", $label);

        return;
    }
    ++$failed;
    printf("  FAIL  %s\n        expected: %s\n        actual:   %s\n", $label, var_export($want, true), var_export($got, true));
}

$p = new ExtensionProtection();

echo "\nPlugin directory resolution\n";

// A plugin that is present in every install: Symfony's own, which lives outside
// both plugins/ and atom-ahg-plugins/ and is the case a two-root check misses.
check('sfPropelPlugin resolves (vendor root)', null !== $p->findPluginDirectory('sfPropelPlugin'), true);
check('ahgThemeB5Plugin resolves', null !== $p->findPluginDirectory('ahgThemeB5Plugin'), true);
check('an absent plugin does not resolve', $p->findPluginDirectory('arArchivesCanadaPlugin'), null);
check('a nonsense name does not resolve', $p->findPluginDirectory('NoSuchPluginXyz'), null);

echo "\nName validation (the value reaches a filesystem path)\n";

check('path traversal rejected', $p->findPluginDirectory('../../etc'), null);
check('slash rejected', $p->findPluginDirectory('plugins/ahgCorePlugin'), null);
check('empty rejected', $p->findPluginDirectory(''), null);
check('null byte rejected', $p->findPluginDirectory("ahgCorePlugin\0"), null);

echo "\nEnable guard\n";

$absent = $p->canEnable('arArchivesCanadaPlugin');
check('absent plugin cannot be enabled', $absent['can_enable'], false);
check('the reason names the plugin', str_contains((string) $absent['reason'], 'arArchivesCanadaPlugin'), true);

$present = $p->canEnable('ahgThemeB5Plugin');
check('present plugin can be enabled', $present['can_enable'], true);
check('no reason when allowed', $present['reason'], null);

printf("\n%d passed, %d failed\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
