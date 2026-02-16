<?php

/**
 * Symfony 1.x Validator Schema & Error Compatibility Stubs.
 *
 * Covers: sfValidatorSchema, sfValidatorSchemaCompare,
 * sfValidatorError, sfValidatorErrorSchema.
 */

// ── sfValidatorError ────────────────────────────────────────────────

if (!class_exists('sfValidatorError', false)) {
    class sfValidatorError extends \Exception
    {
        protected $validator;
        protected $arguments = [];

        public function __construct($validator, $code = 'invalid', $arguments = [])
        {
            $this->validator = $validator;
            $this->arguments = $arguments;

            $message = $code;
            if ($validator instanceof sfValidatorBase) {
                $msg = $validator->getMessage($code);
                if ($msg) {
                    $message = strtr($msg, $arguments);
                }
            } elseif (is_string($validator)) {
                $message = $validator;
            }

            parent::__construct($message, 0);
        }

        public function getValidator()
        {
            return $this->validator;
        }

        public function getArguments()
        {
            return $this->arguments;
        }

        public function getMessageFormat()
        {
            return $this->getMessage();
        }
    }
}

// ── sfValidatorErrorSchema ──────────────────────────────────────────

if (!class_exists('sfValidatorErrorSchema', false)) {
    class sfValidatorErrorSchema extends sfValidatorError implements \Iterator, \ArrayAccess, \Countable
    {
        protected $errors = [];
        protected $globalErrors = [];
        protected $namedErrors = [];

        public function __construct($validator = null, $errors = [])
        {
            if (null === $validator) {
                $validator = new sfValidatorPass();
            }

            parent::__construct($validator, '', []);

            foreach ($errors as $key => $error) {
                $this->addError($error, $key);
            }
        }

        public function addError($error, $name = null)
        {
            if (null === $name || is_int($name)) {
                $this->globalErrors[] = $error;
            } else {
                if (!isset($this->namedErrors[$name])) {
                    $this->namedErrors[$name] = [];
                }
                $this->namedErrors[$name][] = $error;
            }

            $this->errors = array_merge($this->globalErrors, $this->namedErrors);
        }

        public function getGlobalErrors()
        {
            return $this->globalErrors;
        }

        public function getNamedErrors()
        {
            return $this->namedErrors;
        }

        public function getErrors()
        {
            $all = [];
            foreach ($this->globalErrors as $e) {
                $all[] = $e;
            }
            foreach ($this->namedErrors as $name => $errors) {
                foreach ($errors as $e) {
                    $all[$name] = $e;
                }
            }

            return $all;
        }

        public function count(): int
        {
            return count($this->globalErrors) + count($this->namedErrors);
        }

        public function getFullMessage(): string
        {
            $messages = [];
            foreach ($this->globalErrors as $e) {
                $messages[] = $e instanceof \Exception ? $e->getMessage() : (string) $e;
            }
            foreach ($this->namedErrors as $name => $errors) {
                foreach ($errors as $e) {
                    $messages[] = $name . ': ' . ($e instanceof \Exception ? $e->getMessage() : (string) $e);
                }
            }

            return implode('; ', $messages);
        }

        public function __toString(): string
        {
            return $this->getFullMessage();
        }

        // Iterator
        public function current(): mixed
        {
            return current($this->errors);
        }

        public function key(): mixed
        {
            return key($this->errors);
        }

        public function next(): void
        {
            next($this->errors);
        }

        public function rewind(): void
        {
            $this->errors = array_merge($this->globalErrors, $this->namedErrors);
            reset($this->errors);
        }

        public function valid(): bool
        {
            return null !== key($this->errors);
        }

        // ArrayAccess
        public function offsetExists(mixed $offset): bool
        {
            return isset($this->namedErrors[$offset]) || isset($this->globalErrors[$offset]);
        }

        public function offsetGet(mixed $offset): mixed
        {
            if (isset($this->namedErrors[$offset])) {
                return $this->namedErrors[$offset];
            }

            return $this->globalErrors[$offset] ?? null;
        }

        public function offsetSet(mixed $offset, mixed $value): void
        {
            $this->addError($value, $offset);
        }

        public function offsetUnset(mixed $offset): void
        {
            unset($this->namedErrors[$offset], $this->globalErrors[$offset]);
        }
    }
}

// ── sfValidatorSchema ───────────────────────────────────────────────

if (!class_exists('sfValidatorSchema', false)) {
    class sfValidatorSchema extends sfValidatorBase implements \ArrayAccess
    {
        protected $fields = [];
        protected $preValidator = null;
        protected $postValidator = null;

        public function __construct($fields = [], $options = [], $messages = [])
        {
            parent::__construct($options, $messages);

            if (is_array($fields)) {
                foreach ($fields as $name => $validator) {
                    $this[$name] = $validator;
                }
            }
        }

        protected function configure($options = [], $messages = [])
        {
            $this->addOption('allow_extra_fields', false);
            $this->addOption('filter_extra_fields', true);
        }

        public function clean($values)
        {
            return $this->doClean($values);
        }

        protected function doClean($values)
        {
            if (!is_array($values)) {
                throw new sfValidatorError($this, 'invalid');
            }

            $clean = [];
            $errors = new sfValidatorErrorSchema($this);

            // Pre-validator
            if (null !== $this->preValidator) {
                try {
                    $values = $this->preValidator->clean($values);
                } catch (sfValidatorError $e) {
                    $errors->addError($e);
                } catch (sfValidatorErrorSchema $e) {
                    $errors->addError($e);
                }
            }

            // Validate each field
            foreach ($this->fields as $name => $validator) {
                $value = $values[$name] ?? null;

                try {
                    $clean[$name] = $validator->clean($value);
                } catch (sfValidatorError $e) {
                    $errors->addError($e, $name);
                }
            }

            // Extra fields
            $extraFields = array_diff_key($values, $this->fields);
            if (count($extraFields) > 0) {
                if (!$this->getOption('allow_extra_fields')) {
                    foreach ($extraFields as $name => $v) {
                        $errors->addError(new sfValidatorError($this, 'Extra field.'), $name);
                    }
                } elseif (!$this->getOption('filter_extra_fields')) {
                    $clean = array_merge($clean, $extraFields);
                }

                // When allow_extra_fields is true and filter_extra_fields is true,
                // extra fields are silently dropped (which is the common case)
            }

            // Post-validator
            if (null !== $this->postValidator) {
                try {
                    $clean = $this->postValidator->clean($clean);
                } catch (sfValidatorError $e) {
                    $errors->addError($e);
                } catch (sfValidatorErrorSchema $e) {
                    $errors->addError($e);
                }
            }

            if ($errors->count() > 0) {
                throw $errors;
            }

            return $clean;
        }

        public function setPreValidator($validator)
        {
            $this->preValidator = $validator;

            return $this;
        }

        public function getPreValidator()
        {
            return $this->preValidator;
        }

        public function setPostValidator($validator)
        {
            $this->postValidator = $validator;

            return $this;
        }

        public function getPostValidator()
        {
            return $this->postValidator;
        }

        public function getFields()
        {
            return $this->fields;
        }

        // ArrayAccess
        public function offsetExists(mixed $offset): bool
        {
            return isset($this->fields[$offset]);
        }

        public function offsetGet(mixed $offset): mixed
        {
            return $this->fields[$offset] ?? null;
        }

        public function offsetSet(mixed $offset, mixed $value): void
        {
            $this->fields[$offset] = $value;
        }

        public function offsetUnset(mixed $offset): void
        {
            unset($this->fields[$offset]);
        }
    }
}

// ── sfValidatorSchemaCompare ────────────────────────────────────────

if (!class_exists('sfValidatorSchemaCompare', false)) {
    class sfValidatorSchemaCompare extends sfValidatorBase
    {
        public const EQUAL = '==';
        public const IDENTICAL = '===';
        public const NOT_EQUAL = '!=';
        public const LESS_THAN = '<';
        public const LESS_THAN_EQUAL = '<=';
        public const GREATER_THAN = '>';
        public const GREATER_THAN_EQUAL = '>=';

        protected $leftField;
        protected $operator;
        protected $rightField;

        public function __construct($leftField, $operator, $rightField, $options = [], $messages = [])
        {
            $this->leftField = $leftField;
            $this->operator = $operator;
            $this->rightField = $rightField;

            parent::__construct($options, $messages);
        }

        protected function configure($options = [], $messages = [])
        {
            $this->addOption('throw_global_error', false);
            $this->addMessage('invalid', 'The values do not match.');
        }

        protected function doClean($values)
        {
            if (!is_array($values)) {
                throw new sfValidatorError($this, 'invalid');
            }

            $left = $values[$this->leftField] ?? null;
            $right = $values[$this->rightField] ?? null;

            $valid = match ($this->operator) {
                self::EQUAL => $left == $right,
                self::IDENTICAL => $left === $right,
                self::NOT_EQUAL => $left != $right,
                self::LESS_THAN => $left < $right,
                self::LESS_THAN_EQUAL => $left <= $right,
                self::GREATER_THAN => $left > $right,
                self::GREATER_THAN_EQUAL => $left >= $right,
                default => false,
            };

            if (!$valid) {
                if ($this->getOption('throw_global_error')) {
                    throw new sfValidatorError($this, 'invalid');
                }

                throw new sfValidatorErrorSchema($this, [
                    $this->leftField => new sfValidatorError($this, 'invalid'),
                ]);
            }

            return $values;
        }
    }
}
