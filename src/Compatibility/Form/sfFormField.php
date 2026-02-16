<?php

/**
 * Symfony 1.x sfFormField Compatibility Stub.
 *
 * Field proxy returned by $form['fieldName'] or $form->fieldName.
 * Templates use: render(), renderLabel(), renderError(), renderHelp(),
 * renderHiddenFields(), renderRow(), and __toString().
 */

if (!class_exists('sfFormField', false)) {
    class sfFormField
    {
        protected $widget;
        protected $widgetSchema;
        protected $name;
        protected $value;
        protected $error;
        protected $parent;

        public function __construct($widget, $widgetSchema, $name, $value = null, $error = null, $parent = null)
        {
            $this->widget = $widget;
            $this->widgetSchema = $widgetSchema;
            $this->name = $name;
            $this->value = $value;
            $this->error = $error;
            $this->parent = $parent;
        }

        /**
         * Render the widget HTML.
         */
        public function render($attributes = [])
        {
            $renderedName = $this->widgetSchema
                ? $this->widgetSchema->generateName($this->name)
                : $this->name;

            return $this->widget->render($renderedName, $this->value, $attributes);
        }

        /**
         * Render the label for this field.
         */
        public function renderLabel($attributes = [], $label = null)
        {
            if (null === $label && null !== $this->widgetSchema) {
                $label = $this->widgetSchema->getLabel($this->name);
            }

            if (null === $label) {
                $label = ucfirst(str_replace(['_', '-'], ' ', $this->name));
            }

            $for = str_replace(['[', ']'], ['_', ''], $this->name);

            $attrStr = '';
            if (is_array($attributes)) {
                foreach ($attributes as $k => $v) {
                    $attrStr .= ' ' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '"';
                }
            }

            return '<label class="form-label" for="' . htmlspecialchars($for, ENT_QUOTES, 'UTF-8') . '"' . $attrStr . '>'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>';
        }

        /**
         * Render validation errors for this field.
         */
        public function renderError()
        {
            if (empty($this->error)) {
                return '';
            }

            $html = '<div class="invalid-feedback d-block">';

            if (is_array($this->error)) {
                foreach ($this->error as $err) {
                    $msg = $err instanceof \Exception ? $err->getMessage() : (string) $err;
                    $html .= '<span>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</span> ';
                }
            } elseif ($this->error instanceof \Exception) {
                $html .= '<span>' . htmlspecialchars($this->error->getMessage(), ENT_QUOTES, 'UTF-8') . '</span>';
            } else {
                $html .= '<span>' . htmlspecialchars((string) $this->error, ENT_QUOTES, 'UTF-8') . '</span>';
            }

            $html .= '</div>';

            return $html;
        }

        /**
         * Render help text for this field.
         */
        public function renderHelp()
        {
            if (null === $this->widgetSchema) {
                return '';
            }

            $help = $this->widgetSchema->getHelp($this->name);

            if (empty($help)) {
                return '';
            }

            return '<div class="form-text">' . htmlspecialchars($help, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        /**
         * Render hidden fields associated with this field.
         */
        public function renderHiddenFields()
        {
            // For individual fields, this is usually empty
            return '';
        }

        /**
         * Render a full form row (label + field + errors + help).
         */
        public function renderRow($attributes = [], $label = null)
        {
            return '<div class="mb-3">'
                . $this->renderLabel([], $label)
                . $this->render($attributes)
                . $this->renderError()
                . $this->renderHelp()
                . '</div>';
        }

        /**
         * String representation renders the widget.
         */
        public function __toString()
        {
            try {
                return $this->render();
            } catch (\Throwable $e) {
                return '';
            }
        }

        /**
         * Check if the field has an error.
         */
        public function hasError()
        {
            return !empty($this->error);
        }

        /**
         * Get the error object.
         */
        public function getError()
        {
            return $this->error;
        }

        /**
         * Get the widget.
         */
        public function getWidget()
        {
            return $this->widget;
        }

        /**
         * Get the field name.
         */
        public function getName()
        {
            return $this->name;
        }

        /**
         * Get the field value.
         */
        public function getValue()
        {
            return $this->value;
        }

        /**
         * Check if this field is hidden.
         */
        public function isHidden()
        {
            return $this->widget instanceof sfWidgetFormInputHidden
                || ($this->widget instanceof sfWidgetForm && $this->widget->isHidden());
        }

        /**
         * Get the parent form.
         */
        public function getParent()
        {
            return $this->parent;
        }
    }
}
