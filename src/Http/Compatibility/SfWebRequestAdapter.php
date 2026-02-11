<?php

namespace AtomFramework\Http\Compatibility;

use Illuminate\Http\Request;

/**
 * Wraps Illuminate\Http\Request with the sfWebRequest API.
 *
 * Provides backward compatibility for plugin action classes that call
 * sfWebRequest methods like getParameter(), isMethod(), etc.
 * Only used in standalone mode (heratio.php).
 */
class SfWebRequestAdapter
{
    private Request $request;
    private array $parameters = [];

    public function __construct(Request $request)
    {
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

    public function getAll(): array
    {
        return $this->parameters;
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
     * Get the underlying Illuminate Request.
     */
    public function getIlluminateRequest(): Request
    {
        return $this->request;
    }
}
