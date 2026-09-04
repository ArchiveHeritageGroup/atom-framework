<?php

/**
 * Checks for the plugin-manager guards.
 *
 *   php tests/extension_protection_test.php
 *
 * HERMETIC BY CONSTRUCTION. The positive cases build a throwaway directory tree in
 * the system temp dir and pass it in as the root, rather than asserting that real
 * plugin directories exist on the machine running the tests.
 *
 * The first version of this file did the latter and is why: it passed on a live
 * AtoM instance and failed four assertions the moment CI ran it against a bare
 * checkout, because there is no AtoM install above the repository there. A test
 * that only passes on one machine is not testing the code.
 *
 * These guards exist because enabling a plugin whose files are absent took PSIS
 * down on 4 September 2026.
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

// A fabricated instance root: one plugin in each of the three legitimate places.
$root = sys_get_temp_dir() . '/ext_protection_test_' . getmypid();
foreach ([
    '/plugins/somePluginPlugin',
    '/atom-ahg-plugins/ahgSomethingPlugin',
    '/vendor/symfony/lib/plugins/sfPropelPlugin',
] as $dir) {
    mkdir($root . $dir, 0777, true);
}

$p = new ExtensionProtection();

echo "\nPlugin directory resolution, across all three roots\n";

check('resolves under plugins/', null !== $p->findPluginDirectory('somePluginPlugin', $root), true);
check('resolves under atom-ahg-plugins/', null !== $p->findPluginDirectory('ahgSomethingPlugin', $root), true);
// The third root is the one a two-root check misses: sfPropelPlugin ships inside
// Symfony and lives nowhere else, so omitting it reports a core plugin as missing.
check('resolves under vendor/symfony/lib/plugins/', null !== $p->findPluginDirectory('sfPropelPlugin', $root), true);
check('an absent plugin does not resolve', $p->findPluginDirectory('arArchivesCanadaPlugin', $root), null);

echo "\nName validation (the value reaches a filesystem path)\n";

check('path traversal rejected', $p->findPluginDirectory('../../etc', $root), null);
check('slash rejected', $p->findPluginDirectory('plugins/somePluginPlugin', $root), null);
check('empty rejected', $p->findPluginDirectory('', $root), null);
check('null byte rejected', $p->findPluginDirectory("somePluginPlugin\0", $root), null);

echo "\nEnable guard\n";

$absent = $p->canEnable('arArchivesCanadaPlugin', $root);
check('absent plugin cannot be enabled', $absent['can_enable'], false);
check('the reason names the plugin', str_contains((string) $absent['reason'], 'arArchivesCanadaPlugin'), true);

$present = $p->canEnable('somePluginPlugin', $root);
check('present plugin can be enabled', $present['can_enable'], true);
check('no reason when allowed', $present['reason'], null);

echo "\nAn unknown root refuses rather than guessing\n";

check('missing root resolves nothing', $p->findPluginDirectory('somePluginPlugin', $root . '_nonexistent'), null);

foreach (['/plugins/somePluginPlugin', '/atom-ahg-plugins/ahgSomethingPlugin', '/vendor/symfony/lib/plugins/sfPropelPlugin'] as $dir) {
    @rmdir($root . $dir);
}
foreach (['/vendor/symfony/lib/plugins', '/vendor/symfony/lib', '/vendor/symfony', '/vendor', '/plugins', '/atom-ahg-plugins'] as $dir) {
    @rmdir($root . $dir);
}
@rmdir($root);

printf("\n%d passed, %d failed\n", $passed, $failed);
exit(0 === $failed ? 0 : 1);
