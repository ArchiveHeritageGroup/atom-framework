<?php

/**
 * AtoM Theme Form Class Compatibility Stubs.
 *
 * Bootstrap 5 theme form classes used by AHG plugins.
 * Covers: arB5WidgetFormSchemaFormatter, arWidgetFormInputFileEditable,
 * arB5WidgetFormInputFileEditable, arWidgetFormSelectRadio,
 * arWidgetFormUploadQuota.
 */

// ── arB5WidgetFormSchemaFormatter ───────────────────────────────────

if (!class_exists('arB5WidgetFormSchemaFormatter', false)) {
    class arB5WidgetFormSchemaFormatter extends sfWidgetFormSchemaFormatter
    {
        protected $rowFormat = "<div class=\"mb-3\">\n  %label%\n  %field%\n"
            . "  %error%\n  %help%\n  %hidden_fields%\n</div>";
        protected $formErrorListFormat = '<div class="alert alert-danger"'
            . " role=\"alert\">\n  %errors%\n</div>\n";
        protected $errorListFormatInARow = '<div class="invalid-feedback"'
            . " id=\"%errors_id%\">\n  %errors%\n</div>\n";
        protected $errorRowFormatInARow = "<span>%error%</span>\n";
        protected $namedErrorRowFormatInARow = "<span>%name%: %error%</span>\n";
        protected $helpFormat = "<div class=\"form-text\" id=\"%help_id%\">\n"
            . "  %help%\n</div>\n";
        protected $name;

        public function generateLabelName($name)
        {
            $this->name = $name;
            $label = parent::generateLabelName($name);

            // Check if field is required and add asterisk
            if (null !== $this->form) {
                try {
                    $validatorSchema = $this->form->getValidatorSchema();
                    if (
                        isset($validatorSchema[$name])
                        && $validatorSchema[$name]->getOption('required')
                    ) {
                        $requiredTitle = function_exists('__') ? __('This field is required.') : 'This field is required.';
                        $label .= '<span aria-hidden="true" class="text-primary ms-1" title="'
                            . $requiredTitle
                            . '">'
                            . '<strong>*</strong></span>'
                            . '<span class="visually-hidden">'
                            . $requiredTitle
                            . '</span>';
                    }
                } catch (\Throwable $e) {
                    // Validator schema not available
                }
            }

            return $label;
        }

        public function getErrorListFormatInARow()
        {
            // CSRF and non-field-related are global errors
            if (
                null === $this->name
                || (null !== $this->form && $this->form->getCSRFFieldName() === $this->name)
            ) {
                return $this->formErrorListFormat;
            }

            return strtr(
                $this->errorListFormatInARow,
                ['%errors_id%' => $this->name . '-errors']
            );
        }

        public function getHelpFormat()
        {
            return strtr(
                $this->helpFormat,
                ['%help_id%' => ($this->name ?? '') . '-help']
            );
        }
    }
}

// ── arWidgetFormInputFileEditable ───────────────────────────────────

if (!class_exists('arWidgetFormInputFileEditable', false)) {
    class arWidgetFormInputFileEditable extends sfWidgetFormInputFile
    {
        public function render($name, $value = null, $attributes = [], $errors = [])
        {
            $input = parent::render($name, $value, $attributes, $errors);

            if (!$this->getOption('edit_mode')) {
                return $input;
            }

            if ($this->getOption('with_delete')) {
                $deleteName = ']' === substr($name, -1)
                    ? substr($name, 0, -1) . '_delete]'
                    : $name . '_delete';

                $delete = $this->renderTag('input', array_merge([
                    'type' => 'checkbox',
                    'name' => $deleteName,
                ], $attributes));
                $deleteLabel = $this->translate($this->getOption('delete_label'));
                $deleteLabel = $this->renderContentTag('i', $deleteLabel);

                return $this->getFileAsTag($attributes) . $delete . $deleteLabel . '<br />' . $input;
            }

            return $this->getFileAsTag($attributes) . $input;
        }

        protected function configure($options = [], $attributes = [])
        {
            parent::configure($options, $attributes);

            $this->setOption('type', 'file');
            $this->setOption('needs_multipart', true);

            $this->addRequiredOption('file_src');
            $this->addOption('file_src', false);
            $this->addOption('is_image', false);
            $this->addOption('edit_mode', true);
            $this->addOption('with_delete', true);
            $this->addOption('delete_label', 'Remove the current file');
        }

        protected function getFileAsTag($attributes)
        {
            if ($this->getOption('is_image')) {
                return false !== $this->getOption('file_src')
                    ? $this->renderTag('img', array_merge(['src' => $this->getOption('file_src')], $attributes))
                    : '';
            }

            return $this->getOption('file_src') ?: '';
        }
    }
}

// ── arB5WidgetFormInputFileEditable ─────────────────────────────────

if (!class_exists('arB5WidgetFormInputFileEditable', false)) {
    class arB5WidgetFormInputFileEditable extends sfWidgetFormInputFile
    {
        public function render($name, $value = null, $attributes = [], $errors = [])
        {
            $input = parent::render($name, $value, $attributes, $errors);

            $deleteField = '';
            if ($this->getOption('with_delete')) {
                $deleteName = ']' === substr($name, -1)
                    ? substr($name, 0, -1) . '_delete]'
                    : $name . '_delete';
                $deleteInput = $this->renderTag('input', [
                    'type' => 'checkbox',
                    'class' => 'form-check-input',
                    'name' => $deleteName,
                ]);
                $deleteLabel = $this->renderContentTag(
                    'label',
                    $this->translate('Remove the current file'),
                    [
                        'class' => 'form-check-label',
                        'for' => $deleteName,
                    ]
                );

                $deleteField = '<div class="form-check mb-3">'
                    . $deleteInput
                    . $deleteLabel
                    . '</div>';
            }

            return $this->getFileAsTag($attributes) . $deleteField . $input;
        }

        protected function configure($options = [], $attributes = [])
        {
            parent::configure($options, $attributes);

            $this->setOption('type', 'file');
            $this->setOption('needs_multipart', true);
            $this->addOption('with_delete', true);
            $this->addRequiredOption('file_src');
            $this->addOption('file_src', false);
        }

        protected function getFileAsTag($attributes)
        {
            if (false !== $this->getOption('file_src')) {
                $img = $this->renderTag('img', [
                    'class' => 'img-thumbnail',
                    'src' => $this->getOption('file_src'),
                ]);

                return '<div class="mb-3">' . $img . '</div>';
            }

            return '';
        }
    }
}

// ── arWidgetFormSelectRadio ─────────────────────────────────────────

if (!class_exists('arWidgetFormSelectRadio', false)) {
    class arWidgetFormSelectRadio extends sfWidgetFormSelectRadio
    {
        // Identical to sfWidgetFormSelectRadio — exists as an alias
        // in AtoM's lib/form/ directory. Some plugins reference it directly.
    }
}

// ── arWidgetFormUploadQuota ─────────────────────────────────────────

if (!class_exists('arWidgetFormUploadQuota', false)) {
    class arWidgetFormUploadQuota extends sfWidgetForm
    {
        protected function configure($options = [], $attributes = [])
        {
            parent::configure($options, $attributes);
            $this->addOption('current_usage', 0);
            $this->addOption('quota_limit', 0);
        }

        public function render($name, $value = null, $attributes = [], $errors = [])
        {
            $usage = $this->getOption('current_usage') ?? 0;
            $limit = $this->getOption('quota_limit') ?? 0;

            $html = '<div class="upload-quota">';
            $html .= '<span class="usage">' . htmlspecialchars((string) $usage, ENT_QUOTES, 'UTF-8') . '</span>';
            if ($limit > 0) {
                $html .= ' / <span class="limit">' . htmlspecialchars((string) $limit, ENT_QUOTES, 'UTF-8') . '</span>';
            }
            $html .= '</div>';

            return $html;
        }
    }
}

// ── sfValidatedFile ─────────────────────────────────────────────────

if (!class_exists('sfValidatedFile', false)) {
    class sfValidatedFile
    {
        protected $originalName;
        protected $type;
        protected $tmpName;
        protected $size;
        protected $path;

        public function __construct($originalName, $type, $tmpName, $size, $path = null)
        {
            $this->originalName = $originalName;
            $this->type = $type;
            $this->tmpName = $tmpName;
            $this->size = $size;
            $this->path = $path;
        }

        public function getOriginalName()
        {
            return $this->originalName;
        }

        public function getType()
        {
            return $this->type;
        }

        public function getTempName()
        {
            return $this->tmpName;
        }

        public function getSize()
        {
            return $this->size;
        }

        public function getOriginalExtension()
        {
            return pathinfo($this->originalName, PATHINFO_EXTENSION);
        }

        public function getExtension()
        {
            return $this->getOriginalExtension();
        }

        public function save($file = null, $fileMode = 0666, $create = true, $dirMode = 0777)
        {
            $file = $file ?: ($this->path ? $this->path . '/' . $this->originalName : null);

            if (null === $file) {
                return false;
            }

            $dir = dirname($file);
            if ($create && !is_dir($dir)) {
                mkdir($dir, $dirMode, true);
            }

            if (copy($this->tmpName, $file)) {
                chmod($file, $fileMode);

                return $file;
            }

            return false;
        }

        public function isSaved()
        {
            return null !== $this->path;
        }
    }
}
