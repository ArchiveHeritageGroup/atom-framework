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

/*
 * Dual-stack conditional inheritance.
 *
 * When Symfony is loaded (index.php), AhgController extends sfActions so that
 * Symfony's filter chain can call isSecure(), getCredential(), initialize(), etc.
 *
 * When standalone (heratio.php), AhgController is self-contained with its own
 * property-assignment magic and adapter-based helpers.
 */
if (!class_exists(__NAMESPACE__ . '\\AhgControllerBase', false)) {
    if (class_exists('sfActions', false)) {
        // Symfony mode: inherit all sfActions methods (isSecure, initialize, etc.)
        class AhgControllerBase extends \sfActions
        {
            /**
             * Bridge: preExecute() calls boot() so subclasses only need boot().
             */
            public function preExecute()
            {
                // Load framework bootstrap
                static $frameworkBooted = false;
                if (!$frameworkBooted) {
                    $rootDir = \sfConfig::get('sf_root_dir', '');
                    if ($rootDir) {
                        $bootstrap = $rootDir . '/atom-framework/bootstrap.php';
                        if (file_exists($bootstrap)) {
                            require_once $bootstrap;
                        }
                    }
                    $frameworkBooted = true;
                }

                $this->boot();
            }
        }
    } else {
        // Standalone mode: no Symfony dependency
        class AhgControllerBase
        {
            protected array $templateVars = [];
            protected string $moduleName = '';
            protected string $actionName = '';
            protected ?string $redirectUrl = null;
            protected ?array $forwardTarget = null;
            protected ?string $templateOverride = null;
            protected SfResponseAdapter $sfResponse;
            protected ?SfWebRequestAdapter $sfRequest = null;
            protected ?SfUserAdapter $sfUser = null;
            protected ?SfContextAdapter $sfContext = null;
            protected ?\Symfony\Component\HttpFoundation\Response $explicitResponse = null;

            public function __construct()
            {
                $this->sfResponse = new SfResponseAdapter();

                static $frameworkBooted = false;
                if (!$frameworkBooted) {
                    $rootDir = class_exists('\sfConfig', false)
                        ? \sfConfig::get('sf_root_dir', '')
                        : ConfigService::get('sf_root_dir', '');
                    if ($rootDir) {
                        $bootstrap = $rootDir . '/atom-framework/bootstrap.php';
                        if (file_exists($bootstrap)) {
                            require_once $bootstrap;
                        }
                    }
                    $frameworkBooted = true;
                }
            }

            public function __set(string $name, $value): void
            {
                $this->templateVars[$name] = $value;
            }

            public function __get(string $name)
            {
                return $this->templateVars[$name] ?? null;
            }

            public function __isset(string $name): bool
            {
                return array_key_exists($name, $this->templateVars);
            }

            public function getUser(): SfUserAdapter
            {
                if (null !== $this->sfUser) {
                    return $this->sfUser;
                }
                if (SfContextAdapter::hasInstance()) {
                    $this->sfUser = SfContextAdapter::getInstance()->getUser();
                    return $this->sfUser;
                }
                $this->sfUser = new SfUserAdapter();
                return $this->sfUser;
            }

            public function getRequest(): ?SfWebRequestAdapter
            {
                return $this->sfRequest;
            }

            public function getResponse(): SfResponseAdapter
            {
                return $this->sfResponse;
            }

            public function getContext(): ?SfContextAdapter
            {
                if (null !== $this->sfContext) {
                    return $this->sfContext;
                }
                if (SfContextAdapter::hasInstance()) {
                    $this->sfContext = SfContextAdapter::getInstance();
                    return $this->sfContext;
                }
                return null;
            }

            public function getModuleName(): string
            {
                return $this->moduleName;
            }

            public function getActionName(): string
            {
                return $this->actionName;
            }

            public function redirect($url): void
            {
                $this->redirectUrl = is_string($url) && str_starts_with($url, '@')
                    ? $this->resolveNamedRoute($url)
                    : $url;
            }

            public function forward(string $module, string $action): void
            {
                $this->forwardTarget = [$module, $action];
            }

            public function forward404(?string $message = null): void
            {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(
                    $message ?? 'Page not found'
                );
            }

            public function setTemplate(string $name): void
            {
                $this->templateOverride = $name;
            }

            public function renderText($text)
            {
                $this->explicitResponse = new Response(
                    $text,
                    $this->sfResponse->getStatusCode(),
                    ['Content-Type' => $this->sfResponse->getContentType()]
                );
                foreach ($this->sfResponse->getHttpHeaders() as $n => $v) {
                    $this->explicitResponse->header($n, $v);
                }
                return $this->explicitResponse;
            }

            private function resolveNamedRoute(string $route): string
            {
                $route = substr($route, 1);
                $parts = explode('?', $route, 2);
                $routeName = $parts[0];
                $queryString = $parts[1] ?? '';
                try {
                    $params = [];
                    if ($queryString) {
                        parse_str($queryString, $params);
                    }
                    return route($routeName, $params);
                } catch (\Exception $e) {
                }
                $url = '/' . str_replace('_', '/', $routeName);
                if ($queryString) {
                    $url .= '?' . $queryString;
                }
                return $url;
            }
        }
    }
}

/**
 * Standalone base controller for AHG plugins.
 *
 * Dual-stack compatible:
 * - Through Symfony (index.php): extends sfActions via AhgControllerBase,
 *   inherits isSecure(), initialize(), getCredential(), etc.
 * - Through Laravel (heratio.php): standalone with adapter-based helpers.
 *
 * Plugin action classes use:
 *   class myActions extends AhgController
 *
 * The property-assignment pattern ($this->varName = value) works in both modes.
 * New helpers: config(), culture(), userId(), boot(), renderJson(), renderBlade().
 */
class AhgController extends AhgControllerBase
{
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
     */
    public function dispatch(string $action, $request, string $module = ''): \Symfony\Component\HttpFoundation\Response
    {
        // In standalone mode, set state directly
        if (!class_exists('sfActions', false)) {
            $this->actionName = $action;
            $this->moduleName = $module;
            $this->sfRequest = $request instanceof SfWebRequestAdapter ? $request : null;

            if (SfContextAdapter::hasInstance()) {
                $this->sfContext = SfContextAdapter::getInstance();
                $this->sfUser = $this->sfContext->getUser();
            }
        }

        // Run boot hook
        $this->boot();

        // Check for redirect/forward set during boot
        if (!class_exists('sfActions', false)) {
            if ($rsp = $this->checkEarlyExit()) {
                return $rsp;
            }
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

        // If renderText/renderJson set an explicit response (standalone mode)
        if (!class_exists('sfActions', false) && null !== $this->explicitResponse) {
            return $this->explicitResponse;
        }

        // Check for redirect/forward (standalone mode)
        if (!class_exists('sfActions', false)) {
            if ($rsp = $this->checkEarlyExit()) {
                return $rsp;
            }
        }

        // Auto-detect Blade template and render with template vars
        return $this->autoRender();
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
        if (class_exists('sfActions', false)) {
            // Symfony mode: use sfActions' response + renderText
            $this->getResponse()->setContentType('application/json');

            return $this->renderText(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // Standalone mode
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
     * Render raw text content — works in both Symfony and standalone mode.
     *
     * In Symfony mode: delegates to sfActions::renderText() which returns sfView::NONE.
     * In standalone mode: sets explicit Response object.
     */
    public function renderText($text)
    {
        if (class_exists('sfActions', false)) {
            return parent::renderText($text);
        }

        return parent::renderText($text);
    }

    /**
     * Render a Blade template.
     */
    protected function renderBlade(string $view, array $data = [], int $status = 200)
    {
        $renderer = BladeRenderer::getInstance();
        $this->registerPluginViews($renderer);

        $data = array_merge([
            'sf_user' => $this->getUser(),
            'sf_request' => $this->getRequest(),
        ], $this->getTemplateVars(), $data);

        $html = $renderer->render($view, $data);

        return $this->renderText($html);
    }

    // ─── Template Variables ─────────────────────────────────────────

    /**
     * Get all stored template variables.
     *
     * In Symfony mode, reads from sfActions' varHolder.
     * In standalone mode, reads from templateVars array.
     */
    public function getTemplateVars(): array
    {
        if (class_exists('sfActions', false)) {
            // sfActions uses a var holder for template vars
            $vars = [];
            if (method_exists($this, 'getVarHolder')) {
                $holder = $this->getVarHolder();
                if ($holder) {
                    $vars = $holder->getAll();
                }
            }

            return $vars;
        }

        return $this->templateVars ?? [];
    }

    // ─── Internal Methods (Standalone Only) ─────────────────────────

    /**
     * Check for redirect/forward and return appropriate Response, or null.
     * Only used in standalone mode.
     */
    private function checkEarlyExit(): ?\Symfony\Component\HttpFoundation\Response
    {
        if (isset($this->redirectUrl) && null !== $this->redirectUrl) {
            return new RedirectResponse($this->redirectUrl);
        }

        if (isset($this->forwardTarget) && null !== $this->forwardTarget) {
            [$module, $action] = $this->forwardTarget;

            return new RedirectResponse('/' . $module . '/' . $action);
        }

        return null;
    }

    /**
     * Auto-detect and render the Blade template for the current action.
     * Only used in standalone mode dispatch.
     */
    private function autoRender(): \Symfony\Component\HttpFoundation\Response
    {
        $actionName = class_exists('sfActions', false)
            ? $this->getActionName()
            : ($this->actionName ?? '');

        $templateName = $this->templateOverride ?? ($actionName . 'Success');
        $bladeFile = $templateName . '.blade.php';

        $dirs = $this->getTemplateDirs();

        foreach ($dirs as $dir) {
            if (file_exists($dir . '/' . $bladeFile)) {
                $renderer = BladeRenderer::getInstance();
                $renderer->addPath($dir);

                $data = array_merge([
                    'sf_user' => $this->getUser(),
                    'sf_request' => $this->getRequest(),
                ], $this->getTemplateVars());

                $html = $renderer->render($templateName, $data);

                return new Response($html, 200, ['Content-Type' => 'text/html']);
            }
        }

        // No blade template found — check for PHP template (legacy)
        foreach ($dirs as $dir) {
            $phpFile = $templateName . '.php';
            if (file_exists($dir . '/' . $phpFile)) {
                $html = $this->renderPhpTemplate($dir . '/' . $phpFile);

                return new Response($html, 200, ['Content-Type' => 'text/html']);
            }
        }

        return new Response('', 200);
    }

    /**
     * Render a PHP template with template variables extracted into scope.
     */
    private function renderPhpTemplate(string $templatePath): string
    {
        extract($this->getTemplateVars());
        $sf_user = $this->getUser();
        $sf_request = $this->getRequest();

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
        if (empty($rootDir) && class_exists('\sfConfig', false)) {
            $rootDir = \sfConfig::get('sf_root_dir', '');
        }

        $moduleName = class_exists('sfActions', false)
            ? $this->getModuleName()
            : ($this->moduleName ?? '');

        if (empty($rootDir) || empty($moduleName)) {
            return [];
        }

        $dirs = [];
        $pluginsDir = $rootDir . '/plugins';

        if (is_dir($pluginsDir)) {
            $matches = glob($pluginsDir . '/*/modules/' . $moduleName . '/templates');
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
}
