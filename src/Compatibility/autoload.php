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

// ── Phase 6A: Action/Component Stack ─────────────────────────────────
// Load the Symfony 1.x action/component class hierarchy FIRST.
// These must be loaded before any plugin action/component classes.
$actionAutoload = $compatDir . '/Action/action_autoload.php';
if (file_exists($actionAutoload)) {
    require_once $actionAutoload;
}

// ── Phase 6C: sf* Utility Stubs ──────────────────────────────────────
$utilDir = $compatDir . '/Util';
$utilFiles = [
    'sfInflector.php',
    'sfToolkit.php',
    'sfCultureInfo.php',
    'sfDateFormat.php',
    'sfFilter.php',
    'sfFilterChain.php',
    'sfRoute.php',
];
foreach ($utilFiles as $utilFile) {
    $utilPath = $utilDir . '/' . $utilFile;
    if (file_exists($utilPath)) {
        require_once $utilPath;
    }
}

// Load the trait first — stubs depend on it
require_once $compatDir . '/QubitModelTrait.php';

// Alias namespaced trait to global scope for stubs that use `use QubitModelTrait`
if (!trait_exists('QubitModelTrait', false) && trait_exists(\AtomFramework\Compatibility\QubitModelTrait::class, false)) {
    class_alias(\AtomFramework\Compatibility\QubitModelTrait::class, 'QubitModelTrait');
}

// Load Propel shim FIRST — QubitPdo and model stubs depend on it
if (!class_exists('Propel', false)) {
    require_once $compatDir . '/Propel.php';
}

// Load QubitPdo — lightweight SQL wrapper used by CLI commands and plugins
if (!class_exists('QubitPdo', false)) {
    require_once $compatDir . '/QubitPdo.php';
}

// Load all compatibility classes (order matters — dependencies first)
// Load Propel compatibility stubs (Criteria, BasePeer, QubitPager)
require_once $compatDir . '/Criteria.php';
require_once $compatDir . '/PropelColumnConstants.php';
require_once $compatDir . '/BasePeer.php';
require_once $compatDir . '/QubitPager.php';

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
    // Phase 2 stubs — column constants + lightweight methods for standalone mode
    'QubitAuditObject.php',
    'QubitAuditLog.php',
    'QubitStatus.php',
    'QubitAclPermission.php',
    'QubitGrantedRight.php',
    'QubitJob.php',
    'QubitFindingAid.php',
    'QubitFindingAidGenerator.php',
    'QubitFeedback.php',
    'QubitRequestToPublish.php',
    'QubitRequestToPublishI18n.php',
    'QubitBookoutObject.php',
    'QubitSearch.php',
    'QubitCultureFallback.php',
    'QubitDescription.php',
    // Phase 6B stubs — additional entity models for standalone mode
    'QubitNote.php',
    'QubitProperty.php',
    'QubitRights.php',
    'QubitFunctionObject.php',
    'QubitDeaccession.php',
    'QubitClipboardSave.php',
    'QubitClipboardSaveItem.php',
    'QubitSearchPager.php',
    'QubitFlatfileExport.php',
    'QubitLftSyncer.php',
];

foreach ($files as $file) {
    $path = $compatDir . '/' . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}

// Load task system stubs (sfBaseTask, arBaseTask, sfCommandOption, etc.)
$taskAutoload = $compatDir . '/Task/task_autoload.php';
if (file_exists($taskAutoload)) {
    require_once $taskAutoload;
}
