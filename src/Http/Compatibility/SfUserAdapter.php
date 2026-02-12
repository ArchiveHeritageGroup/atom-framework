<?php

namespace AtomFramework\Http\Compatibility;

use AtomExtensions\Services\AclService;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Wraps $_SESSION data with the sfUser/myUser API.
 *
 * Reads and writes session data using Symfony's exact key format so that
 * authentication state is shared between Symfony (index.php) and Heratio
 * (heratio.php) during the dual-stack transition period.
 *
 * Symfony stores session data as flat keys in $_SESSION:
 *   'symfony/user/sfUser/authenticated' => bool
 *   'symfony/user/sfUser/credentials'   => array
 *   'symfony/user/sfUser/lastRequest'   => int (timestamp)
 *   'symfony/user/sfUser/culture'       => string
 *   'symfony/user/sfUser/attributes'    => nested array (keyed by namespace)
 */
class SfUserAdapter
{
    // ─── Symfony Session Key Constants ────────────────────────────────

    private const AUTH_NS = 'symfony/user/sfUser/authenticated';
    private const CREDENTIAL_NS = 'symfony/user/sfUser/credentials';
    private const LAST_REQ_NS = 'symfony/user/sfUser/lastRequest';
    private const ATTRIBUTE_NS = 'symfony/user/sfUser/attributes';
    private const CULTURE_NS = 'symfony/user/sfUser/culture';
    private const DEFAULT_NS = 'symfony/user/sfUser/attributes';
    private const FLASH_NS = 'symfony/user/sfUser/flash';
    private const FLASH_RM_NS = 'symfony/user/sfUser/flash/remove';

    /** @var object|null Cached user object (loaded by AuthMiddleware) */
    public ?object $user = null;

    // ─── Session I/O Helpers ─────────────────────────────────────────

    private function sessionGet(string $key, $default = null)
    {
        return (PHP_SESSION_ACTIVE === session_status()) ? ($_SESSION[$key] ?? $default) : $default;
    }

    private function sessionSet(string $key, $value): void
    {
        if (PHP_SESSION_ACTIVE === session_status()) {
            $_SESSION[$key] = $value;
        }
    }

    // ─── Authentication ──────────────────────────────────────────────

    public function isAuthenticated(): bool
    {
        return (bool) $this->sessionGet(self::AUTH_NS, false);
    }

    public function setAuthenticated(bool $authenticated): void
    {
        $this->sessionSet(self::AUTH_NS, $authenticated);
    }

    public function isAdministrator(): bool
    {
        return in_array('administrator', $this->getCredentials());
    }

    /**
     * Sign in a user — replicates myUser::signIn().
     *
     * Sets session state to match what Symfony's myUser would set.
     *
     * @param object $user User object with id, email/username, slug, name properties
     */
    public function signIn(object $user): void
    {
        $this->setAuthenticated(true);
        $this->user = $user;

        // Store user attributes in Symfony's default namespace
        $this->setAttribute('user_id', $user->id);
        $this->setAttribute('user_slug', $user->slug ?? '');
        $this->setAttribute('user_name', $user->email ?? $user->username ?? '');

        // Load credentials from group membership
        $credentials = $this->buildCredentials((int) $user->id);
        $this->sessionSet(self::CREDENTIAL_NS, $credentials);

        // Update last request timestamp
        $this->updateLastRequest();
    }

    /**
     * Sign out the current user — replicates myUser::signOut().
     */
    public function signOut(): void
    {
        $this->setAuthenticated(false);
        $this->sessionSet(self::CREDENTIAL_NS, []);
        $this->user = null;

        // Clear user attributes but preserve culture
        $attrs = $this->sessionGet(self::ATTRIBUTE_NS, []);
        $defaultNs = $attrs[self::DEFAULT_NS] ?? [];
        unset(
            $defaultNs['user_id'],
            $defaultNs['user_slug'],
            $defaultNs['user_name'],
            $defaultNs['login_route']
        );
        $attrs[self::DEFAULT_NS] = $defaultNs;

        // Clear credential scope
        unset($attrs['credentialScope']);

        $this->sessionSet(self::ATTRIBUTE_NS, $attrs);
    }

    // ─── Credentials ─────────────────────────────────────────────────

    public function getCredentials(): array
    {
        return $this->sessionGet(self::CREDENTIAL_NS, []);
    }

    public function hasCredential(string $credential): bool
    {
        return in_array($credential, $this->getCredentials());
    }

    public function addCredential(string $credential): void
    {
        $credentials = $this->getCredentials();
        if (!in_array($credential, $credentials)) {
            $credentials[] = $credential;
            $this->sessionSet(self::CREDENTIAL_NS, $credentials);
        }
    }

    public function removeCredential(string $credential): void
    {
        $credentials = array_values(array_diff($this->getCredentials(), [$credential]));
        $this->sessionSet(self::CREDENTIAL_NS, $credentials);
    }

    // ─── Culture ─────────────────────────────────────────────────────

    public function getCulture(): string
    {
        return $this->sessionGet(self::CULTURE_NS, 'en');
    }

    public function setCulture(string $culture): void
    {
        $this->sessionSet(self::CULTURE_NS, $culture);
    }

    // ─── Attributes (sfNamespacedParameterHolder compatible) ─────────

    public function getAttribute(string $name, $default = null, string $ns = 'symfony/user/sfUser/attributes')
    {
        $attrs = $this->sessionGet(self::ATTRIBUTE_NS, []);

        return $attrs[$ns][$name] ?? $default;
    }

    public function setAttribute(string $name, $value, string $ns = 'symfony/user/sfUser/attributes'): void
    {
        $attrs = $this->sessionGet(self::ATTRIBUTE_NS, []);
        $attrs[$ns][$name] = $value;
        $this->sessionSet(self::ATTRIBUTE_NS, $attrs);
    }

    public function hasAttribute(string $name, string $ns = 'symfony/user/sfUser/attributes'): bool
    {
        $attrs = $this->sessionGet(self::ATTRIBUTE_NS, []);

        return isset($attrs[$ns][$name]);
    }

    public function removeAttribute(string $name, string $ns = 'symfony/user/sfUser/attributes'): void
    {
        $attrs = $this->sessionGet(self::ATTRIBUTE_NS, []);
        unset($attrs[$ns][$name]);
        $this->sessionSet(self::ATTRIBUTE_NS, $attrs);
    }

    /**
     * Remove all attributes in a namespace.
     */
    public function removeNamespace(string $ns): void
    {
        $attrs = $this->sessionGet(self::ATTRIBUTE_NS, []);
        unset($attrs[$ns]);
        $this->sessionSet(self::ATTRIBUTE_NS, $attrs);
    }

    /**
     * Get the attribute holder (sfNamespacedParameterHolder compat).
     * Returns self since this adapter provides the same API.
     */
    public function getAttributeHolder(): self
    {
        return $this;
    }

    /**
     * Remove an attribute (alias used by sfNamespacedParameterHolder::remove).
     */
    public function remove(string $name, $default = null, string $ns = 'symfony/user/sfUser/attributes')
    {
        $value = $this->getAttribute($name, $default, $ns);
        $this->removeAttribute($name, $ns);

        return $value;
    }

    /**
     * Get all attributes in a namespace.
     */
    public function getAll(string $ns = 'symfony/user/sfUser/attributes'): array
    {
        $attrs = $this->sessionGet(self::ATTRIBUTE_NS, []);

        return $attrs[$ns] ?? [];
    }

    // ─── User ID ─────────────────────────────────────────────────────

    public function getUserID(): ?int
    {
        $id = $this->getAttribute('user_id');

        return $id ? (int) $id : null;
    }

    /**
     * Get the user's myUser/guard instance (for compatibility with AtoM code).
     * Returns self since this adapter already provides the user API.
     */
    public function getGuardUser(): self
    {
        return $this;
    }

    // ─── Flash Messages ──────────────────────────────────────────────

    /**
     * Set a flash message (displayed on next request).
     * Uses Symfony's flash namespace within the attributes array.
     */
    public function setFlash(string $name, string $value): void
    {
        $attrs = $this->sessionGet(self::ATTRIBUTE_NS, []);
        $attrs[self::FLASH_NS][$name] = $value;

        // Remove from the "to-be-removed" list since it's freshly set
        unset($attrs[self::FLASH_RM_NS][$name]);

        $this->sessionSet(self::ATTRIBUTE_NS, $attrs);
    }

    /**
     * Get a flash message.
     */
    public function getFlash(string $name, $default = null)
    {
        $attrs = $this->sessionGet(self::ATTRIBUTE_NS, []);

        return $attrs[self::FLASH_NS][$name] ?? $default;
    }

    /**
     * Check if a flash message exists.
     */
    public function hasFlash(string $name): bool
    {
        $attrs = $this->sessionGet(self::ATTRIBUTE_NS, []);

        return isset($attrs[self::FLASH_NS][$name]);
    }

    // ─── Timeout ─────────────────────────────────────────────────────

    /**
     * Check if the session has timed out (default 1800s = 30 min).
     */
    public function isTimedOut(int $timeout = 1800): bool
    {
        $lastRequest = $this->sessionGet(self::LAST_REQ_NS, 0);

        if (0 === $lastRequest) {
            return false;
        }

        return (time() - $lastRequest) > $timeout;
    }

    /**
     * Update the last request timestamp.
     */
    public function updateLastRequest(): void
    {
        $this->sessionSet(self::LAST_REQ_NS, time());
    }

    /**
     * Get the last request timestamp.
     */
    public function getLastRequestTime(): int
    {
        return (int) $this->sessionGet(self::LAST_REQ_NS, 0);
    }

    // ─── Group Membership ────────────────────────────────────────────

    /**
     * Check if the user belongs to a group (by group ID).
     *
     * Uses AclService for DB-based lookup (matching myUser behavior).
     */
    public function hasGroup(int $groupId): bool
    {
        // Administrator group (100) check — also use credentials
        if (100 === $groupId && $this->isAdministrator()) {
            return true;
        }

        $userId = $this->getUserID();
        if (!$userId) {
            return false;
        }

        return AclService::hasGroup($groupId);
    }

    // ─── Internal ────────────────────────────────────────────────────

    /**
     * Build credentials array from user's group membership.
     * Maps AtoM group IDs to credential strings.
     */
    private function buildCredentials(int $userId): array
    {
        $credentials = [];

        try {
            $groups = DB::table('acl_user_group')
                ->where('user_id', $userId)
                ->pluck('group_id')
                ->toArray();
        } catch (\Exception $e) {
            return $credentials;
        }

        // Map group IDs to credential strings (matching myUser behavior)
        $groupMap = [
            100 => 'administrator',
            101 => 'editor',
            102 => 'contributor',
            103 => 'translator',
        ];

        foreach ($groups as $groupId) {
            if (isset($groupMap[$groupId])) {
                $credentials[] = $groupMap[$groupId];
            }
        }

        return $credentials;
    }
}
