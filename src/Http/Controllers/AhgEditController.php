<?php

namespace AtomFramework\Http\Controllers;

/**
 * AHG equivalent of DefaultEditAction.
 *
 * Reimplements the same API so that plugin action classes can
 * extend this instead of the base-AtoM DefaultEditAction,
 * inheriting AhgController (boot(), culture(), renderBlade(), etc.).
 *
 * @see apps/qubit/modules/default/actions/editAction.class.php
 */
class AhgEditController extends AhgController
{
    public function execute($request)
    {
        // Force subclassing
        if ('default' == $this->context->getModuleName() && 'edit' == $this->context->getActionName()) {
            $this->forward404();
        }

        $this->form = new \sfForm();

        // Call early execute logic, if defined by a child class
        if (method_exists($this, 'earlyExecute')) {
            call_user_func([$this, 'earlyExecute']);
        }

        // Mainly used in autocomplete.js, this tells us that the user wants to
        // reuse existing objects instead of adding new ones.
        if (isset($this->request->linkExisting)) {
            $this->form->setDefault('linkExisting', $this->request->linkExisting);
            $this->form->setValidator('linkExisting', new \sfValidatorBoolean());
            $this->form->setWidget('linkExisting', new \sfWidgetFormInputHidden());
        }

        foreach ($this::$NAMES as $name) {
            $this->addField($name);
        }
    }

    protected function addField($name)
    {
        switch ($name) {
            case 'descriptionDetail':
                $this->form->setDefault('descriptionDetail', $this->context->routing->generate(null, [$this->resource->descriptionDetail, 'module' => 'term']));
                $this->form->setValidator('descriptionDetail', new \sfValidatorString());

                $choices = [];
                $choices[null] = null;
                foreach (\QubitTaxonomy::getTermsById(\QubitTaxonomy::DESCRIPTION_DETAIL_LEVEL_ID) as $item) {
                    $choices[$this->context->routing->generate(null, [$item, 'module' => 'term'])] = $item;
                }

                $this->form->setWidget('descriptionDetail', new \sfWidgetFormSelect(['choices' => $choices]));

                break;

            case 'descriptionStatus':
                $this->form->setDefault('descriptionStatus', $this->context->routing->generate(null, [$this->resource->descriptionStatus, 'module' => 'term']));
                $this->form->setValidator('descriptionStatus', new \sfValidatorString());

                $choices = [];
                $choices[null] = null;
                foreach (\QubitTaxonomy::getTermsById(\QubitTaxonomy::DESCRIPTION_STATUS_ID) as $item) {
                    $choices[$this->context->routing->generate(null, [$item, 'module' => 'term'])] = $item;
                }

                $this->form->setWidget('descriptionStatus', new \sfWidgetFormSelect(['choices' => $choices]));

                break;

            case 'language':
            case 'languageOfDescription':
                $this->form->setDefault($name, $this->resource[$name]);
                $this->form->setValidator($name, new \sfValidatorI18nChoiceLanguage(['multiple' => true]));
                $this->form->setWidget($name, new \sfWidgetFormI18nChoiceLanguage(['culture' => $this->context->user->getCulture(), 'multiple' => true]));

                break;

            case 'otherName':
            case 'parallelName':
            case 'standardizedName':
                $criteria = new \Criteria();
                $criteria = $this->resource->addOtherNamesCriteria($criteria);

                switch ($name) {
                    case 'otherName':
                        $criteria->add(\QubitOtherName::TYPE_ID, \QubitTerm::OTHER_FORM_OF_NAME_ID);

                        break;

                    case 'parallelName':
                        $criteria->add(\QubitOtherName::TYPE_ID, \QubitTerm::PARALLEL_FORM_OF_NAME_ID);

                        break;

                    case 'standardizedName':
                        $criteria->add(\QubitOtherName::TYPE_ID, \QubitTerm::STANDARDIZED_FORM_OF_NAME_ID);

                        break;
                }

                $value = $defaults = [];
                foreach ($this[$name] = \QubitOtherName::get($criteria) as $item) {
                    $defaults[$value[] = $item->id] = $item;
                }

                $this->form->setDefault($name, $value);
                $this->form->setValidator($name, new \sfValidatorPass());
                $this->form->setWidget($name, new \QubitWidgetFormInputMany(['defaults' => $defaults]));

                break;

            case 'script':
            case 'scriptOfDescription':
                $this->form->setDefault($name, $this->resource[$name]);

                $c = \sfCultureInfo::getInstance($this->context->user->getCulture());

                $this->form->setValidator($name, new \sfValidatorChoice(['choices' => array_keys($c->getScripts()), 'multiple' => true]));
                $this->form->setWidget($name, new \sfWidgetFormSelect(['choices' => $c->getScripts(), 'multiple' => true]));

                break;
        }
    }

    protected function processField($field)
    {
        switch ($field->getName()) {
            case 'descriptionDetail':
            case 'descriptionStatus':
                unset($this->resource[$field->getName()]);

                $value = $this->form->getValue($field->getName());
                if (isset($value)) {
                    $params = $this->context->routing->parse(\Qubit::pathInfo($value));
                    $this->resource[$field->getName()] = $params['_sf_route']->resource;
                }

                break;

            case 'otherName':
            case 'parallelName':
            case 'standardizedName':
                $value = $filtered = $this->form->getValue($field->getName());

                foreach ($this[$field->getName()] as $item) {
                    if (!empty($value[$item->id])) {
                        $item->name = $value[$item->id];
                        unset($filtered[$item->id]);
                    } else {
                        $item->delete();
                    }
                }

                foreach ($filtered as $item) {
                    if (!$item) {
                        continue;
                    }

                    $otherName = new \QubitOtherName();
                    $otherName->name = $item;

                    switch ($field->getName()) {
                        case 'parallelName':
                            $otherName->typeId = \QubitTerm::PARALLEL_FORM_OF_NAME_ID;

                            break;

                        case 'standardizedName':
                            $otherName->typeId = \QubitTerm::STANDARDIZED_FORM_OF_NAME_ID;

                            break;

                        default:
                            $otherName->typeId = \QubitTerm::OTHER_FORM_OF_NAME_ID;
                    }

                    $this->resource->otherNames[] = $otherName;
                }

                break;

            default:
                $this->resource[$field->getName()] = $this->form->getValue($field->getName());
        }
    }

    protected function processForm()
    {
        foreach ($this->form as $field) {
            $this->processField($field);
        }
    }
}
