<?php

/**
 * Symfony 1.x Validator Compatibility Stubs.
 *
 * Provides the sfValidator* API surface used by AHG plugins without
 * requiring Symfony. Each class is guarded with class_exists() so it
 * never conflicts with the real Symfony classes when loaded.
 *
 * Covers: sfValidatorBase, sfValidator, sfValidatorString, sfValidatorInteger,
 * sfValidatorNumber, sfValidatorPass, sfValidatorBoolean, sfValidatorChoice,
 * sfValidatorDate, sfValidatorUrl, sfValidatorEmail, sfValidatorRegex,
 * sfValidatorCallback, sfValidatorFile, sfValidatorAnd, sfValidatorOr,
 * sfValidatorI18nChoiceLanguage.
 */

// ── sfValidatorBase ─────────────────────────────────────────────────

if (!class_exists('sfValidatorBase', false)) {
    class sfValidatorBase
    {
        protected $options = [];
        protected $messages = [];
        protected $requiredOptions = [];

        public function __construct($options = [], $messages = [])
        {
            $this->configure($options, $messages);

            // Apply passed options/messages over defaults set in configure()
            $this->options = array_merge($this->options, $options);
            $this->messages = array_merge($this->messages, $messages);

            // Ensure defaults
            if (!isset($this->options['required'])) {
                $this->options['required'] = false;
            }
            if (!isset($this->options['trim'])) {
                $this->options['trim'] = false;
            }
            if (!isset($this->messages['required'])) {
                $this->messages['required'] = 'Required.';
            }
            if (!isset($this->messages['invalid'])) {
                $this->messages['invalid'] = 'Invalid.';
            }
        }

        protected function configure($options = [], $messages = [])
        {
            // Override in subclasses
        }

        public function clean($value)
        {
            return $this->doClean($value);
        }

        protected function doClean($value)
        {
            if ($this->options['trim'] && is_string($value)) {
                $value = trim($value);
            }

            if ($this->getOption('required') && $this->isEmpty($value)) {
                throw new sfValidatorError($this, 'required');
            }

            if (!$this->getOption('required') && $this->isEmpty($value)) {
                return null;
            }

            return $value;
        }

        protected function isEmpty($value)
        {
            return null === $value || '' === $value || (is_array($value) && 0 === count($value));
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
            $this->options[$name] = $default;
        }

        public function addRequiredOption($name)
        {
            $this->requiredOptions[] = $name;
        }

        public function getMessage($name)
        {
            return $this->messages[$name] ?? null;
        }

        public function setMessage($name, $value)
        {
            $this->messages[$name] = $value;

            return $this;
        }

        public function getMessages()
        {
            return $this->messages;
        }

        public function addMessage($name, $default = null)
        {
            $this->messages[$name] = $default;
        }
    }
}

// ── sfValidator (alias) ─────────────────────────────────────────────

if (!class_exists('sfValidator', false)) {
    class sfValidator extends sfValidatorBase
    {
    }
}

// ── sfValidatorString ───────────────────────────────────────────────

if (!class_exists('sfValidatorString', false)) {
    class sfValidatorString extends sfValidatorBase
    {
        protected function configure($options = [], $messages = [])
        {
            $this->addOption('max_length', null);
            $this->addOption('min_length', null);
            $this->addOption('trim', true);
            $this->addMessage('max_length', 'Too long (maximum %max_length% characters).');
            $this->addMessage('min_length', 'Too short (minimum %min_length% characters).');
        }

        protected function doClean($value)
        {
            $value = parent::doClean($value);

            if (null === $value) {
                return $value;
            }

            $value = (string) $value;

            if ($this->options['trim']) {
                $value = trim($value);
            }

            $len = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);

            if (null !== $this->getOption('max_length') && $len > $this->getOption('max_length')) {
                throw new sfValidatorError($this, 'max_length', ['%max_length%' => $this->getOption('max_length')]);
            }

            if (null !== $this->getOption('min_length') && $len < $this->getOption('min_length')) {
                throw new sfValidatorError($this, 'min_length', ['%min_length%' => $this->getOption('min_length')]);
            }

            return $value;
        }
    }
}

// ── sfValidatorInteger ──────────────────────────────────────────────

if (!class_exists('sfValidatorInteger', false)) {
    class sfValidatorInteger extends sfValidatorBase
    {
        protected function configure($options = [], $messages = [])
        {
            $this->addOption('min', null);
            $this->addOption('max', null);
            $this->addMessage('max', 'Must be at most %max%.');
            $this->addMessage('min', 'Must be at least %min%.');
        }

        protected function doClean($value)
        {
            $value = parent::doClean($value);

            if (null === $value) {
                return $value;
            }

            if (!is_numeric($value) || intval($value) != $value) {
                throw new sfValidatorError($this, 'invalid');
            }

            $value = (int) $value;

            if (null !== $this->getOption('min') && $value < $this->getOption('min')) {
                throw new sfValidatorError($this, 'min', ['%min%' => $this->getOption('min')]);
            }

            if (null !== $this->getOption('max') && $value > $this->getOption('max')) {
                throw new sfValidatorError($this, 'max', ['%max%' => $this->getOption('max')]);
            }

            return $value;
        }
    }
}

// ── sfValidatorNumber ───────────────────────────────────────────────

if (!class_exists('sfValidatorNumber', false)) {
    class sfValidatorNumber extends sfValidatorBase
    {
        protected function configure($options = [], $messages = [])
        {
            $this->addOption('min', null);
            $this->addOption('max', null);
            $this->addMessage('max', 'Must be at most %max%.');
            $this->addMessage('min', 'Must be at least %min%.');
        }

        protected function doClean($value)
        {
            $value = parent::doClean($value);

            if (null === $value) {
                return $value;
            }

            if (!is_numeric($value)) {
                throw new sfValidatorError($this, 'invalid');
            }

            $value = (float) $value;

            if (null !== $this->getOption('min') && $value < $this->getOption('min')) {
                throw new sfValidatorError($this, 'min', ['%min%' => $this->getOption('min')]);
            }

            if (null !== $this->getOption('max') && $value > $this->getOption('max')) {
                throw new sfValidatorError($this, 'max', ['%max%' => $this->getOption('max')]);
            }

            return $value;
        }
    }
}

// ── sfValidatorPass ─────────────────────────────────────────────────

if (!class_exists('sfValidatorPass', false)) {
    class sfValidatorPass extends sfValidatorBase
    {
        protected function doClean($value)
        {
            return $value;
        }
    }
}

// ── sfValidatorBoolean ──────────────────────────────────────────────

if (!class_exists('sfValidatorBoolean', false)) {
    class sfValidatorBoolean extends sfValidatorBase
    {
        protected function configure($options = [], $messages = [])
        {
            $this->addOption('true_values', ['true', 't', 'yes', 'y', 'on', '1', 1, true]);
            $this->addOption('false_values', ['false', 'f', 'no', 'n', 'off', '0', 0, false, '']);
        }

        protected function doClean($value)
        {
            if (in_array($value, $this->getOption('true_values'), true)) {
                return true;
            }

            if (in_array($value, $this->getOption('false_values'), true)) {
                return false;
            }

            throw new sfValidatorError($this, 'invalid');
        }
    }
}

// ── sfValidatorChoice ───────────────────────────────────────────────

if (!class_exists('sfValidatorChoice', false)) {
    class sfValidatorChoice extends sfValidatorBase
    {
        protected function configure($options = [], $messages = [])
        {
            $this->addRequiredOption('choices');
            $this->addOption('choices', []);
            $this->addOption('multiple', false);
        }

        protected function doClean($value)
        {
            $value = parent::doClean($value);

            if (null === $value) {
                return $value;
            }

            $choices = $this->getOption('choices');
            if (is_callable($choices)) {
                $choices = call_user_func($choices);
            }

            if ($this->getOption('multiple')) {
                if (!is_array($value)) {
                    $value = [$value];
                }
                foreach ($value as $v) {
                    if (!in_array($v, $choices)) {
                        throw new sfValidatorError($this, 'invalid');
                    }
                }

                return $value;
            }

            if (!in_array($value, $choices)) {
                throw new sfValidatorError($this, 'invalid');
            }

            return $value;
        }
    }
}

// ── sfValidatorDate ─────────────────────────────────────────────────

if (!class_exists('sfValidatorDate', false)) {
    class sfValidatorDate extends sfValidatorBase
    {
        protected function configure($options = [], $messages = [])
        {
            $this->addOption('date_format', null);
            $this->addOption('date_format_error', null);
            $this->addOption('with_time', false);
            $this->addMessage('bad_format', 'Date does not match expected format (%date_format%).');
        }

        protected function doClean($value)
        {
            $value = parent::doClean($value);

            if (null === $value) {
                return $value;
            }

            if (is_array($value)) {
                // Handle array date input (year/month/day)
                $year = $value['year'] ?? '';
                $month = $value['month'] ?? '';
                $day = $value['day'] ?? '';

                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }

            $format = $this->getOption('date_format');
            if (null !== $format && !preg_match($format, $value)) {
                throw new sfValidatorError($this, 'bad_format', ['%date_format%' => $this->getOption('date_format_error') ?: $format]);
            }

            // Verify it parses
            $ts = strtotime($value);
            if (false === $ts) {
                throw new sfValidatorError($this, 'invalid');
            }

            return $value;
        }
    }
}

// ── sfValidatorUrl ──────────────────────────────────────────────────

if (!class_exists('sfValidatorUrl', false)) {
    class sfValidatorUrl extends sfValidatorBase
    {
        protected function configure($options = [], $messages = [])
        {
            $this->addOption('protocols', ['http', 'https', 'ftp', 'ftps']);
        }

        protected function doClean($value)
        {
            $value = parent::doClean($value);

            if (null === $value) {
                return $value;
            }

            $value = (string) $value;

            if (false === filter_var($value, FILTER_VALIDATE_URL)) {
                throw new sfValidatorError($this, 'invalid');
            }

            return $value;
        }
    }
}

// ── sfValidatorEmail ────────────────────────────────────────────────

if (!class_exists('sfValidatorEmail', false)) {
    class sfValidatorEmail extends sfValidatorBase
    {
        protected function doClean($value)
        {
            $value = parent::doClean($value);

            if (null === $value) {
                return $value;
            }

            $value = (string) $value;

            if (false === filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new sfValidatorError($this, 'invalid');
            }

            return $value;
        }
    }
}

// ── sfValidatorRegex ────────────────────────────────────────────────

if (!class_exists('sfValidatorRegex', false)) {
    class sfValidatorRegex extends sfValidatorBase
    {
        protected function configure($options = [], $messages = [])
        {
            $this->addRequiredOption('pattern');
            $this->addOption('pattern', null);
            $this->addOption('must_match', true);
        }

        protected function doClean($value)
        {
            $value = parent::doClean($value);

            if (null === $value) {
                return $value;
            }

            $value = (string) $value;
            $pattern = $this->getOption('pattern');
            if (is_callable($pattern)) {
                $pattern = call_user_func($pattern);
            }

            $match = preg_match($pattern, $value);

            if ($this->getOption('must_match') && !$match) {
                throw new sfValidatorError($this, 'invalid');
            }

            if (!$this->getOption('must_match') && $match) {
                throw new sfValidatorError($this, 'invalid');
            }

            return $value;
        }
    }
}

// ── sfValidatorCallback ─────────────────────────────────────────────

if (!class_exists('sfValidatorCallback', false)) {
    class sfValidatorCallback extends sfValidatorBase
    {
        protected function configure($options = [], $messages = [])
        {
            $this->addRequiredOption('callback');
            $this->addOption('callback', null);
            $this->addOption('arguments', []);
        }

        protected function doClean($value)
        {
            $callback = $this->getOption('callback');
            $args = $this->getOption('arguments');

            return call_user_func($callback, $this, $value, $args);
        }
    }
}

// ── sfValidatorFile ─────────────────────────────────────────────────

if (!class_exists('sfValidatorFile', false)) {
    class sfValidatorFile extends sfValidatorBase
    {
        protected function configure($options = [], $messages = [])
        {
            $this->addOption('max_size', null);
            $this->addOption('mime_types', null);
            $this->addOption('mime_type_guessers', null);
            $this->addOption('validated_file_class', 'sfValidatedFile');
            $this->addOption('path', null);
            $this->addMessage('max_size', 'File too large (max %max_size% bytes).');
            $this->addMessage('mime_types', 'Invalid mime type (%mime_type%).');
            $this->addMessage('partial', 'File was only partially uploaded.');
            $this->addMessage('no_tmp_dir', 'No temporary directory configured.');
        }

        protected function doClean($value)
        {
            // Handle both array (PHP file upload) and null
            if (!is_array($value) || !isset($value['tmp_name'])) {
                if ($this->getOption('required')) {
                    throw new sfValidatorError($this, 'required');
                }

                return null;
            }

            if (UPLOAD_ERR_NO_FILE === ($value['error'] ?? UPLOAD_ERR_OK)) {
                if ($this->getOption('required')) {
                    throw new sfValidatorError($this, 'required');
                }

                return null;
            }

            if (UPLOAD_ERR_OK !== ($value['error'] ?? UPLOAD_ERR_OK)) {
                throw new sfValidatorError($this, 'partial');
            }

            $maxSize = $this->getOption('max_size');
            if (null !== $maxSize && $value['size'] > $maxSize) {
                throw new sfValidatorError($this, 'max_size', ['%max_size%' => $maxSize]);
            }

            $mimeTypes = $this->getOption('mime_types');
            if (null !== $mimeTypes && !in_array($value['type'], (array) $mimeTypes)) {
                throw new sfValidatorError($this, 'mime_types', ['%mime_type%' => $value['type']]);
            }

            return $value;
        }
    }
}

// ── sfValidatorAnd ──────────────────────────────────────────────────

if (!class_exists('sfValidatorAnd', false)) {
    class sfValidatorAnd extends sfValidatorBase
    {
        protected $validators = [];

        public function __construct($validators = [], $options = [], $messages = [])
        {
            $this->validators = $validators;
            parent::__construct($options, $messages);
        }

        protected function doClean($value)
        {
            foreach ($this->validators as $validator) {
                $value = $validator->clean($value);
            }

            return $value;
        }

        public function getValidators()
        {
            return $this->validators;
        }
    }
}

// ── sfValidatorOr ───────────────────────────────────────────────────

if (!class_exists('sfValidatorOr', false)) {
    class sfValidatorOr extends sfValidatorBase
    {
        protected $validators = [];

        public function __construct($validators = [], $options = [], $messages = [])
        {
            $this->validators = $validators;
            parent::__construct($options, $messages);
        }

        protected function doClean($value)
        {
            $errors = [];

            foreach ($this->validators as $validator) {
                try {
                    return $validator->clean($value);
                } catch (sfValidatorError $e) {
                    $errors[] = $e;
                }
            }

            throw new sfValidatorError($this, 'invalid');
        }

        public function getValidators()
        {
            return $this->validators;
        }
    }
}

// ── sfValidatorI18nChoiceLanguage ────────────────────────────────────

if (!class_exists('sfValidatorI18nChoiceLanguage', false)) {
    class sfValidatorI18nChoiceLanguage extends sfValidatorBase
    {
        protected function configure($options = [], $messages = [])
        {
            $this->addOption('languages', null);
        }

        protected function doClean($value)
        {
            $value = parent::doClean($value);

            if (null === $value) {
                return $value;
            }

            // Accept any 2-letter language code
            if (!preg_match('/^[a-z]{2,3}(_[A-Z]{2})?$/', (string) $value)) {
                throw new sfValidatorError($this, 'invalid');
            }

            return (string) $value;
        }
    }
}
