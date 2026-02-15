<?php

/**
 * Qubit Compatibility Autoloader.
 *
 * Include this file to enable backward compatibility for Qubit classes.
 * All Qubit* classes will be available as thin wrappers around framework services.
 *
 * USAGE:
 *   require_once sfConfig::get('sf_root_dir') . '/atom-framework/src/Compatibility/autoload.php';
 *
 * IMPORTANT: This is for gradual migration only.
 * New code should use AtomExtensions\Services classes directly.
 */

$compatDir = __DIR__;

// Load the trait first — stubs depend on it
require_once $compatDir . '/QubitModelTrait.php';

// Load all compatibility classes (order matters — dependencies first)
$files = [
    'Qubit.php',
    'QubitSetting.php',
    'QubitCache.php',
    'QubitObject.php',
    'QubitTerm.php',
    'QubitTaxonomy.php',
    'QubitAcl.php',
    'QubitAclGroup.php',
    'QubitUser.php',
    'QubitSlug.php',
    'QubitHtmlPurifier.php',
    'QubitOai.php',
    'QubitApiExceptions.php',
    'QubitInformationObject.php',
    'QubitActor.php',
    'QubitRepository.php',
    'QubitDigitalObject.php',
    'QubitRelation.php',
    'QubitPhysicalObject.php',
    'QubitObjectTermRelation.php',
    'QubitAccession.php',
    'QubitEvent.php',
    'QubitOtherName.php',
    'QubitMenu.php',
    'QubitDonor.php',
    'QubitContactInformation.php',
    'QubitStaticPage.php',
    'QubitRightsHolder.php',
];

foreach ($files as $file) {
    $path = $compatDir . '/' . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}
