<?php

namespace AtomFramework\Http\Compatibility;

use Illuminate\Http\Request;

/*
 * Dual-stack conditional inheritance for sfWebRequest compatibility.
 *
 * When Symfony is loaded (dual-stack), SfWebRequestAdapter extends sfWebRequest
 * so that type-hinted methods (e.g., buildHiddenFields(sfWebRequest $request))
 * accept our adapter via instanceof checks.
 *
 * When standalone (no Symfony), SfWebRequestAdapter is self-contained.
 */
if (!class_exists(__NAMESPACE__ . '\\SfWebRequestAdapterBase', false)) {
    if (class_exists('sfWebRequest')) {
        class SfWebRequestAdapterBase extends \sfWebRequest
        {
            /**
             * Override constructor — skip sfWebRequest initialization.
             * sfWebRequest requires sfEventDispatcher which we don't have in Heratio mode.
             */
            public function __construct(Request $request)
            {
                // Intentionally skip parent::__construct()
            }
        }
    } else {
        class SfWebRequestAdapterBase
        {
            public function __construct(Request $request)
            {
                // Standalone — no parent to call
            }
        }
    }
}

/**
 * Wraps Illuminate\Http\Request with the sfWebRequest API.
 *
 * Provides backward compatibility for plugin action classes that call
 * sfWebRequest methods like getParameter(), isMethod(), etc.
 * In dual-stack mode, extends sfWebRequest for type-hint compatibility.
 */
class SfWebRequestAdapter extends SfWebRequestAdapterBase
{
    private Request $request;
    private array $parameters = [];

    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->request = $request;
        $this->parameters = array_merge(
            $request->query->all(),
            $request->request->all(),
            $request->route() ? $request->route()->parameters() : []
        );
    }

    public function getParameter(string $name, $default = null)
    {
        return $this->parameters[$name] ?? $default;
    }

    public function setParameter(string $name, $value): void
    {
        $this->parameters[$name] = $value;
    }

    public function hasParameter(string $name): bool
    {
        return array_key_exists($name, $this->parameters);
    }

    public function getParameterHolder(): self
    {
        return $this;
    }

    /**
     * sfParameterHolder::get() alias for getParameter().
     */
    public function get(string $name, $default = null)
    {
        return $this->parameters[$name] ?? $default;
    }

    /**
     * sfParameterHolder::set() alias for setParameter().
     */
    public function set(string $name, $value): void
    {
        $this->parameters[$name] = $value;
    }

    /**
     * sfParameterHolder::has() alias for hasParameter().
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->parameters);
    }

    /**
     * sfParameterHolder::remove().
     */
    public function remove(string $name): void
    {
        unset($this->parameters[$name]);
    }

    public function getAll(): array
    {
        return $this->parameters;
    }

    public function getNames(): array
    {
        return array_keys($this->parameters);
    }

    public function isMethod(string $method): bool
    {
        return strtoupper($method) === $this->request->getMethod();
    }

    public function getMethod(): string
    {
        return $this->request->getMethod();
    }

    public function getUri(): string
    {
        return $this->request->fullUrl();
    }

    public function getReferer(): ?string
    {
        return $this->request->headers->get('referer');
    }

    public function isSecure(): bool
    {
        return $this->request->isSecure();
    }

    public function getHost(): string
    {
        return $this->request->getHost();
    }

    public function getPathInfo(): string
    {
        return $this->request->getPathInfo();
    }

    public function getPathInfoArray(): array
    {
        return $this->request->server->all();
    }

    public function getHttpHeader(string $name, $default = null): ?string
    {
        return $this->request->headers->get($name, $default);
    }

    public function getCookie(string $name, $default = null)
    {
        return $this->request->cookies->get($name, $default);
    }

    public function getContentType(): string
    {
        return $this->request->getContentTypeFormat() ?? '';
    }

    public function getContent(): string
    {
        return $this->request->getContent();
    }

    /**
     * Get POST parameters (form data).
     */
    public function getPostParameters(): array
    {
        return $this->request->request->all();
    }

    /**
     * Magic property access for backward compatibility.
     * Symfony templates often access $request->limit, $request->page, etc.
     */
    public function __get(string $name)
    {
        return $this->getParameter($name);
    }

    public function __set(string $name, $value): void
    {
        $this->setParameter($name, $value);
    }

    public function __isset(string $name): bool
    {
        return $this->hasParameter($name);
    }

    /**
     * Get query (GET) parameters.
     */
    public function getGetParameters(): array
    {
        return $this->request->query->all();
    }

    /**
     * Get POST parameters.
     */
    public function getPostParameter(string $name, $default = null)
    {
        return $this->request->request->get($name, $default);
    }

    /**
     * Get a specific query parameter.
     */
    public function getGetParameter(string $name, $default = null)
    {
        return $this->request->query->get($name, $default);
    }

    /** @var array<string, mixed> Request attributes (sf_route, etc.) */
    private array $attributes = [];

    /**
     * Get a request attribute (used by sfAction::getRoute, etc.).
     */
    public function getAttribute(string $name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * Set a request attribute.
     */
    public function setAttribute(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Get uploaded files.
     */
    public function getFiles(?string $key = null): array
    {
        if (null !== $key) {
            return $this->request->file($key) ? [$this->request->file($key)] : [];
        }

        return $this->request->allFiles();
    }

    /**
     * Check if the request is an XMLHttpRequest (AJAX).
     */
    public function isXmlHttpRequest(): bool
    {
        return $this->request->ajax();
    }

    /**
     * Get the underlying Illuminate Request.
     */
    public function getIlluminateRequest(): Request
    {
        return $this->request;
    }
}
