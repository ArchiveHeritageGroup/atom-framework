<?php

/**
 * sfComponent — Compatibility shim.
 *
 * Faithful port of vendor/symfony/lib/action/sfComponent.class.php.
 * Base class for all components. Manages template variables, context, request/response.
 */

if (!class_exists('sfComponent', false)) {
    class sfComponent
    {
        protected $moduleName = '';
        protected $actionName = '';
        protected $context = null;
        protected $dispatcher = null;
        protected $request = null;
        protected $response = null;
        protected $varHolder = null;
        protected $requestParameterHolder = null;

        /**
         * Initialize the component.
         *
         * @param  sfContext $context
         * @param  string   $moduleName
         * @param  string   $actionName
         */
        public function initialize($context, $moduleName, $actionName)
        {
            $this->moduleName = $moduleName;
            $this->actionName = $actionName;
            $this->context = $context;
            $this->dispatcher = $context->getEventDispatcher();
            $this->request = $context->getRequest();
            $this->response = $context->getResponse();

            $this->varHolder = new \sfParameterHolder();
            $this->requestParameterHolder = $this->request->getParameterHolder();
        }

        // ── Context & Service Access ─────────────────────────────────────

        /**
         * @return sfContext
         */
        public function getContext()
        {
            return $this->context;
        }

        /**
         * @return sfWebRequest
         */
        public function getRequest()
        {
            return $this->request;
        }

        /**
         * @return sfWebResponse
         */
        public function getResponse()
        {
            return $this->response;
        }

        /**
         * @return sfController
         */
        public function getController()
        {
            return $this->context->getController();
        }

        /**
         * @return sfUser
         */
        public function getUser()
        {
            return $this->context->getUser();
        }

        /**
         * @return string
         */
        public function getModuleName()
        {
            return $this->moduleName;
        }

        /**
         * @return string
         */
        public function getActionName()
        {
            return $this->actionName;
        }

        // ── Template Variable Management ─────────────────────────────────

        /**
         * @return sfParameterHolder
         */
        public function getVarHolder()
        {
            return $this->varHolder;
        }

        /**
         * @param  string $name
         *
         * @return mixed
         */
        public function getVar($name)
        {
            return $this->varHolder->get($name);
        }

        /**
         * @param  string $name
         * @param  mixed  $value
         * @param  bool   $safe   Mark value as safe for output escaping
         */
        public function setVar($name, $value, $safe = false)
        {
            $this->varHolder->set($name, $value);
        }

        /**
         * Check if a template variable exists.
         *
         * @param  string $name
         *
         * @return bool
         */
        public function hasVar($name)
        {
            return $this->varHolder->has($name);
        }

        // ── Magic Property Access (delegates to varHolder) ───────────────

        public function &__get($name)
        {
            return $this->varHolder->get($name);
        }

        public function __set($name, $value)
        {
            $this->varHolder->set($name, $value);
        }

        public function __isset($name)
        {
            return $this->varHolder->has($name);
        }

        public function __unset($name)
        {
            $this->varHolder->remove($name);
        }

        // ── Request Parameter Shortcuts ──────────────────────────────────

        /**
         * @param  string $name
         * @param  mixed  $default
         *
         * @return mixed
         */
        public function getRequestParameter($name, $default = null)
        {
            return $this->requestParameterHolder->get($name, $default);
        }

        /**
         * @param  string $name
         *
         * @return bool
         */
        public function hasRequestParameter($name)
        {
            return $this->requestParameterHolder->has($name);
        }

        // ── Rendering Helpers ────────────────────────────────────────────

        /**
         * Render inline text (returns sfView::NONE).
         *
         * @param  string $text
         *
         * @return string sfView::NONE
         */
        public function renderText($text)
        {
            $this->getResponse()->setContent(
                $this->getResponse()->getContent() . $text
            );

            return \sfView::NONE;
        }

        /**
         * Get a partial template as a string.
         *
         * @param  string $templateName  module/partial or just partial
         * @param  array  $vars
         *
         * @return string
         */
        public function getPartial($templateName, $vars = [])
        {
            if (function_exists('get_partial')) {
                return get_partial($templateName, $vars);
            }

            return '';
        }

        /**
         * Include a partial template.
         */
        public function renderPartial($templateName, $vars = [])
        {
            echo $this->getPartial($templateName, $vars);
        }

        // ── URL Generation ───────────────────────────────────────────────

        /**
         * Generate a URL.
         *
         * @param  string $route     Route name or module/action
         * @param  array  $params    Parameters
         * @param  bool   $absolute  Generate absolute URL
         *
         * @return string
         */
        public function generateUrl($route, $params = [], $absolute = false)
        {
            if (function_exists('url_for')) {
                return url_for($route, $absolute);
            }

            return $route;
        }

        // ── Logging ──────────────────────────────────────────────────────

        /**
         * Log a message via event dispatcher.
         *
         * @param  string $message
         * @param  string $priority  'info', 'warning', 'error', etc.
         */
        public function logMessage($message, $priority = 'info')
        {
            if ($this->dispatcher) {
                $this->dispatcher->notify(new \sfEvent(
                    $this,
                    'application.log',
                    ['priority' => $priority, $message]
                ));
            }
        }

        // ── Lifecycle Hooks ──────────────────────────────────────────────

        public function preExecute()
        {
        }

        public function postExecute()
        {
        }

        /**
         * Execute the component.
         *
         * @param  sfWebRequest $request
         *
         * @return string  View name
         */
        public function execute($request)
        {
            return \sfView::INPUT;
        }
    }
}
