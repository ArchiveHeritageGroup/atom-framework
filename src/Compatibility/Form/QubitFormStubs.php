<?php

/**
 * AtoM-specific Form Class Compatibility Stubs.
 *
 * Covers: QubitValidatorPassword, QubitValidatorAccessionIdentifier,
 * QubitValidatorActorDescriptionIdentifier, QubitValidatorUrl,
 * QubitValidatorForbiddenValues, QubitValidatorCountable,
 * QubitWidgetFormInputMany, QubitWidgetFormSchemaFormatterList.
 */

use Illuminate\Database\Capsule\Manager as DB;

// ── QubitValidatorPassword ──────────────────────────────────────────

if (!class_exists('QubitValidatorPassword', false)) {
    class QubitValidatorPassword extends sfValidatorBase
    {
        protected function configure($options = [], $messages = [])
        {
            $this->addOption('min_length', 8);
            $this->addMessage('min_length', 'Password must be at least %min_length% characters.');
            $this->addMessage('required', 'Password is required.');
        }

        protected function doClean($value)
        {
            $value = parent::doClean($value);

            if (null === $value) {
                return $value;
            }

            $value = (string) $value;
            $minLength = $this->getOption('min_length') ?? 8;

            $len = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);

            if ($len < $minLength) {
                throw new sfValidatorError($this, 'min_length', ['%min_length%' => $minLength]);
            }

            return $value;
        }
    }
}

// ── QubitValidatorAccessionIdentifier ───────────────────────────────

if (!class_exists('QubitValidatorAccessionIdentifier', false)) {
    class QubitValidatorAccessionIdentifier extends sfValidatorBase
    {
        protected function configure($options = [], $messages = [])
        {
            $this->addOption('resource_id', null);
            $this->addMessage('duplicate', 'This accession identifier is already in use.');
        }

        protected function doClean($value)
        {
            $value = parent::doClean($value);

            if (null === $value) {
                return $value;
            }

            $value = (string) $value;

            try {
                $query = DB::table('accession')
                    ->where('identifier', $value);

                $resourceId = $this->getOption('resource_id');
                if ($resourceId) {
                    $query->where('id', '!=', $resourceId);
                }

                if ($query->exists()) {
                    throw new sfValidatorError($this, 'duplicate');
                }
            } catch (sfValidatorError $e) {
                throw $e;
            } catch (\Throwable $e) {
                // DB not available — pass through
            }

            return $value;
        }
    }
}

// ── QubitValidatorActorDescriptionIdentifier ────────────────────────

if (!class_exists('QubitValidatorActorDescriptionIdentifier', false)) {
    class QubitValidatorActorDescriptionIdentifier extends sfValidatorBase
    {
        protected function configure($options = [], $messages = [])
        {
            $this->addOption('resource_id', null);
            $this->addMessage('duplicate', 'This identifier is already in use.');
        }

        protected function doClean($value)
        {
            $value = parent::doClean($value);

            if (null === $value) {
                return $value;
            }

            $value = (string) $value;

            try {
                $query = DB::table('actor')
                    ->where('description_identifier', $value);

                $resourceId = $this->getOption('resource_id');
                if ($resourceId) {
                    $query->where('id', '!=', $resourceId);
                }

                if ($query->exists()) {
                    throw new sfValidatorError($this, 'duplicate');
                }
            } catch (sfValidatorError $e) {
                throw $e;
            } catch (\Throwable $e) {
                // DB not available — pass through
            }

            return $value;
        }
    }
}

// ── QubitValidatorUrl ───────────────────────────────────────────────

if (!class_exists('QubitValidatorUrl', false)) {
    class QubitValidatorUrl extends sfValidatorUrl
    {
        protected function configure($options = [], $messages = [])
        {
            parent::configure($options, $messages);
            $this->setOption('required', false);
        }
    }
}

// ── QubitValidatorForbiddenValues ───────────────────────────────────

if (!class_exists('QubitValidatorForbiddenValues', false)) {
    class QubitValidatorForbiddenValues extends sfValidatorBase
    {
        protected function configure($options = [], $messages = [])
        {
            $this->addRequiredOption('forbidden_values');
            $this->addOption('forbidden_values', []);
            $this->addOption('case_sensitive', true);
            $this->addMessage('forbidden', 'This value is not allowed.');
        }

        protected function doClean($value)
        {
            $value = parent::doClean($value);

            if (null === $value) {
                return $value;
            }

            $forbidden = $this->getOption('forbidden_values') ?? [];

            if ($this->getOption('case_sensitive')) {
                if (in_array($value, $forbidden, true)) {
                    throw new sfValidatorError($this, 'forbidden');
                }
            } else {
                $lower = strtolower((string) $value);
                foreach ($forbidden as $f) {
                    if (strtolower((string) $f) === $lower) {
                        throw new sfValidatorError($this, 'forbidden');
                    }
                }
            }

            return $value;
        }
    }
}

// ── QubitValidatorCountable ─────────────────────────────────────────

if (!class_exists('QubitValidatorCountable', false)) {
    class QubitValidatorCountable extends sfValidatorBase
    {
        protected function configure($options = [], $messages = [])
        {
            $this->addOption('min', null);
            $this->addOption('max', null);
            $this->addMessage('min', 'At least %min% items required.');
            $this->addMessage('max', 'At most %max% items allowed.');
        }

        protected function doClean($value)
        {
            if (!is_array($value) && !($value instanceof \Countable)) {
                if ($this->getOption('required')) {
                    throw new sfValidatorError($this, 'required');
                }

                return $value;
            }

            $count = count($value);

            if (null !== $this->getOption('min') && $count < $this->getOption('min')) {
                throw new sfValidatorError($this, 'min', ['%min%' => $this->getOption('min')]);
            }

            if (null !== $this->getOption('max') && $count > $this->getOption('max')) {
                throw new sfValidatorError($this, 'max', ['%max%' => $this->getOption('max')]);
            }

            return $value;
        }
    }
}

// ── QubitWidgetFormInputMany ────────────────────────────────────────

if (!class_exists('QubitWidgetFormInputMany', false)) {
    class QubitWidgetFormInputMany extends sfWidgetForm
    {
        protected function configure($options = [], $attributes = [])
        {
            parent::configure($options, $attributes);
            $this->addOption('separator', "\n");
        }

        public function render($name, $value = null, $attributes = [], $errors = [])
        {
            $mergedAttrs = array_merge($this->attributes, $attributes);
            if (!isset($mergedAttrs['class'])) {
                $mergedAttrs['class'] = 'form-control';
            }

            // Render as textarea — each line is a separate value
            $textValue = '';
            if (is_array($value)) {
                $textValue = implode($this->getOption('separator') ?? "\n", $value);
            } elseif (is_string($value)) {
                $textValue = $value;
            }

            return '<textarea name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                . '" id="' . htmlspecialchars($this->generateId($name), ENT_QUOTES, 'UTF-8') . '"'
                . $this->attributesToHtml($mergedAttrs) . '>'
                . htmlspecialchars($textValue, ENT_QUOTES, 'UTF-8')
                . '</textarea>';
        }
    }
}

// ── QubitWidgetFormSchemaFormatterList ───────────────────────────────

if (!class_exists('QubitWidgetFormSchemaFormatterList', false)) {
    class QubitWidgetFormSchemaFormatterList extends sfWidgetFormSchemaFormatter
    {
        protected $rowFormat = "<tr>\n <td><span title=\"%help%\">%label%</td>\n <td>%error%%field%%hidden_fields%</td>\n</tr>\n";
        protected $helpFormat = '%help%';
        protected $errorRowFormat = "<tr><td colspan=\"2\">\n%errors%</td></tr>\n";
        protected $errorListFormatInARow = " <div class=\"messages error\"><ul>\n%errors% </ul></div>\n";
        protected $errorRowFormatInARow = " <li>%error%</li>\n";
        protected $namedErrorRowFormatInARow = " <li>%name%: %error%</li>\n";
        protected $decoratorFormat = "<table>\n %content%</table>";
    }
}
