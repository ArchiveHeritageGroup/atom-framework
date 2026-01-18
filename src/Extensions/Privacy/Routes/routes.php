<?php

declare(strict_types=1);

/**
 * Privacy Extension Routes
 *
 * POPIA/PAIA/GDPR compliance, ROPA management, DSAR tracking, breach incidents.
 * Mimics AtoM 2.10 routing patterns.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */

use AtomFramework\Extensions\Privacy\Controllers\PrivacyComplianceController;

return function ($app) {
    // Privacy Dashboard
    $app->get('/admin/privacy', [PrivacyComplianceController::class, 'index'])
        ->setName('privacy.index');

    // Export Compliance Report
    $app->get('/admin/privacy/export', [PrivacyComplianceController::class, 'exportReport'])
        ->setName('privacy.export');

    // ROPA (Record of Processing Activities)
    $app->get('/admin/privacy/ropa', [PrivacyComplianceController::class, 'ropaList'])
        ->setName('privacy.ropa.index');

    $app->get('/admin/privacy/ropa/new', [PrivacyComplianceController::class, 'createRopa'])
        ->setName('privacy.ropa.create');

    $app->post('/admin/privacy/ropa', [PrivacyComplianceController::class, 'storeRopa'])
        ->setName('privacy.ropa.store');

    $app->get('/admin/privacy/ropa/{id}', [PrivacyComplianceController::class, 'viewRopa'])
        ->setName('privacy.ropa.view');

    $app->get('/admin/privacy/ropa/{id}/edit', [PrivacyComplianceController::class, 'editRopa'])
        ->setName('privacy.ropa.edit');

    $app->put('/admin/privacy/ropa/{id}', [PrivacyComplianceController::class, 'updateRopa'])
        ->setName('privacy.ropa.update');

    // DSAR (Data Subject Access Requests)
    $app->get('/admin/privacy/dsar', [PrivacyComplianceController::class, 'dsarList'])
        ->setName('privacy.dsar.index');

    $app->get('/admin/privacy/dsar/new', [PrivacyComplianceController::class, 'createDsar'])
        ->setName('privacy.dsar.create');

    $app->post('/admin/privacy/dsar', [PrivacyComplianceController::class, 'storeDsar'])
        ->setName('privacy.dsar.store');

    $app->get('/admin/privacy/dsar/{id}', [PrivacyComplianceController::class, 'viewDsar'])
        ->setName('privacy.dsar.view');

    $app->post('/admin/privacy/dsar/{id}/status', [PrivacyComplianceController::class, 'updateDsarStatus'])
        ->setName('privacy.dsar.status');

    $app->post('/admin/privacy/dsar/{id}/log', [PrivacyComplianceController::class, 'logDsarActivity'])
        ->setName('privacy.dsar.log');

    // Breach Incidents
    $app->get('/admin/privacy/breaches', [PrivacyComplianceController::class, 'breachesList'])
        ->setName('privacy.breaches.index');

    $app->get('/admin/privacy/breaches/new', [PrivacyComplianceController::class, 'createBreach'])
        ->setName('privacy.breaches.create');

    $app->post('/admin/privacy/breaches', [PrivacyComplianceController::class, 'storeBreach'])
        ->setName('privacy.breaches.store');

    $app->get('/admin/privacy/breaches/{id}', [PrivacyComplianceController::class, 'viewBreach'])
        ->setName('privacy.breaches.view');

    $app->put('/admin/privacy/breaches/{id}', [PrivacyComplianceController::class, 'updateBreach'])
        ->setName('privacy.breaches.update');

    // Privacy Templates
    $app->get('/admin/privacy/templates', [PrivacyComplianceController::class, 'templatesList'])
        ->setName('privacy.templates.index');

    $app->get('/admin/privacy/templates/new', [PrivacyComplianceController::class, 'createTemplate'])
        ->setName('privacy.templates.create');

    $app->post('/admin/privacy/templates', [PrivacyComplianceController::class, 'storeTemplate'])
        ->setName('privacy.templates.store');

    $app->post('/admin/privacy/templates/initialize', [PrivacyComplianceController::class, 'initializeTemplates'])
        ->setName('privacy.templates.initialize');
};
