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
    protected function renderJson(array $data, int $status = 200): string
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
    protected function renderJsonSuccess($data = null, string $message = 'Success'): string
    {
        return $this->renderJson(ResponseHelper::success($data, $message));
    }

    /**
     * Render an error JSON response.
     */
    protected function renderJsonError(string $message, int $code = 400): string
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

    // ─── Blade Template Rendering ─────────────────────────────────

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
