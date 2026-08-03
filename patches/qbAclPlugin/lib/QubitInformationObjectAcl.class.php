<?php

/*
 * This file is part of the Access to Memory (AtoM) software.
 *
 * Access to Memory (AtoM) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Access to Memory (AtoM) is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Access to Memory (AtoM).  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Custom ACL rules for QubitInformationObject resources.
 *
 * @author     David Juhasz <david@artefactual.com>
 */
class QubitInformationObjectAcl extends QubitAcl
{
    // Add viewDraft and publish actions to list
    public static $ACTIONS = [
        'read' => 'Read',
        'create' => 'Create',
        'update' => 'Update',
        'delete' => 'Delete',
        'translate' => 'Translate',
        'viewDraft' => 'View draft',
        'publish' => 'Publish',
        'readMaster' => 'Access master',
        'readReference' => 'Access reference',
        'readThumbnail' => 'Access thumbnail',
    ];

    // For information objects check parent authorization for create OR publish
    // actions
    protected static $_parentAuthActions = ['create', 'publish'];
    protected static $_digitalObjectActions = ['readMaster', 'readReference', 'readThumbnail'];

    /**
     * Do custom ACL checks for QubitInformationObject resources.
     *
     * @param myUser                 $user     to authorize
     * @param QubitInformationObject $resource target of the requested action
     * @param string                 $action   requested for authorization (e.g. 'read')
     * @param null|array             $options  optional parameters
     *
     * @return bool true if the access request is authorized
     */
    public static function isAllowed($user, $resource, $action, $options = [])
    {
        if ('read' == $action) {
            return self::isReadAllowed($user, $resource, $action, $options);
        }

        // Do custom ACL checks for digital object actions
        if (in_array($action, self::$_digitalObjectActions)) {
            return self::isDigitalObjectActionAllowed(
                $user,
                $resource,
                $action,
                $options
            );
        }

        // Call QubitAcl::isAllowed(), when no special rules apply
        return parent::isAllowed($user, $resource, $action, $options);
    }

    /**
     * Custom QubitInformationObject "read" authorization rules.
     *
     * @param myUser     $user     to authorize
     * @param mixed      $resource target of the requested action
     * @param string     $action   requested for authorization (e.g. 'read')
     * @param null|array $options  optional parameters
     *
     * @return bool true if the access request is authorized
     */
    private static function isReadAllowed($user, $resource, $action, $options = [])
    {
        if (null === $resource->getPublicationStatus()) {
            throw new sfException(
                'No publication status set for information object id: '.$resource->id
            );
        }

        // If this is a draft information object, check "read" and "viewDraft"
        // authorization
        if (
            QubitTerm::PUBLICATION_STATUS_DRAFT_ID
            == $resource->getPublicationStatus()->statusId
        ) {
            return parent::isAllowed($user, $resource, 'read')
                && parent::isAllowed($user, $resource, 'viewDraft');
        }

        // Otherwise, just do a "read" ACL check
        return parent::isAllowed($user, $resource, $action, $options);
    }

    /**
     * Do custom ACL checks for digital object actions.
     *
     * @param myUser     $user     to authorize
     * @param mixed      $resource target of the requested action
     * @param string     $action   requested for authorization (e.g. 'read')
     * @param null|array $options  optional parameters
     */
    private static function isDigitalObjectActionAllowed(
        $user,
        $resource,
        $action,
        $options = []
    ) {
        // AHG (#258): upstream granted EVERY user readMaster on text media
        // objects, returning before both the standard ACL check and the PREMIS
        // granted-rights check below. That made PDF masters of draft, embargoed
        // and access-restricted descriptions downloadable by anonymous users,
        // and is why the PREMIS Rights module cannot restrict a PDF - see
        // upstream artefactual/atom#1724.
        //
        // Note this covers QubitTerm::TEXT_ID, not just PDF.
        //
        // Off by default, so text masters follow the same ACL as every other
        // media type. Set allow_public_text_masters in ahg_settings to restore
        // upstream behaviour where an instance genuinely wants PDFs public.
        if ('readMaster' == $action
            && $resource->hasTextDigitalObject()
            && self::publicTextMastersAllowed()) {
            return true;
        }

        // Do the standard QubitAcl authorization check AND a QubitGrantedRight
        // check
        return parent::isAllowed($user, $resource, $action, $options)
            && QubitGrantedRight::checkPremis($resource->id, $action);
    }

    /**
     * Whether anonymous access to text/PDF masters is permitted.
     *
     * Fails closed: any error resolving the setting denies the exception, so a
     * misconfiguration cannot silently reopen the bypass.
     */
    private static function publicTextMastersAllowed()
    {
        try {
            return \AtomExtensions\Services\AhgSettingsService::getBool('allow_public_text_masters', false);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
