<?php

namespace AtomFramework\Http\Compatibility;

use Illuminate\Http\Response;

/**
 * Standalone sfWebResponse adapter for when Symfony is not loaded.
 *
 * Captures setTitle, setContentType, setHttpHeader, setStatusCode, etc.
 * so that action classes written for sfWebResponse work seamlessly in
 * standalone mode (heratio.php). The captured state is converted into
 * an Illuminate\Http\Response at the end of the dispatch cycle.
 */
class SfResponseAdapter
{
    private string $title = '';
    private string $contentType = 'text/html';
    private int $statusCode = 200;
    private string $content = '';
    private array $httpHeaders = [];
    private array $metas = [];
    private array $stylesheets = [];
    private array $javascripts = [];

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function setContentType(string $contentType): void
    {
        $this->contentType = $contentType;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $code): void
    {
        $this->statusCode = $code;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function setHttpHeader(string $name, string $value, bool $replace = true): void
    {
        $this->httpHeaders[$name] = $value;
    }

    public function getHttpHeader(string $name, $default = null)
    {
        return $this->httpHeaders[$name] ?? $default;
    }

    public function getHttpHeaders(): array
    {
        return $this->httpHeaders;
    }

    public function addMeta(string $key, string $value, bool $replace = true, bool $escape = true): void
    {
        $this->metas[$key] = $value;
    }

    public function getMetas(): array
    {
        return $this->metas;
    }

    public function addStylesheet(string $file, string $position = '', array $options = []): void
    {
        $this->stylesheets[] = ['file' => $file, 'position' => $position, 'options' => $options];
    }

    public function addJavascript(string $file, string $position = '', array $options = []): void
    {
        $this->javascripts[] = ['file' => $file, 'position' => $position, 'options' => $options];
    }

    /**
     * Convert captured state into an Illuminate Response.
     */
    public function toIlluminateResponse(): Response
    {
        $response = new Response($this->content, $this->statusCode);

        $response->header('Content-Type', $this->contentType);

        foreach ($this->httpHeaders as $name => $value) {
            $response->header($name, $value);
        }

        return $response;
    }

    /**
     * Check if content has been set.
     */
    public function hasContent(): bool
    {
        return '' !== $this->content;
    }
}
