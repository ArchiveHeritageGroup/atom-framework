<?php
/**
 * Check for SearchConnection (catalog #316): with no settings nothing changes,
 * and each setting does what it says.
 *
 *   php tests/search-connection-check.php
 */
require __DIR__.'/../vendor/autoload.php';

class sfConfig
{
    public static $v = [];
    public static function get($k, $d = null) { return array_key_exists($k, self::$v) ? self::$v[$k] : $d; }
}

use AtomFramework\Services\Search\SearchConnection as C;

$fail = 0;
$check = function ($ok, $what) use (&$fail) { echo ($ok ? 'PASS  ' : 'FAIL  ').$what."\n"; $fail += $ok ? 0 : 1; };
putenv('ES_HOST');
putenv('ES_PORT');

sfConfig::$v = [];
$check('http://localhost:9200' === C::baseUrl() && [] === C::curlOptions(), 'no settings: http://localhost:9200 and no extra curl options (unchanged)');

sfConfig::$v = ['app_opensearch_host' => 'os.local', 'app_opensearch_port' => 9201, 'app_elasticsearch_host' => 'es.local'];
$check('http://os.local:9201' === C::baseUrl(), 'opensearch_host wins over elasticsearch_host');

sfConfig::$v = ['app_elasticsearch_host' => 'es.local', 'app_elasticsearch_port' => '9300'];
$check('http://es.local:9300' === C::baseUrl(), 'elasticsearch_host and port used when opensearch_* are not set');

sfConfig::$v = ['app_search_host_url' => 'https://search.example.org:9443/'];
$check('https://search.example.org:9443' === C::baseUrl(), 'search_host_url used when no host is set, trailing slash dropped');

sfConfig::$v = ['app_opensearch_host' => 'es8', 'app_search_scheme' => 'HTTPS', 'app_search_username' => 'elastic', 'app_search_password' => 'pw', 'app_search_ca_file' => '/etc/es/ca.crt'];
$o = C::curlOptions();
$check('https://es8:9200' === C::baseUrl(), 'search_scheme https');
$check(($o[CURLOPT_USERPWD] ?? '') === 'elastic:pw' && ($o[CURLOPT_HTTPAUTH] ?? 0) === CURLAUTH_BASIC, 'username and password become basic authentication');
$check(($o[CURLOPT_CAINFO] ?? '') === '/etc/es/ca.crt' && !isset($o[CURLOPT_SSL_VERIFYPEER]), 'CA file set, certificate checking left on');

sfConfig::$v = ['app_search_verify_tls' => 'false'];
$o = C::curlOptions();
$check(false === ($o[CURLOPT_SSL_VERIFYPEER] ?? null) && 0 === ($o[CURLOPT_SSL_VERIFYHOST] ?? null), 'search_verify_tls false turns certificate checking off');

sfConfig::$v = [];
$check('http://localhost:9200/idx/_search' === C::url('idx/_search') && 'http://localhost:9200/idx/_search' === C::url('/idx/_search'), 'url() joins paths with or without a leading slash');

echo $fail ? "$fail FAILED\n" : "All checks passed\n";
exit($fail ? 1 : 0);
