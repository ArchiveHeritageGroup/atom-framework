<?php

namespace AtomFramework\Http\Compatibility;

use Illuminate\Http\Request;

/**
 * Wraps session data with the sfUser API.
 *
 * Provides backward compatibility for plugin code that calls
 * sfUser methods like isAuthenticated(), getCulture(), getAttribute().
 * Session data is backed by PHP's native session or a configured store.
 */
class SfUserAdapter
{
    private array $attributes = [];
    private string $culture = 'en';
    private bool $authenticated = false;
    private ?int $userId = null;
    private array $credentials = [];

    public function __construct(?Request $request = null)
    {
        if (null !== $request && $request->hasSession()) {
            $session = $request->session();
            $this->authenticated = $session->get('authenticated', false);
            $this->userId = $session->get('user_id');
            $this->culture = $session->get('culture', 'en');
            $this->credentials = $session->get('credentials', []);
            $this->attributes = $session->get('sf_user_attributes', []);
        }
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function setAuthenticated(bool $authenticated): void
    {
        $this->authenticated = $authenticated;
    }

    public function isAdministrator(): bool
    {
        return in_array('administrator', $this->credentials);
    }

    public function getCulture(): string
    {
        return $this->culture;
    }

    public function setCulture(string $culture): void
    {
        $this->culture = $culture;
    }

    public function getAttribute(string $name, $default = null, string $ns = 'sfUser')
    {
        return $this->attributes[$ns][$name] ?? $default;
    }

    public function setAttribute(string $name, $value, string $ns = 'sfUser'): void
    {
        $this->attributes[$ns][$name] = $value;
    }

    public function hasAttribute(string $name, string $ns = 'sfUser'): bool
    {
        return isset($this->attributes[$ns][$name]);
    }

    public function removeAttribute(string $name, string $ns = 'sfUser'): void
    {
        unset($this->attributes[$ns][$name]);
    }

    public function getAttributeHolder(): self
    {
        return $this;
    }

    public function hasCredential(string $credential): bool
    {
        return in_array($credential, $this->credentials);
    }

    public function addCredential(string $credential): void
    {
        if (!in_array($credential, $this->credentials)) {
            $this->credentials[] = $credential;
        }
    }

    public function getUserID(): ?int
    {
        return $this->userId;
    }

    /**
     * Get the user's myUser instance (for compatibility with AtoM code).
     * Returns self since this adapter already provides the user API.
     */
    public function getGuardUser(): self
    {
        return $this;
    }

    // ─── Flash Messages ──────────────────────────────────────────────

    /** @var array<string, string> Flash messages for next request */
    private array $flash = [];

    /**
     * Set a flash message (displayed on next request).
     */
    public function setFlash(string $name, string $value): void
    {
        $this->flash[$name] = $value;
    }

    /**
     * Get a flash message.
     */
    public function getFlash(string $name, $default = null)
    {
        return $this->flash[$name] ?? $default;
    }

    /**
     * Check if a flash message exists.
     */
    public function hasFlash(string $name): bool
    {
        return isset($this->flash[$name]);
    }

    // ─── Group Membership ───────────────────────────────────────────

    /** @var array<int> Group IDs the user belongs to */
    private array $groups = [];

    /**
     * Check if the user belongs to a group (by group ID).
     *
     * In AtoM, this checks QubitAclGroup membership. In standalone mode,
     * group membership is loaded from the session or set programmatically.
     */
    public function hasGroup(int $groupId): bool
    {
        // Administrator group (100) check — also use credentials
        if (100 === $groupId && $this->isAdministrator()) {
            return true;
        }

        return in_array($groupId, $this->groups);
    }

    /**
     * Set group memberships (for standalone mode initialization).
     */
    public function setGroups(array $groupIds): void
    {
        $this->groups = $groupIds;
    }
}
