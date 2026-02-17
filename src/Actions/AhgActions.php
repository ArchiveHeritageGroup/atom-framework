<?php

namespace AtomFramework\Actions;

use AtomExtensions\Helpers\CultureHelper;
use AtomFramework\Helpers\CommonHelper;
use AtomFramework\Helpers\ResponseHelper;
use AtomFramework\Services\ConfigService;
use AtomFramework\Views\BladeRenderer;

/**
 * Base action class for AHG plugins.
 *
 * Extends sfActions with automatic framework bootstrap and modern helpers.
 * Plugin action classes should extend this instead of sfActions directly.
 *
 * Usage:
 *   class myActions extends AhgActions {
 *       public function executeIndex(sfWebRequest $request) {
 *           // Framework is already bootstrapped — no need for AhgDb::init()
 *           $culture = $this->culture();
 *           $userId = $this->userId();
 *       }
 *   }
 */
class AhgActions extends \sfActions
{
    protected static bool $frameworkBooted = false;

    /**
     * Auto-bootstrap the framework before every action.
     */
    public function preExecute()
    {
        parent::preExecute();

        if (!self::$frameworkBooted) {
            $bootstrap = \sfConfig::get('sf_root_dir') . '/atom-framework/bootstrap.php';
            if (file_exists($bootstrap)) {
                require_once $bootstrap;
            }
            self::$frameworkBooted = true;
        }
    }

    // ─── JSON Response Helpers ───────────────────────────────────────

    /**
     * Render a JSON response and return sfView::NONE.
     */
    protected function renderJson(array $data, int $status = 200)
    {
        $this->getResponse()->setStatusCode($status);
        $this->getResponse()->setContentType('application/json');

        return $this->renderText(
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
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

    // ─── Service Access ──────────────────────────────────────────────

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

    // ─── Auth Helpers ────────────────────────────────────────────────

    /**
     * Require authentication — redirect to login if not authenticated.
     */
    protected function requireAuth(): void
    {
        if (!$this->getUser()->isAuthenticated()) {
            $this->redirect(defined('HERATIO_STANDALONE') ? '/auth/login' : 'user/login');
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

    // ─── Blade Auto-Detection & Rendering ───────────────────────

    /**
     * Override execute() to auto-detect blade templates.
     *
     * After the action method runs, if it returned null (meaning
     * "render the default success template"), we check if a
     * .blade.php version exists. If so, render via BladeRenderer
     * and return sfView::NONE to skip the PHP template.
     */
    public function execute($request)
    {
        $result = parent::execute($request);

        // Only auto-detect when the action wants the default success template
        if ($result !== null) {
            return $result;
        }

        return $this->tryBladeAutoRender();
    }

    /**
     * Check if a blade template exists for the current action and render it.
     *
     * @return string|null sfView::NONE if blade rendered, null to fall back to PHP
     */
    private function tryBladeAutoRender()
    {
        $templateName = $this->getActionName() . 'Success';
        $bladeFile = $templateName . '.blade.php';
        $moduleName = $this->getModuleName();

        $dirs = $this->getContext()->getConfiguration()->getTemplateDirs($moduleName);

        foreach ($dirs as $dir) {
            if (file_exists($dir . '/' . $bladeFile)) {
                $data = $this->getVarHolder()->getAll();

                return $this->renderBlade($templateName, $data);
            }
        }

        return null;
    }

    /**
     * Render a Blade template and return it as the response.
     *
     * Bypasses Symfony's sfPHPView — the compiled Blade output becomes
     * the full response body. Use for standalone pages (admin panels,
     * dashboards, CRUD forms) that don't need Symfony's layout decorator.
     *
     * @param string $view   Dot-notation view name (e.g., 'vendor.list')
     * @param array  $data   Variables to pass to the template
     * @param int    $status HTTP status code
     */
    protected function renderBlade(string $view, array $data = [], int $status = 200): string
    {
        $renderer = BladeRenderer::getInstance();

        // Auto-register the calling plugin's view path
        $this->registerPluginViews($renderer);

        // Ensure Symfony's view_instance is set in context.
        // Blade templates that call get_partial() / get_component_slot() need it.
        $this->ensureViewInstance();

        // Merge common template data
        $data = array_merge([
            'sf_user' => $this->getUser(),
            'sf_request' => $this->getRequest(),
        ], $data);

        $html = $renderer->render($view, $data);

        $this->getResponse()->setStatusCode($status);
        $this->getResponse()->setContentType('text/html');

        return $this->renderText($html);
    }

    /**
     * Ensure Symfony's view_instance is set in the context.
     *
     * When Blade bypasses sfPHPView, Symfony helpers like get_component_slot()
     * and get_partial() may need view_instance to be present. This creates a
     * minimal sfPHPView and configures it so those helpers work correctly.
     */
    private function ensureViewInstance(): void
    {
        $context = $this->getContext();

        if ($context->has('view_instance')) {
            return;
        }

        try {
            $view = new \sfPHPView(
                $context,
                $this->getModuleName(),
                $this->getActionName(),
                ''
            );
            $view->configure();
        } catch (\Exception $e) {
            // Fallback: set a minimal view instance so get_component_slot doesn't crash
            // configure() may fail if view.yml doesn't exist for this module
        }
    }

    /**
     * Auto-detect and register the calling plugin's template directories.
     */
    private function registerPluginViews(BladeRenderer $renderer): void
    {
        $moduleName = $this->getModuleName();
        $dirs = $this->getContext()->getConfiguration()->getTemplateDirs($moduleName);

        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $renderer->addPath($dir);
            }
        }
    }
}
