<?php

declare(strict_types=1);

namespace AtomExtensions\Forms;

/**
 * Report Form Handler - No Symfony dependency.
 *
 * Handles form validation and parameter binding without Symfony.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class ReportFormHandler
{
    private array $fields = [];

    private array $values = [];

    private array $defaults = [];

    private array $errors = [];

    /**
     * Add a field definition.
     */
    public function addField(string $name, string $type, array $options = []): void
    {
        $this->fields[$name] = [
            'type' => $type,
            'options' => $options,
            'required' => $options['required'] ?? false,
        ];
    }

    /**
     * Set default values.
     */
    public function setDefaults(array $defaults): void
    {
        $this->defaults = $defaults;
    }

    /**
     * Bind request data.
     */
    public function bind(array $data): void
    {
        $this->values = array_merge($this->defaults, $data);
        $this->validate();
    }

    /**
     * Validate form data.
     */
    private function validate(): void
    {
        $this->errors = [];

        foreach ($this->fields as $name => $field) {
            $value = $this->values[$name] ?? null;

            // Check required fields
            if ($field['required'] && empty($value)) {
                $this->errors[$name] = "Field {$name} is required";
                continue;
            }

            // Validate by type
            switch ($field['type']) {
                case 'date':
                    if ($value && !$this->isValidDate($value)) {
                        $this->errors[$name] = "Invalid date format for {$name}";
                    }
                    break;

                case 'choice':
                    $choices = $field['options']['choices'] ?? [];
                    if ($value && !in_array($value, $choices) && $value !== '') {
                        $this->errors[$name] = "Invalid choice for {$name}";
                    }
                    break;

                case 'integer':
                    if ($value && !is_numeric($value)) {
                        $this->errors[$name] = "Field {$name} must be a number";
                    }
                    break;
            }
        }
    }

    /**
     * Check if date is valid.
     */
    private function isValidDate(string $date): bool
    {
        // Y-m-d format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return true;
        }

        // d/m/Y format
        if (strpos($date, '/') !== false) {
            $parts = explode('/', $date);
            if (count($parts) === 3) {
                return checkdate((int) $parts[1], (int) $parts[0], (int) $parts[2]);
            }
        }

        return false;
    }

    /**
     * Check if form is valid.
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * Get form value.
     */
    public function getValue(string $name, $default = null)
    {
        return $this->values[$name] ?? $default;
    }

    /**
     * Get all values.
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Get errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get field widget HTML.
     */
    public function renderField(string $name): string
    {
        if (!isset($this->fields[$name])) {
            return '';
        }

        $field = $this->fields[$name];
        $value = $this->values[$name] ?? '';
        $type = $field['type'];
        $options = $field['options'];

        switch ($type) {
            case 'date':
                return sprintf(
                    '<input type="date" name="%s" value="%s" class="form-control" />',
                    htmlspecialchars($name),
                    htmlspecialchars($value)
                );

            case 'choice':
                $html = sprintf('<select name="%s" class="form-control">', htmlspecialchars($name));
                foreach ($options['choices'] as $key => $label) {
                    $selected = ($value == $key) ? 'selected' : '';
                    $html .= sprintf(
                        '<option value="%s" %s>%s</option>',
                        htmlspecialchars($key),
                        $selected,
                        htmlspecialchars($label)
                    );
                }
                $html .= '</select>';

                return $html;

            case 'checkbox':
                $checked = $value ? 'checked' : '';

                return sprintf(
                    '<input type="checkbox" name="%s" value="1" %s />',
                    htmlspecialchars($name),
                    $checked
                );

            case 'hidden':
                return sprintf(
                    '<input type="hidden" name="%s" value="%s" />',
                    htmlspecialchars($name),
                    htmlspecialchars($value)
                );

            case 'text':
            case 'integer':
            default:
                return sprintf(
                    '<input type="text" name="%s" value="%s" class="form-control" />',
                    htmlspecialchars($name),
                    htmlspecialchars($value)
                );
        }
    }
}
