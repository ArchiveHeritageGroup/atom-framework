<?php
declare(strict_types=1);
namespace AtomExtensions\Constants;

/**
 * ACL Constants - Replaces QubitAcl and QubitAclGroup constants
 */
final class AclConstants
{
    // Access levels (must match QubitAcl / acl_permission.grant_deny values)
    public const GRANT = 2;
    public const DENY = 0;
    public const INHERIT = 1;

    // Special object IDs
    public const ROOT_ID = 1;
    public const ANONYMOUS_ID = 98;

    // Group IDs
    public const AUTHENTICATED_ID = 99;
    public const ADMINISTRATOR_ID = 100;
    public const EDITOR_ID = 101;
    public const CONTRIBUTOR_ID = 102;
    public const TRANSLATOR_ID = 103;
    
    // Action IDs
    public const ACTION_CREATE = 1;
    public const ACTION_READ = 2;
    public const ACTION_UPDATE = 3;
    public const ACTION_DELETE = 4;
    public const ACTION_TRANSLATE = 5;
    public const ACTION_PUBLISH = 6;
    public const ACTION_VIEW_DRAFT = 7;
    public const ACTION_READ_MASTER = 8;
    public const ACTION_READ_REFERENCE = 9;
    public const ACTION_READ_THUMBNAIL = 10;
}
