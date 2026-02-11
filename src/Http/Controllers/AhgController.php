<?php

namespace AtomFramework\Http\Controllers;

use AtomExtensions\Helpers\CultureHelper;
use AtomFramework\Helpers\CommonHelper;
use AtomFramework\Helpers\ResponseHelper;
use AtomFramework\Http\Compatibility\SfContextAdapter;
use AtomFramework\Http\Compatibility\SfResponseAdapter;
use AtomFramework\Http\Compatibility\SfUserAdapter;
use AtomFramework\Http\Compatibility\SfWebRequestAdapter;
use AtomFramework\Services\ConfigService;
use AtomFramework\Views\BladeRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Standalone base controller for AHG plugins.
 *
 * Provides the same API as AhgActions (which extends sfActions) but without
 * any Symfony dependency. Plugin action classes can switch from:
 *
 *   class myActions extends AhgActions → class myActions extends AhgController
 *
 * The property-assignment pattern ($this->varName = value) continues to work
 * via __set/__get magic. Templates receive the same variables.
 *
 * In standalone mode (heratio.php), dispatch() handles the full lifecycle:
 *   boot() → execute{Action}() → template auto-detection → Response
 *
 * In dual-stack mode (Symfony index.php + heratio.php), the same action class
 * works through both entry points — ActionBridge handles the routing.
 */
class AhgController
{
    // ─── Template Variable Storage ──────────────────────────────────

    /**
     * Template variables set via $this->varName = value.
     *
     * @var array<string, mixed>
     */
    protected array $templateVars = [];

    // ─── Internal State ─────────────────────────────────────────────

    /** Module name (set by dispatch or ActionBridge) */
    protected string $moduleName = '';

    /** Current action name */
    protected string $actionName = '';

    /** Redirect URL (set by redirect()) */
    protected ?string $redirectUrl = null;

    /** Forward target [module, action] (set by forward()) */
    protected ?array $forwardTarget = null;

    /** Template override name (set by setTemplate()) */
    protected ?string $templateOverride = null;

    /** Response object being built */
    protected SfResponseAdapter $response;

    /** Request adapter */
    protected ?SfWebRequestAdapter $request = null;

    /** User adapter */
    protected ?SfUserAdapter $user = null;

    /** Context adapter */
    protected ?SfContextAdapter $context = null;

    /** Whether a response was explicitly returned (renderText/renderJson) */
    protected ?\Symfony\Component\HttpFoundation\Response $explicitResponse = null;

    // ─── Constructor ────────────────────────────────────────────────

    public function __construct()
    {
        $this->response = new SfResponseAdapter();

        // Boot framework if needed
        $this->ensureFrameworkBooted();
    }

    /**
     * Ensure the framework bootstrap has run.
     */
    private function ensureFrameworkBooted(): void
    {
        static $booted = false;
        if ($booted) {
            return;
        }

        // In standalone mode, the kernel already boots everything.
        // In Symfony mode, bootstrap.php is loaded by AhgActions.
        // Check if bootstrap is needed:
        $rootDir = class_exists('\sfConfig', false)
            ? \sfConfig::get('sf_root_dir', '')
            : ConfigService::get('sf_root_dir', '');

        if ($rootDir) {
            $bootstrap = $rootDir . '/atom-framework/bootstrap.php';
            if (file_exists($bootstrap)) {
                require_once $bootstrap;
            }
        }

        $booted = true;
    }

    // ─── Magic Property Access (Backward Compat) ────────────────────

    /**
     * Store template variables via property assignment.
     *
     * Action code like `$this->pages = [...]` stores the value for
     * template rendering (same pattern as sfActions/sfVarHolder).
     */
    public function __set(string $name, $value): void
    {
        $this->templateVars[$name] = $value;
    }

    /**
     * Retrieve stored template variable.
     */
    public function __get(string $name)
    {
        return $this->templateVars[$name] ?? null;
    }

    /**
     * Check if a template variable is set.
     */
    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->templateVars);
    }

    /**
     * Get all stored template variables.
     */
    public function getTemplateVars(): array
    {
        return $this->templateVars;
    }

    // ─── Lifecycle Hooks ────────────────────────────────────────────

    /**
     * Boot hook — runs before execute{Action}().
     *
     * Override this instead of preExecute(). No need to call parent.
     * loadHelpers() calls are unnecessary — Blade handles i18n and URLs.
     */
    public function boot(): void
    {
        // Override in subclass if needed
    }

    // ─── Dispatch (Standalone Entry Point) ──────────────────────────

    /**
     * Dispatch to the appropriate execute{Action}() method.
     *
     * Called by ActionBridge in standalone mode. Handles the full lifecycle:
     * boot() → execute{Action}() → template rendering → Response.
     *
     * @param string                    $action  Action name (e.g., 'list', 'edit')
     * @param SfWebRequestAdapter|mixed $request Request adapter
     * @param string                    $module  Module name
     */
    public function dispatch(string $action, $request, string $module = ''): \Symfony\Component\HttpFoundation\Response
    {
        $this->actionName = $action;
        $this->moduleName = $module;
        $this->request = $request instanceof SfWebRequestAdapter ? $request : null;

        // Initialize user adapter from context if available
        if (SfContextAdapter::hasInstance()) {
            $this->context = SfContextAdapter::getInstance();
            $this->user = $this->context->getUser();
        }

        // Run boot hook
        $this->boot();

        // Check for redirect/forward set during boot
        if ($rsp = $this->checkEarlyExit()) {
            return $rsp;
        }

        // Find and call execute{Action}()
        $method = 'execute' . ucfirst($action);
        if (!method_exists($this, $method)) {
            return new Response("Action method {$method} not found", 404);
        }

        $result = $this->$method($request);

        // If action returned an explicit Response, use it
        if ($result instanceof \Symfony\Component\HttpFoundation\Response) {
            return $result;
        }

        // If renderText/renderJson set an explicit response
        if (null !== $this->explicitResponse) {
            return $this->explicitResponse;
        }

        // Check for redirect/forward set during action
        if ($rsp = $this->checkEarlyExit()) {
            return $rsp;
        }

        // If action returned a string (sfView::NONE / renderText output), wrap it
        if (is_string($result)) {
            return new Response($result, $this->response->getStatusCode(), [
                'Content-Type' => $this->response->getContentType(),
            ]);
        }

        // Auto-detect Blade template and render with template vars
        return $this->autoRender();
    }

    // ─── Symfony Compatibility API ──────────────────────────────────

    /**
     * Get the user adapter.
     */
    public function getUser(): SfUserAdapter
    {
        if (null !== $this->user) {
            return $this->user;
        }

        // Try sfContext adapter
        if (SfContextAdapter::hasInstance()) {
            $this->user = SfContextAdapter::getInstance()->getUser();

            return $this->user;
        }

        // Fallback: empty user
        $this->user = new SfUserAdapter();

        return $this->user;
    }

    /**
     * Get the request adapter.
     */
    public function getRequest(): ?SfWebRequestAdapter
    {
        return $this->request;
    }

    /**
     * Get the response adapter.
     */
    public function getResponse(): SfResponseAdapter
    {
        return $this->response;
    }

    /**
     * Get the context adapter.
     */
    public function getContext(): ?SfContextAdapter
    {
        if (null !== $this->context) {
            return $this->context;
        }

        if (SfContextAdapter::hasInstance()) {
            $this->context = SfContextAdapter::getInstance();

            return $this->context;
        }

        return null;
    }

    /**
     * Get the current module name.
     */
    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    /**
     * Get the current action name.
     */
    public function getActionName(): string
    {
        return $this->actionName;
    }

    /**
     * Set a redirect URL. The dispatch() method will convert this to a RedirectResponse.
     */
    public function redirect($url): void
    {
        // Handle named routes: @route_name?param=value
        if (is_string($url) && str_starts_with($url, '@')) {
            $url = $this->resolveNamedRoute($url);
        }

        $this->redirectUrl = $url;
    }

    /**
     * Set a forward target. In standalone mode, this triggers a sub-dispatch.
     */
    public function forward(string $module, string $action): void
    {
        $this->forwardTarget = [$module, $action];
    }

    /**
     * Throw a 404 response.
     */
    public function forward404(?string $message = null): void
    {
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(
            $message ?? 'Page not found'
        );
    }

    /**
     * Override the template to render.
     */
    public function setTemplate(string $name): void
    {
        $this->templateOverride = $name;
    }

    // ─── Service Access Helpers ─────────────────────────────────────

    /**
     * Get a configuration value.
     */
    protected function config(string $key, $default = null)
    {
        return ConfigService::get($key, $default);
    }

    /**
     * Get the current user's culture.
     */
    protected function culture(): string
    {
        return CultureHelper::getCulture();
    }

    /**
     * Get the current authenticated user's ID.
     */
    protected function userId(): ?int
    {
        return CommonHelper::getCurrentUserId();
    }

    // ─── Auth Helpers ───────────────────────────────────────────────

    /**
     * Require authentication — redirect to login if not authenticated.
     */
    protected function requireAuth(): void
    {
        if (!$this->getUser()->isAuthenticated()) {
            $this->redirect('user/login');
        }
    }

    /**
     * Require administrator — forward to secure module if not admin.
     */
    protected function requireAdmin(): void
    {
        if (!$this->getUser()->isAdministrator()) {
            $this->forward('admin', 'secure');
        }
    }

    // ─── Response Helpers ───────────────────────────────────────────

    /**
     * Render a JSON response.
     */
    protected function renderJson(array $data, int $status = 200)
    {
        $this->explicitResponse = new JsonResponse($data, $status);

        return $this->explicitResponse;
    }

    /**
     * Render a success JSON response.
     */
    protected function renderJsonSuccess($data = null, string $message = 'Success')
    {
        return $this->renderJson(ResponseHelper::success($data, $message));
    }

    /**
     * Render an error JSON response.
     */
    protected function renderJsonError(string $message, int $code = 400)
    {
        return $this->renderJson(ResponseHelper::error($message, $code), $code);
    }

    /**
     * Render raw text content.
     *
     * In sfActions this returns sfView::NONE. Here we set the explicit response.
     */
    protected function renderText(string $text)
    {
        $this->explicitResponse = new Response(
            $text,
            $this->response->getStatusCode(),
            ['Content-Type' => $this->response->getContentType()]
        );

        // Also merge any custom headers set on the response adapter
        foreach ($this->response->getHttpHeaders() as $name => $value) {
            $this->explicitResponse->header($name, $value);
        }

        return $this->explicitResponse;
    }

    /**
     * Render a Blade template.
     */
    protected function renderBlade(string $view, array $data = [], int $status = 200)
    {
        $renderer = BladeRenderer::getInstance();

        // Auto-register the calling plugin's view paths
        $this->registerPluginViews($renderer);

        // Merge common template data
        $data = array_merge([
            'sf_user' => $this->getUser(),
            'sf_request' => $this->getRequest(),
        ], $this->templateVars, $data);

        $html = $renderer->render($view, $data);

        $this->response->setStatusCode($status);
        $this->response->setContentType('text/html');

        return $this->renderText($html);
    }

    // ─── Internal Methods ───────────────────────────────────────────

    /**
     * Check for redirect/forward and return appropriate Response, or null.
     */
    private function checkEarlyExit(): ?\Symfony\Component\HttpFoundation\Response
    {
        if (null !== $this->redirectUrl) {
            return new RedirectResponse($this->redirectUrl);
        }

        if (null !== $this->forwardTarget) {
            [$module, $action] = $this->forwardTarget;

            // In standalone mode, forward returns a simple redirect
            // to the forwarded module/action URL
            return new RedirectResponse('/' . $module . '/' . $action);
        }

        return null;
    }

    /**
     * Auto-detect and render the Blade template for the current action.
     *
     * Checks for {action}Success.blade.php in plugin template directories.
     * Falls back to an empty 200 response if no template found.
     */
    private function autoRender(): \Symfony\Component\HttpFoundation\Response
    {
        $templateName = $this->templateOverride
            ?? ($this->actionName . 'Success');

        $bladeFile = $templateName . '.blade.php';

        // Search plugin template directories
        $dirs = $this->getTemplateDirs();

        foreach ($dirs as $dir) {
            if (file_exists($dir . '/' . $bladeFile)) {
                $renderer = BladeRenderer::getInstance();
                $renderer->addPath($dir);

                $data = array_merge([
                    'sf_user' => $this->getUser(),
                    'sf_request' => $this->getRequest(),
                    'sf_response' => $this->response,
                ], $this->templateVars);

                $html = $renderer->render($templateName, $data);

                return new Response($html, $this->response->getStatusCode(), [
                    'Content-Type' => 'text/html',
                ]);
            }
        }

        // No blade template found — check for PHP template (legacy)
        foreach ($dirs as $dir) {
            $phpFile = $templateName . '.php';
            if (file_exists($dir . '/' . $phpFile)) {
                $html = $this->renderPhpTemplate($dir . '/' . $phpFile);

                return new Response($html, $this->response->getStatusCode(), [
                    'Content-Type' => 'text/html',
                ]);
            }
        }

        // No template at all — return the response adapter state
        if ($this->response->hasContent()) {
            return $this->response->toIlluminateResponse();
        }

        return new Response('', $this->response->getStatusCode());
    }

    /**
     * Render a PHP template with template variables extracted into scope.
     */
    private function renderPhpTemplate(string $templatePath): string
    {
        // Extract template vars into local scope
        extract($this->templateVars);

        // Make standard vars available
        $sf_user = $this->getUser();
        $sf_request = $this->getRequest();
        $sf_response = $this->response;

        ob_start();
        require $templatePath;

        return ob_get_clean();
    }

    /**
     * Get template directories for the current module.
     */
    private function getTemplateDirs(): array
    {
        $rootDir = ConfigService::get('sf_root_dir', '');
        if (empty($rootDir)) {
            $rootDir = class_exists('\sfConfig', false) ? \sfConfig::get('sf_root_dir', '') : '';
        }

        if (empty($rootDir) || empty($this->moduleName)) {
            return [];
        }

        $dirs = [];
        $pluginsDir = $rootDir . '/plugins';

        if (is_dir($pluginsDir)) {
            $matches = glob($pluginsDir . '/*/modules/' . $this->moduleName . '/templates');
            foreach ($matches as $dir) {
                if (is_dir($dir)) {
                    $dirs[] = $dir;
                }
            }
        }

        return $dirs;
    }

    /**
     * Register plugin view paths with the Blade renderer.
     */
    private function registerPluginViews(BladeRenderer $renderer): void
    {
        $dirs = $this->getTemplateDirs();
        foreach ($dirs as $dir) {
            $renderer->addPath($dir);
        }
    }

    /**
     * Resolve a named route (@route_name?param=value) to a URL.
     */
    private function resolveNamedRoute(string $route): string
    {
        // Strip the @ prefix
        $route = substr($route, 1);

        // Split route name from query params
        $parts = explode('?', $route, 2);
        $routeName = $parts[0];
        $queryString = $parts[1] ?? '';

        // Try the Laravel router first
        try {
            $params = [];
            if ($queryString) {
                parse_str($queryString, $params);
            }

            return route($routeName, $params);
        } catch (\Exception $e) {
            // Fall back to simple path construction
        }

        // Fallback: just use the route name as a path hint
        $url = '/' . str_replace('_', '/', $routeName);
        if ($queryString) {
            $url .= '?' . $queryString;
        }

        return $url;
    }
}
