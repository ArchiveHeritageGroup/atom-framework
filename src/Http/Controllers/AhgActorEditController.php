<?php

namespace AtomFramework\Http\Controllers;

/**
 * AHG equivalent of ActorEditAction.
 *
 * Reimplements the same API so that plugin action classes can
 * extend this instead of the base-AtoM ActorEditAction,
 * inheriting AhgController (boot(), culture(), renderBlade(), etc.).
 *
 * @see apps/qubit/modules/actor/actions/editAction.class.php
 */
class AhgActorEditController extends AhgEditController
{
    public function execute($request)
    {
        parent::execute($request);

        if ($request->isMethod('post')) {
            $this->form->bind($request->getPostParameters());
            if ($this->form->isValid()) {
                $this->processForm();

                $this->resource->save();

                if (isset($request->id) && (0 < strlen($next = $this->form->getValue('next')))) {
                    $this->redirect($next);
                }

                $this->redirect([$this->resource, 'module' => 'actor']);
            }
        }

        \QubitDescription::addAssets($this->response);
    }

    protected function earlyExecute()
    {
        $this->form->getValidatorSchema()->setOption('allow_extra_fields', true);

        $this->resource = new \QubitActor();

        // Make root actor the parent of new actors
        $this->resource->parentId = \QubitActor::ROOT_ID;

        if (isset($this->getRoute()->resource)) {
            $this->resource = $this->getRoute()->resource;

            // Check that this isn't the root
            if (!isset($this->resource->parent)) {
                $this->forward404();
            }

            // Check user authorization
            if (!\QubitAcl::check($this->resource, 'update') && !\QubitAcl::check($this->resource, 'translate')) {
                \QubitAcl::forwardUnauthorized();
            }

            // Add optimistic lock
            $this->form->setDefault('serialNumber', $this->resource->serialNumber);
            $this->form->setValidator('serialNumber', new \sfValidatorInteger());
            $this->form->setWidget('serialNumber', new \sfWidgetFormInputHidden());
        } else {
            // Check user authorization against Actor ROOT
            if (!\QubitAcl::check(\QubitActor::getById(\QubitActor::ROOT_ID), 'create')) {
                \QubitAcl::forwardUnauthorized();
            }
        }

        $this->form->setDefault('next', $this->request->getReferer());
        $this->form->setValidator('next', new \sfValidatorString());
        $this->form->setWidget('next', new \sfWidgetFormInputHidden());
    }

    protected function addField($name)
    {
        switch ($name) {
            case 'entityType':
                $this->form->setDefault('entityType', $this->context->routing->generate(null, [$this->resource->entityType, 'module' => 'term']));
                $this->form->setValidator('entityType', new \sfValidatorString());

                $choices = [];
                $choices[null] = null;
                foreach (\QubitTaxonomy::getTaxonomyTerms(\QubitTaxonomy::ACTOR_ENTITY_TYPE_ID) as $item) {
                    $choices[$this->context->routing->generate(null, [$item, 'module' => 'term'])] = $item;
                }

                $this->form->setWidget('entityType', new \sfWidgetFormSelect(['choices' => $choices]));

                break;

            case 'authorizedFormOfName':
            case 'corporateBodyIdentifiers':
            case 'datesOfExistence':
            case 'descriptionIdentifier':
            case 'institutionResponsibleIdentifier':
                $this->form->setDefault($name, $this->resource[$name]);
                $this->form->setValidator($name, new \sfValidatorString());
                $this->form->setWidget($name, new \sfWidgetFormInput());

                break;

            case 'functions':
            case 'generalContext':
            case 'history':
            case 'internalStructures':
            case 'legalStatus':
            case 'mandates':
            case 'places':
            case 'revisionHistory':
            case 'rules':
            case 'sources':
                $this->form->setDefault($name, $this->resource[$name]);
                $this->form->setValidator($name, new \sfValidatorString());
                $this->form->setWidget($name, new \sfWidgetFormTextarea());

                break;

            case 'maintainingRepository':
                $choices = [];
                if (null !== $repo = $this->resource->getMaintainingRepository()) {
                    $repoRoute = $this->context->routing->generate(null, [$repo, 'module' => 'repository']);
                    $choices[$repoRoute] = $repo;
                    $this->form->setDefault('maintainingRepository', $repoRoute);
                }

                $this->form->setValidator('maintainingRepository', new \sfValidatorString());
                $this->form->setWidget('maintainingRepository', new \sfWidgetFormSelect(['choices' => $choices]));

                break;

            case 'subjectAccessPoints':
            case 'placeAccessPoints':
                $criteria = new \Criteria();
                $criteria->add(\QubitObjectTermRelation::OBJECT_ID, $this->resource->id);
                $criteria->addJoin(\QubitObjectTermRelation::TERM_ID, \QubitTerm::ID);

                switch ($name) {
                    case 'subjectAccessPoints':
                        $criteria->add(\QubitTerm::TAXONOMY_ID, \QubitTaxonomy::SUBJECT_ID);

                        break;

                    case 'placeAccessPoints':
                        $criteria->add(\QubitTerm::TAXONOMY_ID, \QubitTaxonomy::PLACE_ID);

                        break;
                }

                $value = $choices = [];
                foreach ($this[$name] = \QubitObjectTermRelation::get($criteria) as $item) {
                    $choices[$value[] = $this->context->routing->generate(null, [$item->term, 'module' => 'term'])] = $item->term;
                }

                $this->form->setDefault($name, $value);
                $this->form->setValidator($name, new \sfValidatorPass());
                $this->form->setWidget($name, new \sfWidgetFormSelect(['choices' => $choices, 'multiple' => true]));

                break;

            default:
                return parent::addField($name);
        }
    }

    /**
     * Process form fields.
     *
     * @param $field mixed symfony form widget
     */
    protected function processField($field)
    {
        switch ($field->getName()) {
            case 'authorizedFormOfName':
                // Avoid duplicates (used in autocomplete.js)
                if (filter_var($this->request->getPostParameter('linkExisting'), FILTER_VALIDATE_BOOLEAN)) {
                    $criteria = new \Criteria();
                    $criteria->addJoin(\QubitObject::ID, \QubitActorI18n::ID);
                    $criteria->add(\QubitObject::CLASS_NAME, get_class($this->request));
                    $criteria->add(\QubitActorI18n::CULTURE, $this->context->user->getCulture());
                    $criteria->add(\QubitActorI18n::AUTHORIZED_FORM_OF_NAME, $this->form->getValue('authorizedFormOfName'));
                    if (null !== $actor = \QubitActor::getOne($criteria)) {
                        $this->redirect([$actor]);

                        return;
                    }
                }

                return parent::processField($field);

            case 'entityType':
                unset($this->resource->entityType);

                $value = $this->form->getValue('entityType');
                if (isset($value)) {
                    $params = $this->context->routing->parse(\Qubit::pathInfo($value));
                    $this->resource->entityType = $params['_sf_route']->resource;
                }

                break;

            case 'maintainingRepository':
                $value = $this->form->getValue('maintainingRepository');
                if (isset($value)) {
                    $params = $this->context->routing->parse(\Qubit::pathInfo($value));
                    $this->resource->setOrDeleteMaintainingRepository($params['_sf_route']->resource);
                } else {
                    $this->resource->setOrDeleteMaintainingRepository();
                }

                break;

            case 'subjectAccessPoints':
            case 'placeAccessPoints':
                $value = $filtered = [];
                foreach ($this->form->getValue($field->getName()) as $item) {
                    $params = $this->context->routing->parse(\Qubit::pathInfo($item));
                    $resource = $params['_sf_route']->resource;
                    $value[$resource->id] = $filtered[$resource->id] = $resource;
                }

                foreach ($this[$field->getName()] as $item) {
                    if (isset($value[$item->term->id])) {
                        unset($filtered[$item->term->id]);
                    } else {
                        $item->indexObjectOnDelete = false;
                        $item->delete();
                    }
                }

                foreach ($filtered as $item) {
                    $relation = new \QubitObjectTermRelation();
                    $relation->term = $item;

                    $this->resource->objectTermRelationsRelatedByobjectId[] = $relation;
                }

                break;

            default:
                return parent::processField($field);
        }
    }
}
