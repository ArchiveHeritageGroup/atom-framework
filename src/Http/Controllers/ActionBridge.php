<?php

namespace AtomFramework\Http\Controllers;

use AtomFramework\Http\Compatibility\SfContextAdapter;
use AtomFramework\Http\Compatibility\SfWebRequestAdapter;
use AtomFramework\Services\ConfigService;
use AtomFramework\Views\BladeRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Bridge that dispatches to existing plugin action classes from Laravel routes.
 *
 * Locates the action class file via plugin module directories, creates the
 * action instance, and calls execute{ActionName}() with a wrapped request.
 * Returns the rendered output as a Laravel Response.
 *
 * This is the backward-compatibility layer that lets existing AhgActions and
 * sfAction subclasses work within the Laravel router without rewriting them.
 */
class ActionBridge
{
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

        if (null === $module) {
            return new Response('Module not specified', 400);
        }

        // Locate the action class file
        $actionFile = $this->findActionFile($module, $action);
        if (null === $actionFile) {
            return new Response("Action not found: {$module}/{$action}", 404);
        }

        // Load and execute the action
        return $this->executeAction($actionFile, $module, $action, $request);
    }

    /**
     * Find the action class file in plugin module directories.
     */
    private function findActionFile(string $module, string $action): ?string
    {
        $rootDir = ConfigService::get('sf_root_dir', '');
        if (empty($rootDir)) {
            $rootDir = defined('ATOM_ROOT_PATH') ? ATOM_ROOT_PATH : '';
        }

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
    private function executeAction(string $actionFile, string $module, string $action, Request $request): Response
    {
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

        try {
            // WP2: Check if action class extends AhgController (new standalone base)
            if (is_subclass_of($className, AhgController::class)) {
                return $this->executeAhgController($className, $action, $sfRequest, $module);
            }

            // Check if action class extends AhgActions (legacy modern base)
            if (is_subclass_of($className, \AtomFramework\Actions\AhgActions::class)
                || is_subclass_of($className, 'AhgActions')) {
                return $this->executeAhgAction($className, $action, $sfRequest, $request);
            }

            // For sfActions subclasses, we need Symfony's full context
            // In standalone mode, use a simplified execution path
            return $this->executeStandaloneAction($className, $action, $sfRequest, $request);
        } catch (\Exception $e) {
            $status = 500;
            $body = ['error' => $e->getMessage()];

            if (ConfigService::getBool('sf_debug', false)) {
                $body['trace'] = $e->getTraceAsString();
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
        $instance = new $className();

        return $instance->dispatch($action, $sfRequest, $module);
    }

    /**
     * Execute an AhgActions-based action (modern framework actions).
     */
    private function executeAhgAction(string $className, string $action, SfWebRequestAdapter $sfRequest, Request $request): Response
    {
        // AhgActions can work with SfWebRequestAdapter in standalone mode
        // because they primarily use $request->getParameter() and renderBlade()
        $instance = new $className(
            SfContextAdapter::getInstance(),
            $module ?? '',
            $action
        );

        $method = 'execute' . ucfirst($action);
        if (!method_exists($instance, $method)) {
            return new Response("Method {$method} not found on {$className}", 404);
        }

        $result = $instance->$method($sfRequest);

        // If the action returned rendered text (via renderText/renderBlade)
        if (is_string($result)) {
            return new Response($result, 200, ['Content-Type' => 'text/html']);
        }

        // If the action set response content
        $context = SfContextAdapter::getInstance();
        $contextResponse = $context->getResponse();
        if ($contextResponse->getContent()) {
            return $contextResponse;
        }

        return new Response('', 200);
    }

    /**
     * Execute an action in simplified standalone mode.
     *
     * For actions that require sfContext, this provides a basic execution
     * environment. Full Symfony context is not available — actions that
     * heavily depend on sfView/sfController should be routed through
     * Symfony's index.php instead.
     */
    private function executeStandaloneAction(string $className, string $action, SfWebRequestAdapter $sfRequest, Request $request): Response
    {
        // Return a helpful error for now — full sfActions bridging requires
        // Symfony context which is only available through index.php
        return new \Illuminate\Http\JsonResponse([
            'error' => 'This action requires the Symfony stack',
            'action' => $action,
            'class' => $className,
            'hint' => 'Route this path through Symfony (index.php) instead of Heratio',
        ], 501);
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
}
