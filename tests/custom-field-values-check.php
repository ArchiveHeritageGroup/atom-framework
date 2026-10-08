<?php
/**
 * CustomFieldValues check (#202). Builds a THROWAWAY database with the custom
 * field tables, adds fields of each type, and checks the text exporters get.
 * The database is dropped at the end; the AtoM database is never touched.
 *
 * Run: php atom-framework/tests/custom-field-values-check.php /path/to/client.cnf
 */
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/src/Services/CustomFieldValues.php';

use AtomFramework\Services\CustomFieldValues as CF;
use Illuminate\Database\Capsule\Manager as DB;

$ini = parse_ini_file($argv[1] ?? '', true)['client'] ?? null;
if (!$ini) { fwrite(STDERR, "usage: php {$argv[0]} client.cnf\n"); exit(2); }
$scratch = 'scratch_custom_field_values_check';
$db = new DB();
$base = ['driver' => 'mysql', 'host' => $ini['host'] ?? 'localhost', 'username' => $ini['user'], 'password' => $ini['password'], 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci'];
$db->addConnection($base + ['database' => 'mysql'], 'admin');
$db->addConnection($base + ['database' => $scratch]);
$db->setAsGlobal();
DB::connection('admin')->statement("DROP DATABASE IF EXISTS {$scratch}");
DB::connection('admin')->statement("CREATE DATABASE {$scratch} CHARACTER SET utf8mb4");
register_shutdown_function(function () use ($scratch) { DB::connection('admin')->statement("DROP DATABASE IF EXISTS {$scratch}"); });

DB::unprepared("CREATE TABLE custom_field_definition (id INT AUTO_INCREMENT PRIMARY KEY, field_key VARCHAR(100), field_label VARCHAR(255), field_type VARCHAR(20), entity_type VARCHAR(50), field_group VARCHAR(100), dropdown_taxonomy VARCHAR(100), is_repeatable TINYINT DEFAULT 0, is_visible_public TINYINT DEFAULT 1, is_active TINYINT DEFAULT 1, sort_order INT DEFAULT 0)");
DB::unprepared("CREATE TABLE custom_field_value (id INT AUTO_INCREMENT PRIMARY KEY, field_definition_id INT, object_id INT, value_text TEXT, value_number DECIMAL(15,4), value_date DATE, value_boolean TINYINT, value_dropdown VARCHAR(100), sequence INT DEFAULT 0)");
DB::unprepared("CREATE TABLE ahg_dropdown (id INT AUTO_INCREMENT PRIMARY KEY, taxonomy VARCHAR(100), code VARCHAR(100), label VARCHAR(255))");

$def = fn ($key, $type, $extra = []) => DB::table('custom_field_definition')->insertGetId($extra + ['field_key' => $key, 'field_label' => ucfirst($key), 'field_type' => $type, 'entity_type' => 'informationobject', 'sort_order' => 0]);
$val = fn ($d, $o, $cols, $seq = 0) => DB::table('custom_field_value')->insert($cols + ['field_definition_id' => $d, 'object_id' => $o, 'sequence' => $seq]);
$t = $def('donor_ref', 'text', ['sort_order' => 1]);
$n = $def('weight', 'number', ['sort_order' => 2]);
$b = $def('digitised', 'boolean', ['sort_order' => 3]);
$d = $def('format', 'dropdown', ['sort_order' => 4, 'dropdown_taxonomy' => 'fmt']);
$r = $def('keyword', 'text', ['sort_order' => 5, 'is_repeatable' => 1]);
$p = $def('internal_note', 'text', ['sort_order' => 6, 'is_visible_public' => 0]);
$x = $def('old', 'text', ['sort_order' => 7, 'is_active' => 0]);
DB::table('ahg_dropdown')->insert(['taxonomy' => 'fmt', 'code' => 'ph', 'label' => 'Photograph']);
$val($t, 10, ['value_text' => 'D-77']); $val($n, 10, ['value_number' => 2.5]); $val($b, 10, ['value_boolean' => 0]);
$val($d, 10, ['value_dropdown' => 'ph']); $val($r, 10, ['value_text' => 'boats'], 1); $val($r, 10, ['value_text' => 'Nile'], 0);
$val($p, 10, ['value_text' => 'staff only']); $val($x, 10, ['value_text' => 'retired']); $val($t, 11, ['value_text' => 'D-78']);

$fail = 0;
$ok = function ($c, $m) use (&$fail) { echo ($c ? 'PASS ' : 'FAIL ').$m."\n"; $fail += $c ? 0 : 1; };
$all = CF::forObject(10, 'informationobject');
$ok(['donor_ref', 'weight', 'digitised', 'format', 'keyword', 'internal_note'] === array_keys($all), 'fields in definition order, inactive field left out');
$ok(['D-77'] === $all['donor_ref']['values'] && 'Donor_ref' === $all['donor_ref']['label'], 'text value with its label');
$ok(['2.5'] === $all['weight']['values'], 'number without trailing zeros');
$ok(['No'] === $all['digitised']['values'], 'boolean as Yes/No');
$ok(['Photograph'] === $all['format']['values'], 'dropdown code becomes its label');
$ok(['Nile', 'boats'] === $all['keyword']['values'], 'repeatable field keeps its order');
$ok(!isset(CF::forObject(10, 'informationobject', true)['internal_note']), 'public-only leaves out non-public fields');
$many = CF::forObjects([10, 11, 12], 'informationobject');
$ok(isset($many[10], $many[11]) && !isset($many[12]) && ['D-78'] === $many[11]['donor_ref']['values'], 'many records at once; a record with no values is absent');
$ok([] === CF::forObject(10, 'actor'), 'another entity type gives nothing');
$csv = sys_get_temp_dir().'/cfv-check-'.getmypid().'.csv';
file_put_contents($csv, "legacyId,title\n10,\"Box, one\"\n12,Two\n");
$added = CF::appendToCsv($csv, 'informationobject', true);
$rows = array_map('str_getcsv', file($csv, FILE_IGNORE_NEW_LINES));
unlink($csv);
$ok(5 === $added && 'customField_donor_ref' === $rows[0][2] && !in_array('customField_internal_note', $rows[0], true), 'CSV gets one column per public field');
$ok('Box, one' === $rows[1][1] && 'D-77' === $rows[1][2] && 'Nile|boats' === $rows[1][6], 'CSV values filled, quoting kept, repeatable joined with |');
$ok('' === $rows[2][2], 'CSV row with no values gets empty cells');

echo $fail ? "\n{$fail} failed\n" : "\nall passed\n";
exit($fail ? 1 : 0);
