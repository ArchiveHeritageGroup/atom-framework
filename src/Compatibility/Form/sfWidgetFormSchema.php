<?php

/**
 * Symfony 1.x Widget Form Schema & Formatter Compatibility Stubs.
 *
 * Covers: sfWidgetFormSchema, sfWidgetFormSchemaFormatter,
 * sfWidgetFormSchemaFormatterTable, sfWidgetFormSchemaFormatterList.
 */

// ── sfWidgetFormSchema ──────────────────────────────────────────────

if (!class_exists('sfWidgetFormSchema', false)) {
    class sfWidgetFormSchema extends sfWidgetForm implements \ArrayAccess
    {
        protected $widgets = [];
        protected $labels = [];
        protected $helps = [];
        protected $nameFormat = '%s';
        protected $formFormatterName = 'table';
        protected $formFormatters = [];
        protected $positions = [];
        protected $form = null;

        public function __construct($widgets = [], $options = [], $attributes = [], $labels = [], $helps = [])
        {
            parent::__construct($options, $attributes);

            foreach ($widgets as $name => $widget) {
                $this[$name] = $widget;
            }

            $this->labels = $labels;
            $this->helps = $helps;

            // Register default formatters
            $this->addFormFormatter('table', new sfWidgetFormSchemaFormatterTable($this));
            $this->addFormFormatter('list', new sfWidgetFormSchemaFormatterList($this));
        }

        public function setForm($form)
        {
            $this->form = $form;

            return $this;
        }

        public function getForm()
        {
            return $this->form;
        }

        public function setNameFormat($format)
        {
            $this->nameFormat = $format;

            return $this;
        }

        public function getNameFormat()
        {
            return $this->nameFormat;
        }

        public function generateName($name)
        {
            if ('%s' === $this->nameFormat) {
                return $name;
            }

            return str_replace('%s', $name, $this->nameFormat);
        }

        public function setFormFormatterName($name)
        {
            $this->formFormatterName = $name;

            return $this;
        }

        public function getFormFormatterName()
        {
            return $this->formFormatterName;
        }

        public function addFormFormatter($name, $formatter)
        {
            $this->formFormatters[$name] = $formatter;

            return $this;
        }

        public function getFormFormatter()
        {
            $name = $this->formFormatterName;

            if (isset($this->formFormatters[$name])) {
                return $this->formFormatters[$name];
            }

            // Fallback to table formatter
            if (!isset($this->formFormatters['table'])) {
                $this->formFormatters['table'] = new sfWidgetFormSchemaFormatterTable($this);
            }

            return $this->formFormatters['table'];
        }

        public function getFormFormatters()
        {
            return $this->formFormatters;
        }

        public function setLabel($name, $value = null)
        {
            if (null === $value) {
                // Called as setLabel('text') — set default label
                return parent::setLabel($name);
            }

            $this->labels[$name] = $value;

            return $this;
        }

        public function getLabel($name = null)
        {
            if (null === $name) {
                return parent::getLabel();
            }

            return $this->labels[$name] ?? null;
        }

        public function getLabels()
        {
            return $this->labels;
        }

        public function setLabels($labels)
        {
            $this->labels = $labels;

            return $this;
        }

        public function setHelp($name, $help)
        {
            $this->helps[$name] = $help;

            return $this;
        }

        public function getHelp($name)
        {
            return $this->helps[$name] ?? '';
        }

        public function getHelps()
        {
            return $this->helps;
        }

        public function setHelps($helps)
        {
            $this->helps = $helps;

            return $this;
        }

        public function getPositions()
        {
            if (empty($this->positions)) {
                return array_keys($this->widgets);
            }

            return $this->positions;
        }

        public function setPositions($positions)
        {
            $this->positions = $positions;

            return $this;
        }

        public function moveField($name, $action, $pivot = null)
        {
            // Simplified: just ensure the field exists
            return $this;
        }

        public function getFields()
        {
            return $this->widgets;
        }

        public function getWidget($name)
        {
            return $this->widgets[$name] ?? null;
        }

        public function render($name, $value = null, $attributes = [], $errors = [])
        {
            // Render all widgets using the formatter
            $formatter = $this->getFormFormatter();
            $html = '';

            foreach ($this->getPositions() as $fieldName) {
                if (!isset($this->widgets[$fieldName])) {
                    continue;
                }

                $widget = $this->widgets[$fieldName];
                if ($widget->isHidden()) {
                    continue;
                }

                $fieldName = $this->generateName($fieldName);
                $fieldValue = is_array($value) ? ($value[$fieldName] ?? null) : null;
                $fieldError = is_array($errors) ? ($errors[$fieldName] ?? []) : [];

                $rendered = $widget->render($fieldName, $fieldValue, $attributes, $fieldError);
                $label = $formatter->generateLabel($fieldName, []);
                $help = $this->getHelp($fieldName);
                $errorHtml = '';

                $html .= $formatter->formatRow($label, $rendered, $errorHtml, $help, '');
            }

            return $html;
        }

        public function needsMultipartForm()
        {
            foreach ($this->widgets as $widget) {
                if ($widget->needsMultipartForm()) {
                    return true;
                }
            }

            return false;
        }

        // ArrayAccess
        public function offsetExists(mixed $offset): bool
        {
            return isset($this->widgets[$offset]);
        }

        public function offsetGet(mixed $offset): mixed
        {
            return $this->widgets[$offset] ?? null;
        }

        public function offsetSet(mixed $offset, mixed $value): void
        {
            $this->widgets[$offset] = $value;
        }

        public function offsetUnset(mixed $offset): void
        {
            unset($this->widgets[$offset]);
        }
    }
}

// ── sfWidgetFormSchemaFormatter ──────────────────────────────────────

if (!class_exists('sfWidgetFormSchemaFormatter', false)) {
    class sfWidgetFormSchemaFormatter
    {
        protected $rowFormat = "<div class=\"mb-3\">\n  %label%\n  %field%\n  %error%\n  %help%\n  %hidden_fields%\n</div>\n";
        protected $formErrorListFormat = "<div class=\"alert alert-danger\" role=\"alert\">\n  %errors%\n</div>\n";
        protected $errorListFormatInARow = "<div class=\"invalid-feedback\">\n  %errors%\n</div>\n";
        protected $errorRowFormatInARow = "<span>%error%</span>\n";
        protected $namedErrorRowFormatInARow = "<span>%name%: %error%</span>\n";
        protected $helpFormat = "<div class=\"form-text\">\n  %help%\n</div>\n";
        protected $decoratorFormat = "%content%";
        protected $errorRowFormat = "%errors%";
        protected $widgetSchema;
        protected $form;

        public function __construct(sfWidgetFormSchema $widgetSchema = null)
        {
            $this->widgetSchema = $widgetSchema;
        }

        public function setWidgetSchema($widgetSchema)
        {
            $this->widgetSchema = $widgetSchema;
        }

        public function getWidgetSchema()
        {
            return $this->widgetSchema;
        }

        public function setForm($form)
        {
            $this->form = $form;

            return $this;
        }

        public function getForm()
        {
            return $this->form;
        }

        public function formatRow($label, $field, $errors = '', $help = '', $hiddenFields = '')
        {
            return strtr($this->rowFormat, [
                '%label%' => $label,
                '%field%' => $field,
                '%error%' => $errors,
                '%help%' => $help ? strtr($this->helpFormat, ['%help%' => $help]) : '',
                '%hidden_fields%' => $hiddenFields,
            ]);
        }

        public function generateLabel($name, $attributes = [])
        {
            $labelText = $this->generateLabelName($name);

            if (empty($labelText)) {
                return '';
            }

            $attrs = '';
            if (!empty($attributes)) {
                foreach ($attributes as $k => $v) {
                    $attrs .= ' ' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '"';
                }
            }

            $for = str_replace(['[', ']'], ['_', ''], $name);

            return '<label class="form-label" for="' . htmlspecialchars($for, ENT_QUOTES, 'UTF-8') . '"' . $attrs . '>' . $labelText . '</label>';
        }

        public function generateLabelName($name)
        {
            // Check for custom label
            if (null !== $this->widgetSchema) {
                $label = $this->widgetSchema->getLabel($name);
                if (null !== $label) {
                    return $label;
                }
            }

            // Convert field_name to Field name
            return ucfirst(str_replace(['_', '-'], ' ', $name));
        }

        public function formatErrorRow($errors)
        {
            if (empty($errors)) {
                return '';
            }

            return strtr($this->errorRowFormat, ['%errors%' => $errors]);
        }

        public function formatErrorsForRow($errors)
        {
            if (empty($errors)) {
                return '';
            }

            if (is_string($errors)) {
                return strtr($this->errorRowFormatInARow, ['%error%' => $errors]);
            }

            $html = '';
            if (is_array($errors)) {
                foreach ($errors as $error) {
                    $html .= strtr($this->errorRowFormatInARow, ['%error%' => $error]);
                }
            }

            return $html;
        }

        public function getDecoratorFormat()
        {
            return $this->decoratorFormat;
        }

        public function setDecoratorFormat($format)
        {
            $this->decoratorFormat = $format;

            return $this;
        }

        public function getRowFormat()
        {
            return $this->rowFormat;
        }

        public function setRowFormat($format)
        {
            $this->rowFormat = $format;

            return $this;
        }
    }
}

// ── sfWidgetFormSchemaFormatterTable ─────────────────────────────────

if (!class_exists('sfWidgetFormSchemaFormatterTable', false)) {
    class sfWidgetFormSchemaFormatterTable extends sfWidgetFormSchemaFormatter
    {
        protected $rowFormat = "<tr>\n  <th>%label%</th>\n  <td>%error%%field%%help%%hidden_fields%</td>\n</tr>\n";
        protected $errorRowFormat = "<tr><td colspan=\"2\">\n%errors%</td></tr>\n";
        protected $errorListFormatInARow = "<ul class=\"error_list\">\n%errors%</ul>\n";
        protected $errorRowFormatInARow = "<li>%error%</li>\n";
        protected $helpFormat = "<br />%help%";
        protected $decoratorFormat = "<table>\n  %content%\n</table>";
    }
}

// ── sfWidgetFormSchemaFormatterList ──────────────────────────────────

if (!class_exists('sfWidgetFormSchemaFormatterList', false)) {
    class sfWidgetFormSchemaFormatterList extends sfWidgetFormSchemaFormatter
    {
        protected $rowFormat = "<li>\n  %label%\n  %error%%field%%help%%hidden_fields%\n</li>\n";
        protected $errorRowFormat = "<li>\n%errors%</li>\n";
        protected $errorListFormatInARow = "<ul class=\"error_list\">\n%errors%</ul>\n";
        protected $errorRowFormatInARow = "<li>%error%</li>\n";
        protected $helpFormat = "<p class=\"help\">%help%</p>";
        protected $decoratorFormat = "<ul>\n  %content%\n</ul>";
    }
}
