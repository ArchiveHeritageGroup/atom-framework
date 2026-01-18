<?php

declare(strict_types=1);

/**
 * Security Extension Routes
 *
 * NARSSA/POPIA compliance, retention schedules, and audit exports.
 * Mimics AtoM 2.10 routing patterns.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */

use AtomFramework\Extensions\Security\Controllers\SecurityComplianceController;

return function ($app) {
    // Compliance Dashboard
    $app->get('/admin/security/compliance', [SecurityComplianceController::class, 'index'])
        ->setName('security.compliance.index');

    // Compliance Report
    $app->get('/admin/security/compliance/report', [SecurityComplianceController::class, 'report'])
        ->setName('security.compliance.report');

    // Export Compliance Report
    $app->get('/admin/security/compliance/export', [SecurityComplianceController::class, 'exportReport'])
        ->setName('security.compliance.export');

    // Retention Schedules
    $app->get('/admin/security/compliance/retention', [SecurityComplianceController::class, 'retentionSchedules'])
        ->setName('security.compliance.retention');

    $app->get('/admin/security/compliance/retention/edit[/{id}]', [SecurityComplianceController::class, 'editRetentionSchedule'])
        ->setName('security.compliance.retention.edit');

    $app->post('/admin/security/compliance/retention/save', [SecurityComplianceController::class, 'saveRetentionSchedule'])
        ->setName('security.compliance.retention.save');

    // Pending Reviews
    $app->get('/admin/security/compliance/reviews', [SecurityComplianceController::class, 'pendingReviews'])
        ->setName('security.compliance.reviews');

    // Declassification Schedule
    $app->get('/admin/security/compliance/declassification', [SecurityComplianceController::class, 'declassificationSchedule'])
        ->setName('security.compliance.declassification');

    // Suggest Declassification Date (AJAX)
    $app->get('/admin/security/compliance/suggest-declassification/{objectId}', [SecurityComplianceController::class, 'suggestDeclassification'])
        ->setName('security.compliance.suggest');

    // Access Logs
    $app->get('/admin/security/compliance/access-logs', [SecurityComplianceController::class, 'accessLogs'])
        ->setName('security.compliance.accessLogs');

    $app->get('/admin/security/compliance/access-logs/export', [SecurityComplianceController::class, 'exportAccessLogs'])
        ->setName('security.compliance.accessLogs.export');

    // Clearance Logs
    $app->get('/admin/security/compliance/clearance-logs', [SecurityComplianceController::class, 'clearanceLogs'])
        ->setName('security.compliance.clearanceLogs');

    $app->get('/admin/security/compliance/clearance-logs/export', [SecurityComplianceController::class, 'exportClearanceLogs'])
        ->setName('security.compliance.clearanceLogs.export');

    // Justification Templates
    $app->get('/admin/security/compliance/justification-templates', [SecurityComplianceController::class, 'justificationTemplates'])
        ->setName('security.compliance.justificationTemplates');

    $app->get('/admin/security/compliance/justification-templates/edit[/{id}]', [SecurityComplianceController::class, 'editJustificationTemplate'])
        ->setName('security.compliance.justificationTemplates.edit');

    $app->post('/admin/security/compliance/justification-templates/save', [SecurityComplianceController::class, 'saveJustificationTemplate'])
        ->setName('security.compliance.justificationTemplates.save');

    $app->post('/admin/security/compliance/justification-templates/initialize', [SecurityComplianceController::class, 'initializeDefaultTemplates'])
        ->setName('security.compliance.justificationTemplates.initialize');

    // Object-level access conditions
    $app->post('/{slug}/security/access-conditions', [SecurityComplianceController::class, 'linkAccessConditions'])
        ->setName('security.object.accessConditions');
};
