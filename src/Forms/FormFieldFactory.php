<?php

declare(strict_types=1);

namespace AtomExtensions\Forms;

use sfForm;
use sfValidatorChoice;
use sfValidatorString;
use sfWidgetFormInput;
use sfWidgetFormSelect;

/**
 * Form Field Factory.
 *
 * Centralized form field creation for reports.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class FormFieldFactory
{
    /**
     * Add date range fields (with date picker).
     */
    public static function addDateFields(sfForm $form): void
    {
        // Date start with date picker
        $form->setValidator('dateStart', new sfValidatorString(['required' => false]));
        $form->setWidget('dateStart', new sfWidgetFormInput([
            'type' => 'date'
        ]));

        // Date end with date picker
        $form->setValidator('dateEnd', new sfValidatorString(['required' => false]));
        $form->setWidget('dateEnd', new sfWidgetFormInput([
            'type' => 'date'
        ]));

        // Date of selector (radio buttons)
        $choices = [
            'CREATED_AT' => 'Created',
            'UPDATED_AT' => 'Updated',
            'both' => 'Both',
        ];

        $form->setValidator('dateOf', new sfValidatorChoice([
            'choices' => array_keys($choices),
            'required' => false,
        ]));

        $form->setWidget('dateOf', new sfWidgetFormSelect([
            'choices' => $choices,
        ]));
    }

    /**
     * Add control fields (limit, sort, page).
     */
    public static function addControlFields(sfForm $form): void
    {
        // Limit (results per page)
        $limitChoices = [
            '10' => '10',
            '20' => '20',
            '50' => '50',
            '100' => '100',
        ];

        $form->setValidator('limit', new sfValidatorChoice([
            'choices' => array_keys($limitChoices),
            'required' => false,
        ]));

        $form->setWidget('limit', new sfWidgetFormSelect([
            'choices' => $limitChoices,
        ]));

        // Sort (hidden for now)
        $form->setValidator('sort', new sfValidatorString(['required' => false]));
        $form->setWidget('sort', new sfWidgetFormInput(['type' => 'hidden']));

        // Page (hidden)
        $form->setValidator('page', new sfValidatorString(['required' => false]));
        $form->setWidget('page', new sfWidgetFormInput(['type' => 'hidden']));
    }
}
