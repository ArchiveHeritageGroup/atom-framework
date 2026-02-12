<?php
declare(strict_types=1);
namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;
use AtomExtensions\Constants\AclConstants;

/**
 * ACL Service - Replaces QubitAcl (362 uses)
 */
class AclService
{
    public const GRANT = AclConstants::GRANT;
    public const DENY = AclConstants::DENY;
    public const INHERIT = AclConstants::INHERIT;

    public static array $ACTIONS = [
        'read' => 'Read',
        'create' => 'Create',
        'update' => 'Update',
        'delete' => 'Delete',
        'translate' => 'Translate',
        'publish' => 'Publish',
        'viewDraft' => 'View Draft',
        'readMaster' => 'Read Master',
        'readReference' => 'Read Reference',
        'readThumbnail' => 'Read Thumbnail',
        'createTerm' => 'Create Term',
        'list' => 'List',
    ];

    private static ?object $user = null;
    private static ?array $userGroups = null;

    public static function setUser(?object $user): void
    {
        self::$user = $user;
        self::$userGroups = null;
    }

    public static function getUser(): ?object
    {
        return self::$user;
    }

    public static function check(?object $resource, $action, ?object $user = null): bool
    {
        $user = $user ?? self::$user;
        
        // Auto-load user from sfContext if not set
        if (!$user && class_exists('sfContext') && \sfContext::hasInstance()) {
            $sfUser = \sfContext::getInstance()->getUser();
            if ($sfUser && $sfUser->isAuthenticated() && isset($sfUser->user)) {
                $user = $sfUser->user;
                self::$user = $user;
            }
        }
        
        if (!$user) {
            return false;
        }
        
        $groups = self::getUserGroups($user->id ?? null);
        
        // Administrator has all permissions
        if (in_array(AclConstants::ADMINISTRATOR_ID, $groups)) {
            return true;
        }
        
        // Handle array of actions
        if (is_array($action)) {
            foreach ($action as $a) {
                if (self::checkSingleAction($resource, $a, $user, $groups)) {
                    return true;
                }
            }
            return false;
        }
        
        return self::checkSingleAction($resource, $action, $user, $groups);
    }

    private static function checkSingleAction(?object $resource, string $action, object $user, array $groups): bool
    {
        // Editors can do most things
        if (in_array(AclConstants::EDITOR_ID, $groups)) {
            $editorActions = ['create', 'read', 'update', 'delete', 'translate', 'publish', 'createTerm', 'list', 'readMaster', 'readReference', 'readThumbnail'];
            if (in_array($action, $editorActions)) {
                return true;
            }
        }
        
        // Contributors can create and update
        if (in_array(AclConstants::CONTRIBUTOR_ID, $groups)) {
            $contributorActions = ['create', 'read', 'update'];
            if (in_array($action, $contributorActions)) {
                return true;
            }
        }
        
        // Translators can translate
        if (in_array(AclConstants::TRANSLATOR_ID, $groups)) {
            if ($action === 'translate') {
                return true;
            }
        }
        
        // Check object-specific permissions
        $actionId = self::getActionId($action);
        if ($actionId && $resource) {
            $perm = DB::table('acl_permission')
                ->where(function($q) use ($user, $groups) {
                    $q->where('user_id', $user->id)
                      ->orWhereIn('group_id', $groups);
                })
                ->where('action', $action)
                ->where(function($q) use ($resource) {
                    $q->whereNull('object_id')
                      ->orWhere('object_id', $resource->id ?? null);
                })
                ->orderByRaw('object_id IS NULL')
                ->first();
            
            if ($perm) {
                return $perm->grant_deny == self::GRANT;
            }
        }
        
        return false;
    }

    public static function getUserGroups(?int $userId): array
    {
        if (!$userId) {
            return [];
        }
        
        if (self::$userGroups !== null && self::$user && self::$user->id === $userId) {
            return self::$userGroups;
        }
        
        self::$userGroups = DB::table('acl_user_group')
            ->where('user_id', $userId)
            ->pluck('group_id')
            ->toArray();
        
        return self::$userGroups;
    }

    public static function hasGroup(int $groupId, ?object $user = null): bool
    {
        $user = $user ?? self::$user;
        if (!$user) {
            return false;
        }
        return in_array($groupId, self::getUserGroups($user->id ?? null));
    }

    public static function isAdministrator(?object $user = null): bool
    {
        return self::hasGroup(AclConstants::ADMINISTRATOR_ID, $user);
    }

    public static function isEditor(?object $user = null): bool
    {
        return self::hasGroup(AclConstants::EDITOR_ID, $user);
    }

    public static function isContributor(?object $user = null): bool
    {
        return self::hasGroup(AclConstants::CONTRIBUTOR_ID, $user);
    }

    public static function isTranslator(?object $user = null): bool
    {
        return self::hasGroup(AclConstants::TRANSLATOR_ID, $user);
    }

    public static function getRepositoryAccess(string $action): array
    {
        $user = self::$user;
        if (!$user) {
            return [['access' => self::DENY]];
        }
        if (self::isAdministrator()) {
            return [['access' => self::GRANT]];
        }
        
        $groups = self::getUserGroups($user->id);
        $actionId = self::getActionId($action);
        
        $permissions = DB::table('acl_permission')
            ->whereIn('group_id', $groups)
            ->where('action', $action)
            ->whereNotNull('object_id')
            ->select('object_id', 'grant_deny')
            ->get();
        
        $result = [];
        foreach ($permissions as $perm) {
            $result[] = ['repository_id' => $perm->object_id, 'access' => $perm->grant_deny];
        }
        
        $defaultAccess = in_array(AclConstants::EDITOR_ID, $groups) ? self::GRANT : self::DENY;
        $result[] = ['access' => $defaultAccess];
        
        return $result;
    }

    public static function forwardUnauthorized(bool $return = false)
    {
        if ($return) {
            return false;
        }

        if (class_exists('sfContext') && \sfContext::hasInstance()) {
            \sfContext::getInstance()->getController()->forward('admin', 'secure');
            throw new \sfStopException();
        }
    }

    public static function forwardToSecureAction(): void
    {
        self::forwardUnauthorized();
    }

    public static function forwardToLoginAction(): void
    {
        if (class_exists('sfContext') && \sfContext::hasInstance()) {
            \sfContext::getInstance()->getController()->forward('user', 'login');
            throw new \sfStopException();
        }
    }

    /**
     * Filter a Laravel Query Builder query to only return published records
     * OR records the current user can view as drafts.
     *
     * Status type_id 158 = publicationStatusId
     * Status status_id 160 = PUBLICATION_STATUS_PUBLISHED_ID
     *
     * @param mixed $query Laravel Query Builder instance or Propel Criteria
     *
     * @return mixed
     */
    public static function addFilterDraftsCriteria($query): mixed
    {
        $user = self::$user;

        // Auto-load user from sfContext if not set
        if (!$user && class_exists('sfContext') && \sfContext::hasInstance()) {
            $sfUser = \sfContext::getInstance()->getUser();
            if ($sfUser && $sfUser->isAuthenticated() && isset($sfUser->user)) {
                $user = $sfUser->user;
                self::$user = $user;
            }
        }

        // If no user or not authenticated, only show published
        if (!$user) {
            // For Propel Criteria objects, return as-is (let base AtoM handle it)
            if (is_object($query) && !($query instanceof \Illuminate\Database\Query\Builder)) {
                return $query;
            }

            // For Laravel QB
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('status')
                    ->whereColumn('status.object_id', 'i.id')
                    ->where('status.type_id', 158)
                    ->where('status.status_id', 160);
            });

            return $query;
        }

        $groups = self::getUserGroups($user->id ?? null);

        // Administrators and editors can see all drafts
        if (in_array(AclConstants::ADMINISTRATOR_ID, $groups) || in_array(AclConstants::EDITOR_ID, $groups)) {
            return $query;
        }

        // For Propel Criteria objects, return as-is
        if (is_object($query) && !($query instanceof \Illuminate\Database\Query\Builder)) {
            return $query;
        }

        // Contributors can see their own drafts + all published
        $query->where(function ($q) use ($user) {
            // Published records
            $q->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('status')
                    ->whereColumn('status.object_id', 'i.id')
                    ->where('status.type_id', 158)
                    ->where('status.status_id', 160);
            });
            // OR records created by this user
            if ($user->id ?? null) {
                $q->orWhereExists(function ($sub) use ($user) {
                    $sub->select(DB::raw(1))
                        ->from('object')
                        ->whereColumn('object.id', 'i.id')
                        ->where('object.created_by', $user->id);
                });
            }
        });

        return $query;
    }

    private static function getActionId(string $action): ?int
    {
        $map = [
            'create' => 1, 'read' => 2, 'update' => 3, 'delete' => 4,
            'translate' => 5, 'publish' => 6, 'viewDraft' => 7,
            'readMaster' => 8, 'readReference' => 9, 'readThumbnail' => 10,
            'createTerm' => 3, 'list' => 2,
        ];
        return $map[$action] ?? null;
    }
}
