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
        
        // No authenticated user means ANONYMOUS, not "denied". Returning false
        // here refused every anonymous visitor in every module that calls this
        // service, regardless of publication status and regardless of the
        // anonymous group's own grants - a published record still 403'd.
        // Base AtoM's QubitAcl evaluates the anonymous group; so must we, or the
        // two disagree and only the pages routed through base AtoM work.
        if (!$user) {
            // $action may be a single action or a list of them - the array
            // branch below runs AFTER this point, so the list has to be
            // handled here too. Passing an array into a string parameter
            // threw a TypeError on live record views.
            if (is_array($action)) {
                foreach ($action as $single) {
                    if (self::checkAnonymous($resource, (string) $single)) {
                        return true;
                    }
                }

                return false;
            }

            return self::checkAnonymous($resource, (string) $action);
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
            $scope = self::scopeIds($resource);
            $rows = DB::table('acl_permission')
                ->where(function($q) use ($user, $groups) {
                    $q->where('user_id', $user->id)
                      ->orWhereIn('group_id', $groups);
                })
                ->where('action', $action)
                ->where(function($q) use ($scope) {
                    $q->whereNull('object_id')
                      ->orWhereIn('object_id', $scope);
                })
                ->get(['object_id', 'grant_deny']);

            $perm = self::nearest($rows, $scope);
            if ($perm) {
                return self::isGrant($perm->grant_deny);
            }
        }
        
        return false;
    }

    /**
     * Is this acl_permission row a grant?
     *
     * The stored value is 0 = deny, 1 = grant - that is what base AtoM writes
     * and what every row in the wild contains. AclConstants::GRANT is 2, so the
     * previous comparison `grant_deny == self::GRANT` could never be true and
     * every explicit permission row read as a denial. Both values are accepted
     * so a deployment written against the constant still works.
     */
    private static function isGrant($grantDeny): bool
    {
        return in_array((int) $grantDeny, [1, self::GRANT], true);
    }

    /**
     * Evaluate a permission for an unauthenticated visitor against the
     * anonymous group, with the same object-specific-before-global precedence
     * used for signed-in users.
     */
    private static function checkAnonymous(?object $resource, string $action): bool
    {
        $scope = self::scopeIds($resource);
        $rows = DB::table('acl_permission')
            ->where('group_id', AclConstants::ANONYMOUS_ID)
            ->where('action', $action)
            ->where(function ($q) use ($scope) {
                $q->whereNull('object_id')
                  ->orWhereIn('object_id', $scope);
            })
            ->get(['object_id', 'grant_deny']);

        $perm = self::nearest($rows, $scope);

        return $perm ? self::isGrant($perm->grant_deny) : false;
    }

    /**
     * The objects a permission on this resource can come from, nearest first: the
     * resource itself, then (for an archival description) its ancestors up to the
     * root. Base AtoM's QubitAcl inherits a grant down the tree; RARI and most sites
     * grant readThumbnail/readReference on the root (object 1) only, so checking the
     * record alone denied every thumbnail and term pages showed generic icons
     * (13 Nov 2026).
     *
     * @return int[]
     */
    private static function scopeIds(?object $resource): array
    {
        $id = (int) ($resource->id ?? 0);
        if ($id <= 0) {
            return [];
        }
        static $cache = [];
        if (isset($cache[$id])) {
            return $cache[$id];
        }
        $ids = [$id];
        try {
            $node = DB::table('information_object')->where('id', $id)->first(['lft', 'rgt']);
            if ($node) {
                $ids = DB::table('information_object')
                    ->where('lft', '<=', $node->lft)->where('rgt', '>=', $node->rgt)
                    ->orderByDesc('lft')->pluck('id')->map(fn ($v) => (int) $v)->all();
            }
        } catch (\Throwable $e) {
            $ids = [$id];
        }
        if (count($cache) > 2000) {
            $cache = [];
        }

        return $cache[$id] = $ids;
    }

    /** The permission row from the nearest object in $scope; a global row (no object) last. */
    private static function nearest(iterable $rows, array $scope): ?object
    {
        $best = null;
        $bestRank = PHP_INT_MAX;
        $rank = array_flip($scope);
        foreach ($rows as $row) {
            $r = null === $row->object_id ? count($scope) : ($rank[(int) $row->object_id] ?? PHP_INT_MAX);
            if ($r < $bestRank) {
                $best = $row;
                $bestRank = $r;
            }
        }

        return $best;
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
        // Base AtoM treats every signed-in user as a member of the authenticated
        // group; without it a researcher had none of that group's grants here.
        if (!in_array(AclConstants::AUTHENTICATED_ID, self::$userGroups)) {
            self::$userGroups[] = AclConstants::AUTHENTICATED_ID;
        }
        
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

        // Fallback: hard redirect if sfContext not available
        if (!headers_sent()) {
            $loginUrl = defined('HERATIO_STANDALONE') ? '/auth/login' : '/index.php/user/login';
            header('Location: ' . $loginUrl, true, 302);
        }
        exit;
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

        // Fallback: hard redirect if sfContext not available
        if (!headers_sent()) {
            $loginUrl = defined('HERATIO_STANDALONE') ? '/auth/login' : '/index.php/user/login';
            header('Location: ' . $loginUrl, true, 302);
        }
        exit;
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
