<?php

/**
 * QubitAclGroup Compatibility Layer.
 *
 * @deprecated Use AtomExtensions\Services\AclGroupService directly
 */

use AtomExtensions\Services\AclGroupService;

class QubitAclGroup
{
    public const ADMINISTRATOR_ID = AclGroupService::ADMINISTRATOR_ID;
    public const EDITOR_ID = AclGroupService::EDITOR_ID;
    public const CONTRIBUTOR_ID = AclGroupService::CONTRIBUTOR_ID;
    public const TRANSLATOR_ID = AclGroupService::TRANSLATOR_ID;

    public static function getById(int $id): ?object
    {
        return AclGroupService::getById($id);
    }
}
