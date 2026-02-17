<?php

namespace AtomFramework\Http\Controllers;

use AtomFramework\Http\Compatibility\SfContextAdapter;
use AtomFramework\Http\Compatibility\SfWebRequestAdapter;
use AtomFramework\Services\ConfigService;
use AtomFramework\Views\BladeRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Bridge that dispatches to existing plugin action classes from Laravel routes.
 *
 * Locates the action class file via plugin module directories, creates the
 * action instance, and calls execute{ActionName}() with a wrapped request.
 * Returns the rendered output as a Laravel Response.
 *
 * Supports three dispatch modes:
 *   1. AhgController — new standalone controllers (WP2)
 *   2. AhgActions — modern framework actions (Blade rendering)
 *   3. sfActions — base AtoM action classes (full compatibility bridge)
 */
class ActionBridge
{
    /**
     * Module name aliases: URL segment → actual module directory name.
     *
     * When nginx routes /security/ to Heratio, the catch-all parses
     * _module=security, but the action class lives in modules/securityClearance/.
     */
    private const MODULE_ALIASES = [
        'security' => 'securityClearance',
    ];

    /**
     * Action-level aliases: base AtoM module/action → AHG manage module/action.
     *
     * When the original module's action file is not found (e.g., because
     * apps/qubit/modules is not available in standalone mode), these aliases
     * redirect to AHG manage plugin equivalents.
     *
     * Format: 'baseModule' => ['baseAction' => ['targetModule', 'targetAction']]
     */
    private const ACTION_ALIASES = [
        'actor'          => ['browse' => ['actorManage', 'browse']],
        'repository'     => ['browse' => ['repositoryManage', 'browse']],
        'taxonomy'       => ['index' => ['termTaxonomy', 'index'], 'browse' => ['termTaxonomy', 'index']],
        'accession'      => ['browse' => ['accessionManage', 'browse']],
        'donor'          => ['browse' => ['donorManage', 'browse']],
        'rightsholder'   => ['browse' => ['rightsHolderManage', 'browse']],
        'physicalobject' => ['browse' => ['storageManage', 'browse']],
        'function'       => ['browse' => ['functionManage', 'browse']],
        'user'           => [
            'list' => ['userManage', 'browse'],
            'login' => ['_auth', 'login'],
            'logout' => ['_auth', 'logout'],
        ],
        'settings'       => ['global' => ['ahgSettings', 'index'], 'visibleElements' => ['ahgSettings', 'index']],
    ];

    /**
     * Default action map: module → default execute method name.
     *
     * When a module doesn't have execute{Action}() for the requested action,
     * try the default action instead. This covers cases like /heritage
     * defaulting to index but the real entry point is executeLanding().
     */
    private const DEFAULT_ACTIONS = [
        'heritage' => 'landing',
        'spectrum' => 'index',
        'reports' => 'index',
    ];

    /**
     * Dispatch to a plugin action class.
     *
     * Route parameters _module and _action are set by RouteCollector/RouteLoader.
     */
    public function dispatch(Request $request)
    {
        $module = $request->route()->parameter('_module')
            ?? $request->route()->defaults['_module']
            ?? null;
        $action = $request->route()->parameter('_action')
            ?? $request->route()->defaults['_action']
            ?? 'index';

        // Apply module aliases (URL segment → module directory name)
        if (null !== $module && isset(self::MODULE_ALIASES[$module])) {
            $module = self::MODULE_ALIASES[$module];
        }
        $slug = $request->route()->parameter('slug')
            ?? $request->route()->defaults['slug']
            ?? null;

        if (null === $module) {
            return new Response('Module not specified', 400);
        }

        // Slug-as-action detection: when a plugin route like "user/{slug}"
        // or "actor/{slug}" captures a URL like /user/login or /actor/browse,
        // the slug value is actually a base AtoM action name, not an entity slug.
        // Detect this by checking if the slug matches an action file in the
        // URL's first path segment module.
        if ($slug) {
            $urlSegments = explode('/', trim($request->getPathInfo(), '/'));
            $originalModule = $urlSegments[0] ?? $module;
            $potentialAction = $slug;
            $altFile = $this->findActionFile($originalModule, $potentialAction);
            if ($altFile) {
                // Slug is really an action name — re-route to the base module
                $module = $originalModule;
                $action = $potentialAction;
                $slug = null;
            }
        }

        // In standalone mode, ALL aliases must intercept BEFORE findActionFile
        // because the base AtoM action files still exist on disk but depend on
        // Symfony/Propel/Elasticsearch classes that aren't loaded in standalone mode.
        if (defined('HERATIO_STANDALONE') && isset(self::ACTION_ALIASES[$module][$action])) {
            [$aliasModule, $aliasAction] = self::ACTION_ALIASES[$module][$action];

            // Special _auth marker — delegate to AuthController
            if ('_auth' === $aliasModule) {
                $authController = new AuthController();
                $illuminateRequest = $request instanceof Request
                    ? $request
                    : Request::capture();

                if ('login' === $aliasAction) {
                    return $authController->login($illuminateRequest);
                }
                if ('logout' === $aliasAction) {
                    return $authController->logout($illuminateRequest);
                }
            }

            // Regular alias — find the AHG manage plugin action file
            $aliasFile = $this->findActionFile($aliasModule, $aliasAction);
            if (null !== $aliasFile) {
                $module = $aliasModule;
                $action = $aliasAction;

                return $this->executeAction($aliasFile, $module, $action, $request);
            }
        }

        // Locate the action class file
        $actionFile = $this->findActionFile($module, $action);

        // Fallback: if action not found, check ACTION_ALIASES to redirect
        // base AtoM module/action URLs to AHG manage plugin equivalents.
        // This handles standalone mode where apps/qubit/modules is unavailable.
        if (null === $actionFile && isset(self::ACTION_ALIASES[$module][$action])) {
            [$aliasModule, $aliasAction] = self::ACTION_ALIASES[$module][$action];

            $aliasFile = $this->findActionFile($aliasModule, $aliasAction);
            if (null !== $aliasFile) {
                $module = $aliasModule;
                $action = $aliasAction;
                $actionFile = $aliasFile;
            }
        }

        // If module not found and this is a slug route, resolve the slug
        // to determine the correct module (mimics QubitMetadataRoute behavior)
        if (null === $actionFile && $slug) {
            $resolved = $this->resolveSlugRoute($slug, $action);
            if ($resolved) {
                $module = $resolved['module'];
                $action = $resolved['action'];
                $actionFile = $this->findActionFile($module, $action);
            }
        }

        // If module not found, the first segment might be a slug (/{slug} pattern)
        if (null === $actionFile && 'object' === $module && 'show' === $action && $slug) {
            $resolved = $this->resolveSlugRoute($slug, 'index');
            if ($resolved) {
                $module = $resolved['module'];
                $action = $resolved['action'];
                $actionFile = $this->findActionFile($module, $action);
            }
        }

        if (null === $actionFile) {
            return new Response("Action not found: {$module}/{$action}", 404);
        }

        // Load and execute the action
        return $this->executeAction($actionFile, $module, $action, $request);
    }

    /**
     * Resolve a slug to a module/action pair (mimics QubitMetadataRoute).
     *
     * Looks up the slug in the database to determine the entity class,
     * then maps it to the appropriate module name.
     */
    private function resolveSlugRoute(string $slug, string $defaultAction = 'index'): ?array
    {
        try {
            $row = \Illuminate\Database\Capsule\Manager::table('slug')
                ->join('object', 'slug.object_id', '=', 'object.id')
                ->where('slug.slug', $slug)
                ->select('object.class_name', 'slug.object_id')
                ->first();

            if (!$row) {
                return null;
            }

            // Map Propel class to module name
            $classToModule = [
                'QubitInformationObject' => 'sfIsadPlugin',
                'QubitActor' => 'actor',
                'QubitRepository' => 'repository',
                'QubitTerm' => 'term',
                'QubitAccession' => 'accession',
                'QubitFunction' => 'sfIsdfiPlugin',
                'QubitRightsHolder' => 'rightsholder',
                'QubitDonor' => 'donor',
                'QubitPhysicalObject' => 'physicalobject',
                'QubitStaticPage' => 'staticpage',
            ];

            $module = $classToModule[$row->class_name] ?? null;
            if (!$module) {
                return null;
            }

            return [
                'module' => $module,
                'action' => $defaultAction,
            ];
        } catch (\Exception $e) {
            error_log('[heratio] Slug resolution failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Find the action class file in plugin module directories.
     */
    private function findActionFile(string $module, string $action): ?string
    {
        $rootDir = $this->getRootDir();
        $pluginsDir = $rootDir . '/plugins';
        if (!is_dir($pluginsDir)) {
            return null;
        }

        // Search all plugin directories for the module's actions
        $plugins = glob($pluginsDir . '/*/modules/' . $module);
        foreach ($plugins as $moduleDir) {
            $actionFile = $moduleDir . '/actions/' . $action . 'Action.class.php';
            if (file_exists($actionFile)) {
                return $actionFile;
            }

            // Also check for combined actions file
            $actionsFile = $moduleDir . '/actions/actions.class.php';
            if (file_exists($actionsFile)) {
                return $actionsFile;
            }
        }

        // Check base AtoM apps directory
        $baseModule = $rootDir . '/apps/qubit/modules/' . $module;
        if (is_dir($baseModule)) {
            $actionFile = $baseModule . '/actions/' . $action . 'Action.class.php';
            if (file_exists($actionFile)) {
                return $actionFile;
            }

            $actionsFile = $baseModule . '/actions/actions.class.php';
            if (file_exists($actionsFile)) {
                return $actionsFile;
            }
        }

        return null;
    }

    /**
     * Execute the action and return a Response.
     */
    private function executeAction(string $actionFile, string $module, string $action, Request $request): \Symfony\Component\HttpFoundation\Response
    {
        // Register autoloader for base module action classes (Default*Action, etc.)
        // These are parent classes defined in apps/qubit/modules/default/actions/
        // and plugins that other action classes extend.
        $this->registerActionAutoloader();

        // Require the action file
        require_once $actionFile;

        // Determine the class name
        $className = $this->resolveClassName($actionFile, $module, $action);
        if (null === $className || !class_exists($className, false)) {
            return new Response("Action class not found for {$module}/{$action}", 500);
        }

        // Create the request adapter
        $sfRequest = new SfWebRequestAdapter($request);

        // Set route parameters on the adapter
        $routeParams = $request->route() ? $request->route()->parameters() : [];
        foreach ($routeParams as $key => $value) {
            if ('_module' !== $key && '_action' !== $key) {
                $sfRequest->setParameter($key, $value);
            }
        }
        $sfRequest->setParameter('module', $module);
        $sfRequest->setParameter('action', $action);

        // Set module/action on the context
        $context = SfContextAdapter::getInstance();
        $context->setModuleName($module);
        $context->setActionName($action);

        try {
            // WP2: Check if action class extends AhgController (new standalone base)
            if (is_subclass_of($className, AhgController::class)) {
                return $this->executeAhgController($className, $action, $sfRequest, $module);
            }

            // Check if action class extends AhgActions (legacy modern base)
            if (is_subclass_of($className, \AtomFramework\Actions\AhgActions::class)
                || is_subclass_of($className, 'AhgActions')) {
                return $this->executeAhgAction($className, $module, $action, $sfRequest);
            }

            // For sfActions subclasses — full compatibility bridge
            return $this->executeSfAction($className, $module, $action, $sfRequest);
        } catch (\Throwable $e) {
            $status = 500;
            $body = ['error' => $e->getMessage() ?: get_class($e)];

            if (ConfigService::getBool('sf_debug', false) || empty($e->getMessage())) {
                $body['trace'] = $e->getTraceAsString();
                $body['file'] = $e->getFile() . ':' . $e->getLine();
                $body['class'] = get_class($e);
            }

            return new \Illuminate\Http\JsonResponse($body, $status);
        }
    }

    /**
     * Execute an AhgController-based action (WP2 standalone controllers).
     *
     * AhgController subclasses handle their own lifecycle via dispatch().
     * They return Illuminate\Http\Response objects directly.
     */
    private function executeAhgController(string $className, string $action, SfWebRequestAdapter $sfRequest, string $module): \Symfony\Component\HttpFoundation\Response
    {
        // When sfActions is loaded (via sfCoreAutoload), AhgControllerBase
        // extends sfActions → sfComponent, whose constructor requires
        // ($context, $moduleName, $actionName). In pure standalone mode
        // (no sfComponent), the constructor takes 0 args.
        if (is_subclass_of($className, 'sfComponent')) {
            $context = SfContextAdapter::getInstance();
            $instance = new $className($context, $module, $action);
        } else {
            $instance = new $className();
        }

        try {
            return $instance->dispatch($action, $sfRequest, $module);
        } catch (\sfStopException $e) {
            // Handle redirect/forward from ACL checks
            $context = SfContextAdapter::getInstance();
            $controller = $context->getController();

            if ($controller->hasRedirect()) {
                return new RedirectResponse(
                    $controller->getRedirectUrl(),
                    $controller->getRedirectStatusCode()
                );
            }

            if ($controller->hasForward()) {
                // Forward to 404 or login
                $fwdModule = $controller->getForwardModule();
                $fwdAction = $controller->getForwardAction();
                if ('sfError404' === $fwdModule || '404' === $fwdAction) {
                    return new Response('Page not found', 404);
                }

                // Redirect to login for unauthorized access
                return new RedirectResponse(self::loginUrl(), 302);
            }

            // Default: redirect to login
            return new RedirectResponse(self::loginUrl(), 302);
        }
    }

    /**
     * Execute an AhgActions-based action (modern framework actions).
     */
    private function executeAhgAction(string $className, string $module, string $action, SfWebRequestAdapter $sfRequest): \Symfony\Component\HttpFoundation\Response
    {
        $context = SfContextAdapter::getInstance();

        $instance = new $className($context, $module, $action);

        $method = 'execute' . ucfirst($action);
        if (!method_exists($instance, $method)) {
            // Try default action from map
            $defaultAction = self::DEFAULT_ACTIONS[$module] ?? null;
            if ($defaultAction) {
                $fallback = 'execute' . ucfirst($defaultAction);
                if (method_exists($instance, $fallback)) {
                    $method = $fallback;
                    $action = $defaultAction;
                }
            }
            // Generic fallback: try executeIndex
            if (!method_exists($instance, $method) && $action !== 'index' && method_exists($instance, 'executeIndex')) {
                $method = 'executeIndex';
                $action = 'index';
            }
            if (!method_exists($instance, $method)) {
                return new Response("Method {$method} not found on {$className}", 404);
            }
        }

        $result = $instance->$method($sfRequest);

        // If the action returned rendered text (via renderText/renderBlade)
        if (is_string($result)) {
            return new Response($result, 200, ['Content-Type' => 'text/html']);
        }

        // If the action set response content
        $contextResponse = $context->getResponse();
        if ($contextResponse->getContent()) {
            return $contextResponse;
        }

        return new Response('', 200);
    }

    /**
     * Execute an sfActions-based action with full compatibility bridge.
     *
     * This enables base AtoM modules (informationobject, actor, etc.)
     * to work through Heratio without the full Symfony boot sequence.
     *
     * Flow:
     *   1. Create sfActions instance with SfContextAdapter
     *   2. Call execute{Action}($request)
     *   3. Handle redirect/forward (via sfStopException)
     *   4. Render template with action vars
     *   5. Wrap in layout
     */
    private function executeSfAction(string $className, string $module, string $action, SfWebRequestAdapter $sfRequest): \Symfony\Component\HttpFoundation\Response
    {
        $context = SfContextAdapter::getInstance();
        $controller = $context->getController();
        $controller->reset();

        // Create the sfActions instance — sfComponent constructor calls
        // initialize($context, $moduleName, $actionName) which stores
        // the context, request, response, and event dispatcher.
        $instance = new $className($context, $module, $action);

        // Set up sf_route attribute for actions that use $this->getRoute()->resource.
        // This mimics QubitMetadataRoute which resolves slugs to Propel objects.
        $slug = $sfRequest->getParameter('slug');
        if ($slug) {
            $resource = $this->resolveResource($slug);
            if ($resource) {
                $sfRequest->setAttribute('sf_route', new class($resource) {
                    public $resource;

                    public function __construct($resource)
                    {
                        $this->resource = $resource;
                    }
                });
            }
        }

        // Determine the execute method
        $method = 'execute' . ucfirst($action);
        if (!method_exists($instance, $method)) {
            // Some classes use a combined execute() method
            if (method_exists($instance, 'execute')) {
                $method = 'execute';
            } else {
                return new Response("Method {$method} not found on {$className}", 404);
            }
        }

        // Execute the action — may throw sfStopException on redirect/forward
        $viewName = null;
        try {
            $viewName = $instance->$method($sfRequest);
        } catch (\sfStopException $e) {
            // Normal flow — redirect or forward was called
        } catch (\sfForwardException $e) {
            // Forward to another action
            $controller->forward($e->getModule(), $e->getAction());
        } catch (\Exception $e) {
            // Check for forward/redirect exceptions that might use different names
            if (str_contains(get_class($e), 'Stop') || str_contains(get_class($e), 'Forward')) {
                // Treated as flow control
            } else {
                throw $e;
            }
        }

        // Handle redirect
        if ($controller->hasRedirect()) {
            return new RedirectResponse(
                $controller->getRedirectUrl(),
                $controller->getRedirectStatusCode()
            );
        }

        // Handle forward (recursive dispatch)
        if ($controller->hasForward()) {
            $fwdModule = $controller->getForwardModule();
            $fwdAction = $controller->getForwardAction();
            $fwdFile = $this->findActionFile($fwdModule, $fwdAction);
            if ($fwdFile) {
                require_once $fwdFile;
                $fwdClass = $this->resolveClassName($fwdFile, $fwdModule, $fwdAction);
                if ($fwdClass) {
                    $sfRequest->setParameter('module', $fwdModule);
                    $sfRequest->setParameter('action', $fwdAction);

                    return $this->executeSfAction($fwdClass, $fwdModule, $fwdAction, $sfRequest);
                }
            }

            return new Response("Forward target not found: {$fwdModule}/{$fwdAction}", 404);
        }

        // If renderText() was used, viewName is 'None' — return response content
        if ('None' === $viewName || (defined('sfView::NONE') && \sfView::NONE === $viewName)) {
            $content = $context->getResponse()->getContent();

            return new Response($content ?: '', 200, ['Content-Type' => 'text/html']);
        }

        // Default view name
        if (null === $viewName || true === $viewName) {
            $viewName = 'Success';
        }

        // Extract action variables for the template
        $vars = $this->extractActionVars($instance);
        $vars['sf_user'] = $context->getUser();
        $vars['sf_request'] = $sfRequest;
        $vars['sf_context'] = $context;

        // Find and render the template
        $templateFile = $this->findTemplate($module, $action, $viewName);
        if (null === $templateFile) {
            // No template found — return response content if any
            $content = $context->getResponse()->getContent();
            if ($content) {
                return new Response($content, 200, ['Content-Type' => 'text/html']);
            }

            return new Response("Template not found: {$module}/{$action}{$viewName}", 404);
        }

        // Render the PHP template
        $content = $this->renderPhpTemplate($templateFile, $vars);

        // Wrap in layout
        $html = $this->wrapInLayout($content, $vars);

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Extract public properties from an action instance as template vars.
     *
     * sfView extracts action vars via sfParameterHolder. In standalone mode,
     * we use reflection to get public properties and varHolder contents.
     */
    private function extractActionVars(object $instance): array
    {
        $vars = [];

        // Get public properties (the standard Symfony pattern)
        $reflection = new \ReflectionObject($instance);
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            // Skip internal sfComponent properties
            if (in_array($name, ['request', 'response', 'context', 'dispatcher', 'moduleName', 'actionName', 'varHolder', 'requestParameterHolder'])) {
                continue;
            }
            $vars[$name] = $prop->getValue($instance);
        }

        // Also check varHolder if it exists (sfParameterHolder)
        if (isset($instance->varHolder) && method_exists($instance->varHolder, 'getAll')) {
            $holderVars = $instance->varHolder->getAll();
            $vars = array_merge($vars, $holderVars);
        }

        return $vars;
    }

    /**
     * Find the template file for a module/action/viewName combination.
     *
     * Search order (matching Symfony's template resolution):
     *   1. Plugin overrides (ahg plugins first, then base plugins)
     *   2. Base AtoM module templates
     */
    private function findTemplate(string $module, string $action, string $viewName): ?string
    {
        $rootDir = $this->getRootDir();
        $templateName = $action . $viewName . '.php';
        $indexTemplate = 'index' . $viewName . '.php';

        // 1. Search plugin module templates (AHG plugins take priority)
        $pluginDirs = glob($rootDir . '/plugins/*/modules/' . $module . '/templates');
        // Sort so ahg* plugins come first
        usort($pluginDirs, function ($a, $b) {
            $aIsAhg = str_contains($a, '/ahg');
            $bIsAhg = str_contains($b, '/ahg');
            if ($aIsAhg && !$bIsAhg) {
                return -1;
            }
            if (!$aIsAhg && $bIsAhg) {
                return 1;
            }

            return strcmp($a, $b);
        });

        foreach ($pluginDirs as $dir) {
            $file = $dir . '/' . $templateName;
            if (file_exists($file)) {
                return $file;
            }
            // Try indexSuccess.php for index action
            if ('index' === $action) {
                $file = $dir . '/' . $indexTemplate;
                if (file_exists($file)) {
                    return $file;
                }
            }
        }

        // 2. Base AtoM module templates
        $baseDir = $rootDir . '/apps/qubit/modules/' . $module . '/templates';
        if (is_dir($baseDir)) {
            $file = $baseDir . '/' . $templateName;
            if (file_exists($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Render a PHP template file with extracted variables.
     */
    private function renderPhpTemplate(string $templateFile, array $vars): string
    {
        // Load Symfony template helper shims (slot, url_for, link_to, etc.)
        // Guarded with function_exists() — safe when Symfony is also loaded.
        require_once dirname(__DIR__, 2) . '/Views/blade_shims.php';

        // Make standard Symfony template variables available
        if (!isset($vars['sf_user'])) {
            $vars['sf_user'] = SfContextAdapter::getInstance()->getUser();
        }
        if (!isset($vars['sf_request'])) {
            $vars['sf_request'] = SfContextAdapter::getInstance()->getRequest();
        }

        // Extract variables into template scope
        extract($vars, EXTR_SKIP);

        // $sf_data provides escaped access (in standalone, just pass through)
        if (!isset($sf_data)) {
            $sf_data = new class($vars) {
                private array $vars;

                public function __construct(array $vars)
                {
                    $this->vars = $vars;
                }

                public function __get(string $name)
                {
                    return $this->vars[$name] ?? null;
                }

                public function __isset(string $name): bool
                {
                    return isset($this->vars[$name]);
                }

                public function getRaw(string $name)
                {
                    return $this->vars[$name] ?? null;
                }
            };
        }

        ob_start();

        try {
            include $templateFile;
        } catch (\Throwable $e) {
            ob_end_clean();
            error_log("[heratio] Template error in {$templateFile}: " . $e->getMessage());

            return '<div class="alert alert-danger">Template error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        return ob_get_clean();
    }

    /**
     * Wrap action content in the Heratio master layout.
     */
    private function wrapInLayout(string $content, array $vars = []): string
    {
        // Skip wrapping if the content already contains a full HTML document.
        // This happens when:
        //  - Blade templates use @extends('layouts.page') which renders full HTML
        //  - PHP templates call get_partial('layout_start') which outputs <html>/<head>/<body>
        $trimmed = ltrim($content);
        if (str_starts_with($trimmed, '<!DOCTYPE') || str_starts_with($trimmed, '<html')) {
            return $content;
        }

        try {
            $renderer = BladeRenderer::getInstance();

            return $renderer->render('layouts.heratio', array_merge($vars, [
                'sf_user' => SfContextAdapter::getInstance()->getUser(),
                'sf_content' => $content,
                'siteTitle' => ConfigService::get('siteTitle', ConfigService::get('app_siteTitle', 'AtoM')),
                'culture' => SfContextAdapter::getInstance()->getUser()->getCulture(),
            ]));
        } catch (\Throwable $e) {
            // Fallback: return content with minimal HTML wrapper
            error_log('[heratio] Layout render failed: ' . $e->getMessage());

            return '<!DOCTYPE html><html><head><meta charset="utf-8">'
                . '<title>' . htmlspecialchars(ConfigService::get('siteTitle', 'AtoM')) . '</title>'
                . '<link rel="stylesheet" href="/dist/css/app.css">'
                . '</head><body>' . $content . '</body></html>';
        }
    }

    /**
     * Resolve the action class name from the file.
     */
    private function resolveClassName(string $actionFile, string $module, string $action): ?string
    {
        $basename = basename($actionFile, '.class.php');

        // Convention: {module}{Action}Action (e.g., donorManageBrowseAction)
        // or just {action}Action
        $candidates = [
            $module . ucfirst($action) . 'Action',
            $action . 'Action',
            $module . 'Actions',
            'actions',
        ];

        // Also check for class defined in the file
        foreach ($candidates as $candidate) {
            if (class_exists($candidate, false)) {
                return $candidate;
            }
        }

        // Parse the file to find the class name
        $content = file_get_contents($actionFile);
        if (preg_match('/class\s+(\w+)\s+extends/', $content, $matches)) {
            $detectedClass = $matches[1];
            if (class_exists($detectedClass, false)) {
                return $detectedClass;
            }
        }

        return null;
    }

    /** @var bool Whether the action autoloader has been registered */
    private static bool $actionAutoloaderRegistered = false;

    /**
     * Register an autoloader for AtoM module action/component base classes.
     *
     * Many action classes extend base classes from the "default" module
     * (DefaultBrowseAction, DefaultEditAction, etc.) or from other modules.
     * This autoloader searches module action directories for the class file.
     */
    private function registerActionAutoloader(): void
    {
        if (self::$actionAutoloaderRegistered) {
            return;
        }

        $rootDir = $this->getRootDir();

        spl_autoload_register(function (string $class) use ($rootDir) {
            // Skip namespaced classes
            if (str_contains($class, '\\')) {
                return;
            }

            // Search default module first (most common parent classes)
            $defaultDir = $rootDir . '/apps/qubit/modules/default/actions';
            if (is_dir($defaultDir)) {
                // Try ClassName.class.php
                $file = $defaultDir . '/' . lcfirst($class) . '.class.php';
                if (file_exists($file)) {
                    require_once $file;

                    return;
                }
                // Try walking the directory
                foreach (glob($defaultDir . '/*.class.php') as $candidate) {
                    $content = file_get_contents($candidate);
                    if (preg_match('/class\s+' . preg_quote($class) . '\b/', $content)) {
                        require_once $candidate;

                        return;
                    }
                }
            }

            // Search application lib directories (form formatters, helpers, etc.)
            // These are NOT in sfCoreAutoload's class map in standalone dispatch.
            $libDirs = [
                $rootDir . '/lib/form',
                $rootDir . '/lib',
            ];
            foreach ($libDirs as $libDir) {
                if (!is_dir($libDir)) {
                    continue;
                }
                foreach (glob($libDir . '/*.class.php') as $candidate) {
                    $basename = basename($candidate, '.class.php');
                    if (strcasecmp($basename, $class) === 0) {
                        require_once $candidate;

                        return;
                    }
                }
            }

            // Search all plugin and base module action directories
            $searchDirs = array_merge(
                glob($rootDir . '/plugins/*/modules/*/actions') ?: [],
                glob($rootDir . '/apps/qubit/modules/*/actions') ?: []
            );

            foreach ($searchDirs as $dir) {
                foreach (glob($dir . '/*.class.php') as $candidate) {
                    $basename = basename($candidate, '.class.php');
                    if (strcasecmp($basename, $class) === 0) {
                        require_once $candidate;

                        return;
                    }
                }
            }
        });

        self::$actionAutoloaderRegistered = true;
    }

    /**
     * Resolve a slug to its Propel model object.
     *
     * Uses QubitXxx::getBySlug() to load the actual Propel object,
     * matching what QubitMetadataRoute does for the sf_route resource.
     */
    private function resolveResource(string $slug): ?object
    {
        try {
            $row = \Illuminate\Database\Capsule\Manager::table('slug')
                ->join('object', 'slug.object_id', '=', 'object.id')
                ->where('slug.slug', $slug)
                ->select('object.class_name', 'slug.object_id')
                ->first();

            if (!$row) {
                return null;
            }

            $className = $row->class_name;

            // Try Propel first for full ORM object
            try {
                if (class_exists($className) && method_exists($className, 'getById')) {
                    $resource = $className::getById($row->object_id);
                    if ($resource) {
                        return $resource;
                    }
                }
            } catch (\Throwable $propelError) {
                // Propel failed — fall through to lightweight DB object
                error_log('[heratio] Propel getById failed for ' . $className . '#' . $row->object_id . ': ' . $propelError->getMessage());
            }

            // Fallback: build a lightweight resource object from DB
            return $this->buildLightweightResource($className, $row->object_id, $slug);
        } catch (\Throwable $e) {
            error_log('[heratio] resolveResource failed for slug=' . $slug . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Build a lightweight resource object from DB when Propel fails.
     *
     * Returns an object with the most commonly used properties
     * (__toString, id, slug, title, etc.) so templates can render.
     */
    private function buildLightweightResource(string $className, int $objectId, string $slug): ?object
    {
        $DB = \Illuminate\Database\Capsule\Manager::class;
        $culture = SfContextAdapter::getInstance()->getUser()->getCulture();

        // Map class to its table and i18n table
        $classToTable = [
            'QubitStaticPage' => ['table' => 'static_page', 'i18n' => 'static_page_i18n', 'fields' => ['title', 'content']],
            'QubitInformationObject' => ['table' => 'information_object', 'i18n' => 'information_object_i18n', 'fields' => ['title']],
            'QubitActor' => ['table' => 'actor', 'i18n' => 'actor_i18n', 'fields' => ['authorized_form_of_name']],
            'QubitRepository' => ['table' => 'repository', 'i18n' => 'repository_i18n', 'fields' => ['authorized_form_of_name']],
            'QubitTerm' => ['table' => 'term', 'i18n' => 'term_i18n', 'fields' => ['name']],
        ];

        $config = $classToTable[$className] ?? null;
        if (!$config) {
            return null;
        }

        // Load i18n data
        $i18n = $DB::table($config['i18n'])
            ->where('id', $objectId)
            ->where('culture', $culture)
            ->first();

        if (!$i18n) {
            // Try source culture fallback
            $i18n = $DB::table($config['i18n'])
                ->where('id', $objectId)
                ->first();
        }

        // Build resource object with properties that templates commonly use
        $data = (array) ($i18n ?? []);
        $data['id'] = $objectId;
        $data['slug'] = $slug;
        $data['className'] = $className;

        return new class($data, $config, $culture) {
            private array $data;
            private array $config;
            private string $culture;

            public function __construct(array $data, array $config, string $culture)
            {
                $this->data = $data;
                $this->config = $config;
                $this->culture = $culture;
            }

            public function __toString(): string
            {
                // Try i18n title fields in order
                foreach ($this->config['fields'] as $field) {
                    if (!empty($this->data[$field])) {
                        return (string) $this->data[$field];
                    }
                }

                return $this->data['slug'] ?? '';
            }

            public function __get(string $name)
            {
                if ('slug' === $name) {
                    return $this->data['slug'] ?? '';
                }
                if ('id' === $name) {
                    return $this->data['id'] ?? 0;
                }
                if ('className' === $name) {
                    return $this->data['className'] ?? '';
                }

                return $this->data[$name] ?? null;
            }

            public function __isset(string $name): bool
            {
                return isset($this->data[$name]) || in_array($name, ['slug', 'id', 'className']);
            }

            /**
             * Get i18n content with culture fallback.
             */
            public function getContent(array $options = []): string
            {
                return $this->data['content'] ?? '';
            }
        };
    }

    /**
     * Get the login URL, standalone-aware.
     */
    private static function loginUrl(): string
    {
        return defined('HERATIO_STANDALONE') ? '/auth/login' : '/index.php/user/login';
    }

    /**
     * Get the AtoM root directory.
     */
    private function getRootDir(): string
    {
        $rootDir = ConfigService::get('sf_root_dir', '');
        if (empty($rootDir)) {
            $rootDir = defined('ATOM_ROOT_PATH') ? ATOM_ROOT_PATH : '';
        }

        return $rootDir;
    }
}
