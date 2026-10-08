<?php
require '/usr/share/nginx/archive/atom-framework/vendor/autoload.php';
use AtomFramework\Services\CsrfService;
$cases = [
  ['POST', '/sso/saml/acs', ['SAMLResponse' => 'x'], true],
  ['POST', '/index.php/sso/saml/acs', ['SAMLResponse' => 'x'], true],
  ['POST', '/sso/saml/acs', [], false],
  ['POST', '/sso/saml/acs/../../admin', ['SAMLResponse' => 'x'], false],
  ['POST', '/sso/saml/acsx', ['SAMLResponse' => 'x'], false],
  ['POST', '/user/login', ['SAMLResponse' => 'x'], false],
  ['GET', '/anything', [], true],
];
foreach ($cases as [$m, $uri, $post, $want]) {
  $_SERVER = ['REQUEST_METHOD' => $m, 'REQUEST_URI' => $uri]; $_POST = $post;
  $got = CsrfService::isExempt();
  echo ($got === $want ? 'PASS' : 'FAIL'), " $m $uri ", json_encode($post), ' -> ', var_export($got, true), "\n";
}
