<?php
/**
 * AtoM Framework Bootstrap
 * 
 * To customize: copy bootstrap.php.dist to bootstrap.local.php and modify
 * This file will load bootstrap.local.php if it exists, otherwise bootstrap.php.dist
 */

$localBootstrap = __DIR__ . '/bootstrap.local.php';
$distBootstrap = __DIR__ . '/bootstrap.php.dist';

if (file_exists($localBootstrap)) {
    require_once $localBootstrap;
} elseif (file_exists($distBootstrap)) {
    require_once $distBootstrap;
}
