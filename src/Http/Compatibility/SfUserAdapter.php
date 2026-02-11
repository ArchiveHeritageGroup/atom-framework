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
}
