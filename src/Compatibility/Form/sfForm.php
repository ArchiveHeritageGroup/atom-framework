<?php

/**
 * Symfony 1.x sfForm Compatibility Stub.
 *
 * Provides the sfForm API surface used by AHG plugins without Symfony.
 * Supports: widget/validator setup, bind/validate, value extraction,
 * CSRF protection, hidden field rendering, ArrayAccess for field proxies.
 */

if (!class_exists('sfForm', false)) {
    class sfForm implements \ArrayAccess
    {
        protected $widgetSchema;
        protected $validatorSchema;
        protected $defaults = [];
        protected $options = [];
        protected $taintedValues = [];
        protected $taintedFiles = [];
        protected $values = [];
        protected $isBound = false;
        protected $isValid = null;
        protected $errorSchema;
        protected $CSRFSecret;
        protected $CSRFFieldName = '_csrf_token';
        protected $localCSRFSecret;

        public function __construct($defaults = [], $options = [], $CSRFSecret = null)
        {
            $this->defaults = $defaults;
            $this->options = $options;
            $this->CSRFSecret = $CSRFSecret;

            $this->widgetSchema = new sfWidgetFormSchema();
            $this->widgetSchema->setForm($this);
            $this->validatorSchema = new sfValidatorSchema();
            $this->errorSchema = new sfValidatorErrorSchema($this->validatorSchema);

            $this->setup();
            $this->configure();

            // Add CSRF field if a secret was provided
            if (null !== $this->CSRFSecret && false !== $this->CSRFSecret) {
                $this->addCSRFProtection($this->CSRFSecret);
            }
        }

        protected function setup()
        {
            // Override in subclasses
        }

        protected function configure()
        {
            // Override in subclasses — called by form classes to set up widgets/validators
        }

        // ── Widget Management ──────────────────────────────────────

        public function setWidgets(array $widgets)
        {
            foreach ($widgets as $name => $widget) {
                $this->setWidget($name, $widget);
            }
        }

        public function setWidget($name, $widget)
        {
            $this->widgetSchema[$name] = $widget;
        }

        public function getWidget($name)
        {
            return $this->widgetSchema[$name] ?? null;
        }

        public function getWidgetSchema()
        {
            return $this->widgetSchema;
        }

        public function setWidgetSchema($schema)
        {
            $this->widgetSchema = $schema;
            $this->widgetSchema->setForm($this);
        }

        // ── Validator Management ───────────────────────────────────

        public function setValidators(array $validators)
        {
            foreach ($validators as $name => $validator) {
                $this->setValidator($name, $validator);
            }
        }

        public function setValidator($name, $validator)
        {
            $this->validatorSchema[$name] = $validator;
        }

        public function getValidator($name)
        {
            return $this->validatorSchema[$name] ?? null;
        }

        public function getValidatorSchema()
        {
            return $this->validatorSchema;
        }

        public function setValidatorSchema($schema)
        {
            $this->validatorSchema = $schema;
        }

        // ── Defaults ───────────────────────────────────────────────

        public function setDefault($name, $value)
        {
            $this->defaults[$name] = $value;
        }

        public function getDefault($name)
        {
            return $this->defaults[$name] ?? null;
        }

        public function setDefaults($defaults)
        {
            $this->defaults = array_merge($this->defaults, $defaults);
        }

        public function getDefaults()
        {
            return $this->defaults;
        }

        // ── Binding & Validation ───────────────────────────────────

        public function bind(array $taintedValues = [], array $taintedFiles = [])
        {
            $this->taintedValues = $taintedValues;
            $this->taintedFiles = $taintedFiles;
            $this->isBound = true;
            $this->isValid = null;
            $this->values = [];
            $this->errorSchema = new sfValidatorErrorSchema($this->validatorSchema);

            try {
                $this->values = $this->validatorSchema->clean(
                    self::deepArrayUnion($this->taintedValues, self::convertFileInformation($this->taintedFiles))
                );
                $this->isValid = true;
            } catch (sfValidatorErrorSchema $e) {
                $this->values = [];
                $this->errorSchema = $e;
                $this->isValid = false;
            } catch (sfValidatorError $e) {
                $this->values = [];
                $this->errorSchema->addError($e);
                $this->isValid = false;
            }
        }

        public function isValid()
        {
            if (!$this->isBound) {
                return false;
            }

            if (null === $this->isValid) {
                $this->isValid = (0 === $this->errorSchema->count());
            }

            return $this->isValid;
        }

        public function isBound()
        {
            return $this->isBound;
        }

        public function hasErrors()
        {
            return $this->errorSchema->count() > 0;
        }

        // ── Value Access ───────────────────────────────────────────

        public function getValues()
        {
            return $this->values;
        }

        public function getValue($name)
        {
            return $this->values[$name] ?? null;
        }

        public function getTaintedValues()
        {
            return $this->taintedValues;
        }

        // ── Error Access ───────────────────────────────────────────

        public function getErrorSchema()
        {
            return $this->errorSchema;
        }

        public function getGlobalErrors()
        {
            return $this->errorSchema->getGlobalErrors();
        }

        // ── CSRF ───────────────────────────────────────────────────

        public function addCSRFProtection($secret)
        {
            if (false === $secret || null === $secret) {
                return;
            }

            $this->localCSRFSecret = $secret;
            $this->setWidget($this->CSRFFieldName, new sfWidgetFormInputHidden());
            $this->setValidator($this->CSRFFieldName, new sfValidatorPass());
            $this->setDefault($this->CSRFFieldName, $this->getCSRFToken($secret));
        }

        public function getCSRFToken($secret = null)
        {
            // Generate a simple CSRF token
            $secret = $secret ?: $this->localCSRFSecret ?: session_id();

            return md5($secret . session_id() . get_class($this));
        }

        public function getCSRFFieldName()
        {
            return $this->CSRFFieldName;
        }

        public function isCSRFProtected()
        {
            return null !== $this->localCSRFSecret;
        }

        public function disableCSRFProtection()
        {
            $this->localCSRFSecret = null;
            if (isset($this->widgetSchema[$this->CSRFFieldName])) {
                unset($this->widgetSchema[$this->CSRFFieldName]);
            }
            if (isset($this->validatorSchema[$this->CSRFFieldName])) {
                unset($this->validatorSchema[$this->CSRFFieldName]);
            }
        }

        // ── Rendering ──────────────────────────────────────────────

        public function renderHiddenFields($includeCSRF = true)
        {
            $html = '';

            foreach ($this->widgetSchema->getFields() as $name => $widget) {
                if ($widget instanceof sfWidgetFormInputHidden || ($widget instanceof sfWidgetForm && $widget->isHidden())) {
                    $value = $this->defaults[$name] ?? '';
                    if ($this->isBound && isset($this->taintedValues[$name])) {
                        $value = $this->taintedValues[$name];
                    }
                    $html .= $widget->render(
                        $this->widgetSchema->generateName($name),
                        $value
                    ) . "\n";
                }
            }

            return $html;
        }

        public function renderGlobalErrors()
        {
            $errors = $this->errorSchema->getGlobalErrors();
            if (empty($errors)) {
                return '';
            }

            $html = '<div class="alert alert-danger" role="alert"><ul>';
            foreach ($errors as $error) {
                $msg = $error instanceof \Exception ? $error->getMessage() : (string) $error;
                $html .= '<li>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $html .= '</ul></div>';

            return $html;
        }

        public function render($attributes = [])
        {
            return $this->widgetSchema->render(
                $this->widgetSchema->getNameFormat(),
                $this->getDefaults(),
                $attributes
            );
        }

        // ── Options ────────────────────────────────────────────────

        public function getOption($name)
        {
            return $this->options[$name] ?? null;
        }

        public function setOption($name, $value)
        {
            $this->options[$name] = $value;
        }

        // ── ArrayAccess (returns sfFormField proxies) ──────────────

        public function offsetExists(mixed $offset): bool
        {
            return isset($this->widgetSchema[$offset]);
        }

        public function offsetGet(mixed $offset): mixed
        {
            if (!isset($this->widgetSchema[$offset])) {
                return null;
            }

            $widget = $this->widgetSchema[$offset];
            $value = $this->defaults[$offset] ?? null;
            if ($this->isBound && array_key_exists($offset, $this->taintedValues)) {
                $value = $this->taintedValues[$offset];
            }

            $error = null;
            if (isset($this->errorSchema[$offset])) {
                $error = $this->errorSchema[$offset];
            }

            return new sfFormField($widget, $this->widgetSchema, $offset, $value, $error, $this);
        }

        public function offsetSet(mixed $offset, mixed $value): void
        {
            // Not supported — use setWidget()
        }

        public function offsetUnset(mixed $offset): void
        {
            unset($this->widgetSchema[$offset]);
            unset($this->validatorSchema[$offset]);
        }

        // ── Magic property access (legacy $form->fieldName) ───────

        public function __get($name)
        {
            if ('widgetSchema' === $name) {
                return $this->widgetSchema;
            }
            if ('validatorSchema' === $name) {
                return $this->validatorSchema;
            }

            // Return sfFormField proxy (same as ArrayAccess)
            return $this->offsetGet($name);
        }

        // ── Helpers ────────────────────────────────────────────────

        public function needsMultipartForm()
        {
            return $this->widgetSchema->needsMultipartForm();
        }

        public function getFormFieldSchema()
        {
            return $this->widgetSchema;
        }

        /**
         * Deep merge two arrays (for combining POST values with file uploads).
         */
        protected static function deepArrayUnion($a, $b)
        {
            $result = $a;
            foreach ($b as $key => $value) {
                if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                    $result[$key] = self::deepArrayUnion($result[$key], $value);
                } else {
                    $result[$key] = $value;
                }
            }

            return $result;
        }

        /**
         * Convert PHP file upload array to normalised per-file arrays.
         */
        protected static function convertFileInformation($taintedFiles)
        {
            if (empty($taintedFiles)) {
                return [];
            }

            $files = [];
            foreach ($taintedFiles as $key => $data) {
                if (is_array($data) && isset($data['tmp_name'])) {
                    $files[$key] = $data;
                }
            }

            return $files;
        }
    }
}
