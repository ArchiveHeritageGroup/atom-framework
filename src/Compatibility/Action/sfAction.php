<?php

/**
 * sfAction — Compatibility shim.
 *
 * Faithful port of vendor/symfony/lib/action/sfAction.class.php.
 * Extends sfComponent with forwarding, redirecting, template management, and security.
 */

if (!class_exists('sfAction', false)) {
    class sfAction extends sfComponent
    {
        protected $security = [];

        // ── Forwarding ───────────────────────────────────────────────────

        /**
         * Forward to another action.
         *
         * @param  string $module
         * @param  string $action
         *
         * @throws sfStopException Always thrown to stop execution
         */
        public function forward($module, $action)
        {
            $this->getController()->forward($module, $action);

            throw new \sfStopException();
        }

        /**
         * Forward to a 404 error page.
         *
         * @param  string $message
         *
         * @throws sfError404Exception
         */
        public function forward404($message = null)
        {
            throw new \sfError404Exception(
                $this->getContext()->getController()->genUrl(
                    '@sf_error404_module/' . \sfConfig::get('sf_error_404_action', 'error404')
                ) . ' ' . ($message ?? '')
            );
        }

        /**
         * Forward to 404 if condition is true.
         *
         * @param  bool   $condition
         * @param  string $message
         */
        public function forward404If($condition, $message = null)
        {
            if ($condition) {
                $this->forward404($message);
            }
        }

        /**
         * Forward to 404 unless condition is true.
         *
         * @param  bool   $condition
         * @param  string $message
         */
        public function forward404Unless($condition, $message = null)
        {
            if (!$condition) {
                $this->forward404($message);
            }
        }

        // ── Redirecting ──────────────────────────────────────────────────

        /**
         * Redirect to a URL.
         *
         * @param  string $url
         * @param  int    $statusCode
         *
         * @throws sfStopException Always thrown to stop execution
         */
        public function redirect($url, $statusCode = 302)
        {
            // Support module/action or named route format
            if (is_string($url) && !preg_match('#^https?://#', $url) && !str_starts_with($url, '/')) {
                $url = $this->generateUrl($url);
            }

            $this->getController()->redirect($url, 0, $statusCode);

            throw new \sfStopException();
        }

        /**
         * Redirect to a route.
         *
         * @param  string $route
         * @param  array  $params
         * @param  int    $statusCode
         */
        public function redirectToRoute($route, $params = [], $statusCode = 302)
        {
            $url = $this->generateUrl($route, $params);
            $this->redirect($url, $statusCode);
        }

        // ── Template Management ──────────────────────────────────────────

        /**
         * Set the template for this action.
         *
         * @param  string $name       Template name (without suffix)
         * @param  string $module     Module name (current module if null)
         */
        public function setTemplate($name, $module = null)
        {
            if (null !== $module) {
                $name = \sfConfig::get('sf_app_dir')
                    . '/modules/' . $module . '/templates/' . $name;
            }

            \sfConfig::set(
                'sf_' . strtolower($this->getModuleName()) . '_' . strtolower($this->getActionName()) . '_template',
                $name
            );
        }

        /**
         * Get the template for this action.
         *
         * @return string|null
         */
        public function getTemplate()
        {
            return \sfConfig::get(
                'sf_' . strtolower($this->getModuleName()) . '_' . strtolower($this->getActionName()) . '_template'
            );
        }

        /**
         * Set the layout.
         *
         * @param  string|false $name  Layout name or false to disable
         */
        public function setLayout($name)
        {
            if (false === $name) {
                \sfConfig::set(
                    'sf_' . strtolower($this->getModuleName()) . '_' . strtolower($this->getActionName()) . '_layout',
                    false
                );
            } else {
                \sfConfig::set(
                    'sf_' . strtolower($this->getModuleName()) . '_' . strtolower($this->getActionName()) . '_layout',
                    $name
                );
            }
        }

        /**
         * Get the layout.
         *
         * @return string|false|null
         */
        public function getLayout()
        {
            return \sfConfig::get(
                'sf_' . strtolower($this->getModuleName()) . '_' . strtolower($this->getActionName()) . '_layout'
            );
        }

        // ── Route Access ─────────────────────────────────────────────────

        /**
         * Get the matched route object.
         *
         * @return sfRoute|null
         */
        public function getRoute()
        {
            return $this->getRequest()->getAttribute('sf_route');
        }

        // ── Security ─────────────────────────────────────────────────────

        /**
         * Get the full security configuration.
         *
         * @return array
         */
        public function getSecurityConfiguration()
        {
            return $this->security;
        }

        /**
         * Set the security configuration.
         *
         * @param  array $security
         */
        public function setSecurityConfiguration($security)
        {
            $this->security = $security;
        }

        /**
         * Check if the action is secure.
         *
         * @return bool
         */
        public function isSecure()
        {
            $actionName = strtolower($this->getActionName());

            if (isset($this->security[$actionName]['is_secure'])) {
                return $this->security[$actionName]['is_secure'];
            }

            return $this->security['all']['is_secure'] ?? false;
        }

        /**
         * Get the credential required for this action.
         *
         * @return mixed
         */
        public function getCredential()
        {
            $actionName = strtolower($this->getActionName());

            if (isset($this->security[$actionName]['credentials'])) {
                return $this->security[$actionName]['credentials'];
            }

            return $this->security['all']['credentials'] ?? null;
        }

        // ── View Class ───────────────────────────────────────────────────

        /**
         * Set the view class for this action.
         *
         * @param  string $module
         * @param  string $action
         * @param  string $class
         */
        public function setViewClass($module, $action, $class)
        {
            \sfConfig::set(
                'mod_' . strtolower($module) . '_' . strtolower($action) . '_view_class',
                $class
            );
        }

        // ── Lifecycle Hooks ──────────────────────────────────────────────

        public function preExecute()
        {
        }

        public function postExecute()
        {
        }

        /**
         * Validate the request — returns true by default.
         *
         * @return bool
         */
        public function validate()
        {
            return true;
        }

        /**
         * Handle validation error — called when validate() returns false.
         *
         * @return string  sfView constant
         */
        public function handleError()
        {
            return \sfView::ERROR;
        }

        /**
         * Get default view — returned when execute returns sfView::INPUT.
         *
         * @return string
         */
        public function getDefaultView()
        {
            return \sfView::INPUT;
        }
    }
}
