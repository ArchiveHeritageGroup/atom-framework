<?php
/**
 * AclService inherits permissions down the description tree (13 Nov 2026).
 * Grants on the root (object 1), as base AtoM sites have them, must reach every
 * description: an anonymous visitor may see thumbnails and reference copies, not masters.
 *
 *   php atom-framework/tests/acl-inheritance-check.php <atom-root> <description-id>
 *   e.g. php atom-framework/tests/acl-inheritance-check.php /usr/share/nginx/archive 913568
 */
$root = $argv[1] ?? '/usr/share/nginx/archive';
$id = (int) ($argv[2] ?? 0);
require $root . '/atom-framework/vendor/autoload.php';
$cfg = (require $root . '/config/config.php')['all']['propel']['param'];
preg_match('/dbname=([^;]+)/', $cfg['dsn'], $db);
preg_match('/host=([^;]+)/', $cfg['dsn'], $host);
$cap = new Illuminate\Database\Capsule\Manager();
$cap->addConnection(['driver' => 'mysql', 'host' => $host[1] ?? 'localhost', 'database' => $db[1],
    'username' => $cfg['username'], 'password' => $cfg['password'], 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']);
$cap->setAsGlobal();
require $root . '/atom-framework/src/Services/AclService.php';
if ($id <= 0) {
    fwrite(STDERR, "give a published description id\n");
    exit(2);
}
$expect = ['readThumbnail' => true, 'readReference' => true, 'readMaster' => false];
$fail = 0;
foreach ($expect as $action => $want) {
    $got = \AtomExtensions\Services\AclService::check((object) ['id' => $id], $action);
    printf("anonymous %-14s %-8s %s\n", $action, $got ? 'allowed' : 'denied', $got === $want ? 'ok' : 'FAIL');
    $fail += (int) ($got !== $want);
}
echo $fail ? "FAIL\n" : "PASS\n";
exit($fail ? 1 : 0);
