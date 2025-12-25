<?php

declare(strict_types=1);

/**
 * Condition Extension Routes
 *
 * Condition reporting, risk assessment, conservation tracking.
 * Spectrum 5.0 compliant for museum cataloging.
 * Mimics AtoM 2.10 routing patterns.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */

use AtoM\Framework\Extensions\Condition\Controllers\ConditionController;

return function ($app) {
    // Condition Dashboard
    $app->get('/admin/condition', [ConditionController::class, 'index'])
        ->setName('condition.index');

    // Risk Assessment
    $app->get('/admin/condition/risk', [ConditionController::class, 'riskAssessment'])
        ->setName('condition.risk');

    // Vocabulary Management
    $app->get('/admin/condition/vocabularies', [ConditionController::class, 'vocabularies'])
        ->setName('condition.vocabularies');

    $app->post('/admin/condition/vocabularies/add', [ConditionController::class, 'addVocabularyTerm'])
        ->setName('condition.vocabularies.add');

    $app->post('/admin/condition/vocabularies/initialize', [ConditionController::class, 'initializeVocabularies'])
        ->setName('condition.vocabularies.initialize');

    // Assessment Schedule
    $app->get('/admin/condition/schedule', [ConditionController::class, 'scheduleList'])
        ->setName('condition.schedule');

    // Object-level Condition Routes
    $app->get('/{slug}/condition', [ConditionController::class, 'show'])
        ->setName('condition.show');

    $app->get('/{slug}/condition/new', [ConditionController::class, 'create'])
        ->setName('condition.create');

    $app->post('/{slug}/condition', [ConditionController::class, 'store'])
        ->setName('condition.store');

    $app->get('/{slug}/condition/export', [ConditionController::class, 'export'])
        ->setName('condition.export');

    $app->get('/{slug}/condition/{eventId}', [ConditionController::class, 'view'])
        ->setName('condition.view');

    $app->get('/{slug}/condition/{eventId}/edit', [ConditionController::class, 'edit'])
        ->setName('condition.edit');

    $app->put('/{slug}/condition/{eventId}', [ConditionController::class, 'update'])
        ->setName('condition.update');

    // Conservation Treatment Routes
    $app->get('/{slug}/condition/treatment/new', [ConditionController::class, 'createTreatment'])
        ->setName('condition.treatment.create');

    $app->post('/{slug}/condition/treatment', [ConditionController::class, 'storeTreatment'])
        ->setName('condition.treatment.store');
};
