<?php

/**
 * Symfony 1.x Widget Compatibility Stubs.
 *
 * Provides the sfWidgetForm* API surface used by AHG plugins without
 * requiring Symfony. Each class is guarded with class_exists().
 *
 * Covers: sfWidget, sfWidgetForm, sfWidgetFormInput, sfWidgetFormInputText,
 * sfWidgetFormInputHidden, sfWidgetFormInputPassword, sfWidgetFormInputCheckbox,
 * sfWidgetFormInputFile, sfWidgetFormSelect, sfWidgetFormTextarea,
 * sfWidgetFormChoice, sfWidgetFormSelectRadio, sfWidgetFormI18nChoiceLanguage.
 */

// ── sfWidget (base) ─────────────────────────────────────────────────

if (!class_exists('sfWidget', false)) {
    class sfWidget
    {
        protected $options = [];
        protected $attributes = [];
        protected $requiredOptions = [];

        public function __construct($options = [], $attributes = [])
        {
            $this->configure($options, $attributes);
            $this->options = array_merge($this->options, $options);
            $this->attributes = array_merge($this->attributes, $attributes);
        }

        protected function configure($options = [], $attributes = [])
        {
            // Override in subclasses
        }

        public function render($name, $value = null, $attributes = [], $errors = [])
        {
            return '';
        }

        public function getOption($name)
        {
            return $this->options[$name] ?? null;
        }

        public function setOption($name, $value)
        {
            $this->options[$name] = $value;

            return $this;
        }

        public function hasOption($name)
        {
            return array_key_exists($name, $this->options);
        }

        public function getOptions()
        {
            return $this->options;
        }

        public function addOption($name, $default = null)
        {
            if (!array_key_exists($name, $this->options)) {
                $this->options[$name] = $default;
            }
        }

        public function addRequiredOption($name)
        {
            $this->requiredOptions[] = $name;
        }

        public function getAttribute($name)
        {
            return $this->attributes[$name] ?? null;
        }

        public function setAttribute($name, $value)
        {
            $this->attributes[$name] = $value;

            return $this;
        }

        public function getAttributes()
        {
            return $this->attributes;
        }

        public function setAttributes($attributes)
        {
            $this->attributes = $attributes;

            return $this;
        }

        public function getLabel()
        {
            return $this->getOption('label');
        }

        public function setLabel($label)
        {
            $this->setOption('label', $label);

            return $this;
        }

        /**
         * Render an HTML tag.
         */
        public function renderTag($tag, $attributes = [])
        {
            if (empty($tag)) {
                return '';
            }

            return '<' . $tag . $this->attributesToHtml($attributes) . ' />';
        }

        /**
         * Render an HTML content tag (with opening and closing tags).
         */
        public function renderContentTag($tag, $content = '', $attributes = [])
        {
            if (empty($tag)) {
                return '';
            }

            return '<' . $tag . $this->attributesToHtml($attributes) . '>' . $content . '</' . $tag . '>';
        }

        /**
         * Convert an array of attributes to HTML attribute string.
         */
        public function attributesToHtml($attributes)
        {
            $html = '';
            foreach ($attributes as $key => $value) {
                if (null === $value || false === $value) {
                    continue;
                }
                if (true === $value) {
                    $html .= ' ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                } else {
                    $html .= ' ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
                }
            }

            return $html;
        }

        /**
         * Translate a string (passthrough — real translation handled elsewhere).
         */
        public function translate($text, $args = [])
        {
            if (function_exists('__')) {
                return __($text, $args);
            }

            return strtr($text, $args);
        }

        public function needsMultipartForm()
        {
            return $this->getOption('needs_multipart') ?? false;
        }

        public function getJavaScripts()
        {
            return [];
        }

        public function getStylesheets()
        {
            return [];
        }
    }
}

// ── sfWidgetForm ────────────────────────────────────────────────────

if (!class_exists('sfWidgetForm', false)) {
    class sfWidgetForm extends sfWidget
    {
        protected function configure($options = [], $attributes = [])
        {
            parent::configure($options, $attributes);
            $this->addOption('label', null);
            $this->addOption('id_format', '%s');
            $this->addOption('is_hidden', false);
            $this->addOption('needs_multipart', false);
            $this->addOption('default', null);
        }

        public function render($name, $value = null, $attributes = [], $errors = [])
        {
            return $this->renderTag('input', array_merge([
                'type' => 'text',
                'name' => $name,
                'value' => $value,
            ], $this->attributes, $attributes));
        }

        public function isHidden()
        {
            return (bool) $this->getOption('is_hidden');
        }

        public function generateId($name, $value = null)
        {
            $id = str_replace(['[', ']'], ['_', ''], $name);

            return preg_replace('/[^a-zA-Z0-9_-]/', '_', $id);
        }
    }
}

// ── sfWidgetFormInput ───────────────────────────────────────────────

if (!class_exists('sfWidgetFormInput', false)) {
    class sfWidgetFormInput extends sfWidgetForm
    {
        protected function configure($options = [], $attributes = [])
        {
            parent::configure($options, $attributes);
            $this->addOption('type', 'text');
        }

        public function render($name, $value = null, $attributes = [], $errors = [])
        {
            $mergedAttrs = array_merge($this->attributes, $attributes);
            if (!isset($mergedAttrs['class'])) {
                $mergedAttrs['class'] = 'form-control';
            }

            return $this->renderTag('input', array_merge([
                'type' => $this->getOption('type') ?? 'text',
                'name' => $name,
                'value' => $value,
                'id' => $this->generateId($name),
            ], $mergedAttrs));
        }
    }
}

// ── sfWidgetFormInputText ───────────────────────────────────────────

if (!class_exists('sfWidgetFormInputText', false)) {
    class sfWidgetFormInputText extends sfWidgetFormInput
    {
    }
}

// ── sfWidgetFormInputHidden ─────────────────────────────────────────

if (!class_exists('sfWidgetFormInputHidden', false)) {
    class sfWidgetFormInputHidden extends sfWidgetForm
    {
        protected function configure($options = [], $attributes = [])
        {
            parent::configure($options, $attributes);
            $this->setOption('is_hidden', true);
        }

        public function render($name, $value = null, $attributes = [], $errors = [])
        {
            return $this->renderTag('input', array_merge([
                'type' => 'hidden',
                'name' => $name,
                'value' => $value,
                'id' => $this->generateId($name),
            ], $this->attributes, $attributes));
        }
    }
}

// ── sfWidgetFormInputPassword ───────────────────────────────────────

if (!class_exists('sfWidgetFormInputPassword', false)) {
    class sfWidgetFormInputPassword extends sfWidgetFormInput
    {
        protected function configure($options = [], $attributes = [])
        {
            parent::configure($options, $attributes);
            $this->setOption('type', 'password');
        }

        public function render($name, $value = null, $attributes = [], $errors = [])
        {
            // Never pre-fill password fields
            return parent::render($name, '', $attributes, $errors);
        }
    }
}

// ── sfWidgetFormInputCheckbox ───────────────────────────────────────

if (!class_exists('sfWidgetFormInputCheckbox', false)) {
    class sfWidgetFormInputCheckbox extends sfWidgetForm
    {
        protected function configure($options = [], $attributes = [])
        {
            parent::configure($options, $attributes);
            $this->addOption('value_attribute_value', '1');
        }

        public function render($name, $value = null, $attributes = [], $errors = [])
        {
            $mergedAttrs = array_merge($this->attributes, $attributes);
            if (!isset($mergedAttrs['class'])) {
                $mergedAttrs['class'] = 'form-check-input';
            }

            $attrs = array_merge([
                'type' => 'checkbox',
                'name' => $name,
                'value' => $this->getOption('value_attribute_value'),
                'id' => $this->generateId($name),
            ], $mergedAttrs);

            if ($value && $value == $this->getOption('value_attribute_value')) {
                $attrs['checked'] = true;
            }

            return $this->renderTag('input', $attrs);
        }
    }
}

// ── sfWidgetFormInputFile ───────────────────────────────────────────

if (!class_exists('sfWidgetFormInputFile', false)) {
    class sfWidgetFormInputFile extends sfWidgetForm
    {
        protected function configure($options = [], $attributes = [])
        {
            parent::configure($options, $attributes);
            $this->setOption('needs_multipart', true);
        }

        public function render($name, $value = null, $attributes = [], $errors = [])
        {
            $mergedAttrs = array_merge($this->attributes, $attributes);
            if (!isset($mergedAttrs['class'])) {
                $mergedAttrs['class'] = 'form-control';
            }

            return $this->renderTag('input', array_merge([
                'type' => 'file',
                'name' => $name,
                'id' => $this->generateId($name),
            ], $mergedAttrs));
        }
    }
}

// ── sfWidgetFormSelect ──────────────────────────────────────────────

if (!class_exists('sfWidgetFormSelect', false)) {
    class sfWidgetFormSelect extends sfWidgetForm
    {
        protected function configure($options = [], $attributes = [])
        {
            parent::configure($options, $attributes);
            $this->addOption('choices', []);
            $this->addOption('multiple', false);
        }

        public function render($name, $value = null, $attributes = [], $errors = [])
        {
            $mergedAttrs = array_merge($this->attributes, $attributes);
            if (!isset($mergedAttrs['class'])) {
                $mergedAttrs['class'] = 'form-select';
            }

            if ($this->getOption('multiple')) {
                $mergedAttrs['multiple'] = true;
                if (']' !== substr($name, -1)) {
                    $name .= '[]';
                }
            }

            $selectAttrs = array_merge([
                'name' => $name,
                'id' => $this->generateId($name),
            ], $mergedAttrs);

            $choices = $this->getOption('choices');
            if (is_callable($choices)) {
                $choices = call_user_func($choices);
            }

            $optionsHtml = '';
            foreach ((array) $choices as $optValue => $optLabel) {
                $selected = '';
                if (is_array($value) ? in_array($optValue, $value) : (string) $optValue === (string) $value) {
                    $selected = ' selected="selected"';
                }
                $optionsHtml .= '<option value="' . htmlspecialchars((string) $optValue, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>'
                    . htmlspecialchars((string) $optLabel, ENT_QUOTES, 'UTF-8') . '</option>' . "\n";
            }

            return '<select' . $this->attributesToHtml($selectAttrs) . '>' . "\n" . $optionsHtml . '</select>';
        }
    }
}

// ── sfWidgetFormTextarea / sfWidgetFormTextArea ──────────────────────

if (!class_exists('sfWidgetFormTextarea', false)) {
    class sfWidgetFormTextarea extends sfWidgetForm
    {
        public function render($name, $value = null, $attributes = [], $errors = [])
        {
            $mergedAttrs = array_merge($this->attributes, $attributes);
            if (!isset($mergedAttrs['class'])) {
                $mergedAttrs['class'] = 'form-control';
            }

            return $this->renderContentTag('textarea', htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8'), array_merge([
                'name' => $name,
                'id' => $this->generateId($name),
            ], $mergedAttrs));
        }
    }
}

// Alias for alternate capitalisation used in some plugins
if (!class_exists('sfWidgetFormTextArea', false)) {
    class_alias('sfWidgetFormTextarea', 'sfWidgetFormTextArea');
}

// ── sfWidgetFormChoice ──────────────────────────────────────────────

if (!class_exists('sfWidgetFormChoice', false)) {
    class sfWidgetFormChoice extends sfWidgetFormSelect
    {
        protected function configure($options = [], $attributes = [])
        {
            parent::configure($options, $attributes);
            $this->addOption('expanded', false);
        }

        public function render($name, $value = null, $attributes = [], $errors = [])
        {
            if ($this->getOption('expanded') && !$this->getOption('multiple')) {
                // Render as radio buttons
                return $this->renderRadioGroup($name, $value, $attributes);
            }

            // Default to select dropdown
            return parent::render($name, $value, $attributes, $errors);
        }

        protected function renderRadioGroup($name, $value, $attributes)
        {
            $choices = $this->getOption('choices');
            if (is_callable($choices)) {
                $choices = call_user_func($choices);
            }

            $html = '';
            foreach ((array) $choices as $optValue => $optLabel) {
                $id = $this->generateId($name) . '_' . $optValue;
                $checked = (string) $optValue === (string) $value ? ' checked="checked"' : '';
                $html .= '<div class="form-check">'
                    . '<input class="form-check-input" type="radio" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                    . '" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8')
                    . '" value="' . htmlspecialchars((string) $optValue, ENT_QUOTES, 'UTF-8') . '"' . $checked . ' />'
                    . '<label class="form-check-label" for="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">'
                    . htmlspecialchars((string) $optLabel, ENT_QUOTES, 'UTF-8') . '</label>'
                    . '</div>' . "\n";
            }

            return $html;
        }
    }
}

// ── sfWidgetFormSelectRadio ─────────────────────────────────────────

if (!class_exists('sfWidgetFormSelectRadio', false)) {
    class sfWidgetFormSelectRadio extends sfWidgetForm
    {
        protected function configure($options = [], $attributes = [])
        {
            parent::configure($options, $attributes);
            $this->addOption('choices', []);
            $this->addOption('separator', "\n");
            $this->addOption('formatter', null);
        }

        public function render($name, $value = null, $attributes = [], $errors = [])
        {
            $choices = $this->getOption('choices');
            if (is_callable($choices)) {
                $choices = call_user_func($choices);
            }

            $html = '';
            foreach ((array) $choices as $optValue => $optLabel) {
                $id = $this->generateId($name) . '_' . $optValue;
                $checked = (string) $optValue === (string) $value ? ' checked="checked"' : '';
                $html .= '<div class="form-check">'
                    . '<input class="form-check-input" type="radio" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                    . '" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8')
                    . '" value="' . htmlspecialchars((string) $optValue, ENT_QUOTES, 'UTF-8') . '"' . $checked . ' />'
                    . '<label class="form-check-label" for="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">'
                    . htmlspecialchars((string) $optLabel, ENT_QUOTES, 'UTF-8') . '</label>'
                    . '</div>' . "\n";
            }

            return $html;
        }
    }
}

// ── sfWidgetFormI18nChoiceLanguage ───────────────────────────────────

if (!class_exists('sfWidgetFormI18nChoiceLanguage', false)) {
    class sfWidgetFormI18nChoiceLanguage extends sfWidgetFormSelect
    {
        protected function configure($options = [], $attributes = [])
        {
            parent::configure($options, $attributes);
            $this->addOption('culture', null);
            $this->addOption('languages', null);
            $this->addOption('add_empty', false);

            // Provide common languages as default choices
            $defaultLangs = [
                '' => '',
                'en' => 'English',
                'fr' => 'French',
                'de' => 'German',
                'es' => 'Spanish',
                'pt' => 'Portuguese',
                'it' => 'Italian',
                'nl' => 'Dutch',
                'af' => 'Afrikaans',
                'zu' => 'Zulu',
                'xh' => 'Xhosa',
                'ar' => 'Arabic',
                'zh' => 'Chinese',
                'ja' => 'Japanese',
                'ko' => 'Korean',
                'ru' => 'Russian',
            ];

            if (!isset($options['choices']) || empty($options['choices'])) {
                $this->setOption('choices', $defaultLangs);
            }
        }
    }
}
