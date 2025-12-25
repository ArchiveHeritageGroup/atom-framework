<?php

declare(strict_types=1);

namespace AtoM\Framework\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * Service for managing condition assessment templates.
 * Supports material-specific assessment forms following Spectrum 5.0 standards.
 */
class ConditionTemplateService
{
    private Logger $logger;

    public function __construct()
    {
        $this->logger = new Logger('condition-template');
        $this->logger->pushHandler(
            new RotatingFileHandler('/var/log/atom/condition.log', 30, Logger::DEBUG)
        );
    }

    /**
     * Get all active templates.
     */
    public function getAllTemplates(bool $activeOnly = true): array
    {
        $query = DB::table('spectrum_condition_template')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($activeOnly) {
            $query->where('is_active', 1);
        }

        return $query->get()->toArray();
    }

    /**
     * Get templates by material type.
     */
    public function getTemplatesByMaterial(string $materialType): array
    {
        return DB::table('spectrum_condition_template')
            ->where('material_type', $materialType)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    /**
     * Get template by ID with full structure (sections and fields).
     */
    public function getTemplate(int $templateId): ?object
    {
        $template = DB::table('spectrum_condition_template')
            ->where('id', $templateId)
            ->first();

        if (!$template) {
            return null;
        }

        $template->sections = $this->getTemplateSections($templateId);

        return $template;
    }

    /**
     * Get template by code.
     */
    public function getTemplateByCode(string $code): ?object
    {
        $template = DB::table('spectrum_condition_template')
            ->where('code', $code)
            ->first();

        if (!$template) {
            return null;
        }

        $template->sections = $this->getTemplateSections($template->id);

        return $template;
    }

    /**
     * Get default template for a material type.
     */
    public function getDefaultTemplate(string $materialType): ?object
    {
        $template = DB::table('spectrum_condition_template')
            ->where('material_type', $materialType)
            ->where('is_default', 1)
            ->where('is_active', 1)
            ->first();

        // Fallback to first active template for material
        if (!$template) {
            $template = DB::table('spectrum_condition_template')
                ->where('material_type', $materialType)
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->first();
        }

        // Fallback to general template
        if (!$template) {
            $template = DB::table('spectrum_condition_template')
                ->where('material_type', 'general')
                ->where('is_active', 1)
                ->first();
        }

        if ($template) {
            $template->sections = $this->getTemplateSections($template->id);
        }

        return $template;
    }

    /**
     * Get template sections with fields.
     */
    public function getTemplateSections(int $templateId): array
    {
        $sections = DB::table('spectrum_condition_template_section')
            ->where('template_id', $templateId)
            ->orderBy('sort_order')
            ->get()
            ->toArray();

        foreach ($sections as &$section) {
            $section->fields = $this->getSectionFields($section->id);
        }

        return $sections;
    }

    /**
     * Get fields for a section.
     */
    public function getSectionFields(int $sectionId): array
    {
        $fields = DB::table('spectrum_condition_template_field')
            ->where('section_id', $sectionId)
            ->orderBy('sort_order')
            ->get()
            ->toArray();

        // Decode JSON options
        foreach ($fields as &$field) {
            if ($field->options) {
                $field->options = json_decode($field->options, true);
            }
            if ($field->validation_rules) {
                $field->validation_rules = json_decode($field->validation_rules, true);
            }
        }

        return $fields;
    }

    /**
     * Get available material types.
     */
    public function getMaterialTypes(): array
    {
        return DB::table('spectrum_condition_template')
            ->select('material_type')
            ->where('is_active', 1)
            ->distinct()
            ->orderBy('material_type')
            ->pluck('material_type')
            ->toArray();
    }

    /**
     * Save condition check template data.
     */
    public function saveCheckData(int $checkId, int $templateId, array $fieldData): bool
    {
        try {
            

            // Update condition check with template reference
            DB::table('spectrum_condition_check')
                ->where('id', $checkId)
                ->update([
                    'template_id' => $templateId,
                    'template_data' => json_encode($fieldData),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            file_put_contents('/tmp/template_debug.log', "Service: template_data update done\n", FILE_APPEND);

            // Save individual field values
            foreach ($fieldData as $fieldId => $value) {
                file_put_contents('/tmp/template_debug.log', "Processing field: $fieldId = " . print_r($value, true) . "\n", FILE_APPEND);
                if (!is_numeric($fieldId)) {
                    continue;
                }

                $valueStr = is_array($value) ? json_encode($value) : (string) $value;

                DB::table('spectrum_condition_check_data')
                    ->updateOrInsert(
                        [
                            'condition_check_id' => $checkId,
                            'field_id' => (int) $fieldId,
                        ],
                        [
                            'template_id' => $templateId,
                            'field_value' => $valueStr,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]
                    );
            }

            

            $this->logger->info('Condition check data saved', [
                'check_id' => $checkId,
                'template_id' => $templateId,
                'fields_count' => count($fieldData),
            ]);

            return true;
        } catch (\Exception $e) {
            
            $this->logger->error('Failed to save check data', [
                'check_id' => $checkId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get condition check template data.
     */
    public function getCheckData(int $checkId): array
    {
        $data = DB::table('spectrum_condition_check_data as d')
            ->join('spectrum_condition_template_field as f', 'd.field_id', '=', 'f.id')
            ->where('d.condition_check_id', $checkId)
            ->select('d.field_id', 'd.field_value', 'f.field_name', 'f.field_type')
            ->get()
            ->toArray();

        $result = [];
        foreach ($data as $row) {
            $value = $row->field_value;

            // Decode JSON for multi-value fields
            if (in_array($row->field_type, ['multiselect', 'checkbox']) && $value) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                }
            }

            $result[$row->field_id] = $value;
            $result[$row->field_name] = $value;
        }

        return $result;
    }

    /**
     * Create a new template.
     */
    public function createTemplate(array $data, ?int $userId = null): ?int
    {
        try {
            $templateId = DB::table('spectrum_condition_template')->insertGetId([
                'name' => $data['name'],
                'code' => $data['code'],
                'material_type' => $data['material_type'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? 1,
                'is_default' => $data['is_default'] ?? 0,
                'sort_order' => $data['sort_order'] ?? 0,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $userId,
            ]);

            $this->logger->info('Template created', ['template_id' => $templateId]);

            return $templateId;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create template', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Add section to template.
     */
    public function addSection(int $templateId, array $data): ?int
    {
        try {
            return DB::table('spectrum_condition_template_section')->insertGetId([
                'template_id' => $templateId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_required' => $data['is_required'] ?? 0,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to add section', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Add field to section.
     */
    public function addField(int $sectionId, array $data): ?int
    {
        try {
            return DB::table('spectrum_condition_template_field')->insertGetId([
                'section_id' => $sectionId,
                'field_name' => $data['field_name'],
                'field_label' => $data['field_label'],
                'field_type' => $data['field_type'],
                'options' => isset($data['options']) ? json_encode($data['options']) : null,
                'default_value' => $data['default_value'] ?? null,
                'placeholder' => $data['placeholder'] ?? null,
                'help_text' => $data['help_text'] ?? null,
                'is_required' => $data['is_required'] ?? 0,
                'validation_rules' => isset($data['validation_rules']) ? json_encode($data['validation_rules']) : null,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to add field', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Update template.
     */
    public function updateTemplate(int $templateId, array $data): bool
    {
        try {
            $updateData = array_filter([
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : null,
                'is_default' => isset($data['is_default']) ? (int) $data['is_default'] : null,
                'sort_order' => $data['sort_order'] ?? null,
                'updated_at' => date('Y-m-d H:i:s'),
            ], fn($v) => $v !== null);

            if (empty($updateData)) {
                return false;
            }

            return DB::table('spectrum_condition_template')
                ->where('id', $templateId)
                ->update($updateData) !== false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to update template', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Delete template.
     */
    public function deleteTemplate(int $templateId): bool
    {
        try {
            // Check if template is in use
            $inUse = DB::table('spectrum_condition_check')
                ->where('template_id', $templateId)
                ->exists();

            if ($inUse) {
                // Soft delete - just deactivate
                return $this->updateTemplate($templateId, ['is_active' => 0]);
            }

            // Hard delete
            return DB::table('spectrum_condition_template')
                ->where('id', $templateId)
                ->delete() > 0;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete template', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Generate HTML form for template.
     */
    public function renderForm(object $template, array $existingData = [], bool $readonly = false): string
    {
        $html = '<div class="condition-template-form" data-template-id="' . $template->id . '">';

        foreach ($template->sections as $section) {
            $html .= $this->renderSection($section, $existingData, $readonly);
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render a section with its fields.
     */
    private function renderSection(object $section, array $existingData, bool $readonly): string
    {
        $required = $section->is_required ? '<span class="text-danger">*</span>' : '';

        $html = '<div class="condition-section card mb-3" data-section-id="' . $section->id . '">';
        $html .= '<div class="card-header bg-light">';
        $html .= '<h5 class="mb-0">' . htmlspecialchars($section->name) . ' ' . $required . '</h5>';
        if ($section->description) {
            $html .= '<small class="text-muted">' . htmlspecialchars($section->description) . '</small>';
        }
        $html .= '</div>';
        $html .= '<div class="card-body">';

        foreach ($section->fields as $field) {
            $html .= $this->renderField($field, $existingData, $readonly);
        }

        $html .= '</div></div>';

        return $html;
    }

    /**
     * Render a single field.
     */
    private function renderField(object $field, array $existingData, bool $readonly): string
    {
        $value = $existingData[$field->id] ?? $existingData[$field->field_name] ?? $field->default_value ?? '';
        $required = $field->is_required ? 'required' : '';
        $disabled = $readonly ? 'disabled' : '';
        $requiredMark = $field->is_required ? '<span class="text-danger">*</span>' : '';

        $fieldName = 'template_field[' . $field->id . ']';
        $fieldId = 'field_' . $field->id;

        $html = '<div class="mb-3 condition-field" data-field-id="' . $field->id . '">';
        $html .= '<label class="form-label" for="' . $fieldId . '">' . htmlspecialchars($field->field_label) . ' ' . $requiredMark . '</label>';

        switch ($field->field_type) {
            case 'text':
                $html .= '<input type="text" class="form-control" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '" placeholder="' . htmlspecialchars($field->placeholder ?? '') . '" ' . $required . ' ' . $disabled . '>';
                break;

            case 'textarea':
                $html .= '<textarea class="form-control" id="' . $fieldId . '" name="' . $fieldName . '" rows="3" placeholder="' . htmlspecialchars($field->placeholder ?? '') . '" ' . $required . ' ' . $disabled . '>' . htmlspecialchars((string) $value) . '</textarea>';
                break;

            case 'number':
                $html .= '<input type="number" class="form-control" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '" ' . $required . ' ' . $disabled . '>';
                break;

            case 'date':
                $html .= '<input type="date" class="form-control" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '" ' . $required . ' ' . $disabled . '>';
                break;

            case 'select':
                $html .= '<select class="form-select" id="' . $fieldId . '" name="' . $fieldName . '" ' . $required . ' ' . $disabled . '>';
                $html .= '<option value="">-- Select --</option>';
                if (is_array($field->options)) {
                    foreach ($field->options as $option) {
                        $selected = ($value === $option) ? 'selected' : '';
                        $html .= '<option value="' . htmlspecialchars($option) . '" ' . $selected . '>' . htmlspecialchars($option) . '</option>';
                    }
                }
                $html .= '</select>';
                break;

            case 'multiselect':
                $selectedValues = is_array($value) ? $value : [];
                $html .= '<select class="form-select" id="' . $fieldId . '" name="' . $fieldName . '[]" multiple ' . $required . ' ' . $disabled . ' size="5">';
                if (is_array($field->options)) {
                    foreach ($field->options as $option) {
                        $selected = in_array($option, $selectedValues) ? 'selected' : '';
                        $html .= '<option value="' . htmlspecialchars($option) . '" ' . $selected . '>' . htmlspecialchars($option) . '</option>';
                    }
                }
                $html .= '</select>';
                $html .= '<small class="form-text text-muted">Hold Ctrl/Cmd to select multiple</small>';
                break;

            case 'radio':
                if (is_array($field->options)) {
                    foreach ($field->options as $i => $option) {
                        $checked = ($value === $option) ? 'checked' : '';
                        $html .= '<div class="form-check">';
                        $html .= '<input class="form-check-input" type="radio" id="' . $fieldId . '_' . $i . '" name="' . $fieldName . '" value="' . htmlspecialchars($option) . '" ' . $checked . ' ' . $disabled . '>';
                        $html .= '<label class="form-check-label" for="' . $fieldId . '_' . $i . '">' . htmlspecialchars($option) . '</label>';
                        $html .= '</div>';
                    }
                }
                break;

            case 'checkbox':
                if (is_array($field->options)) {
                    $selectedValues = is_array($value) ? $value : [];
                    foreach ($field->options as $i => $option) {
                        $checked = in_array($option, $selectedValues) ? 'checked' : '';
                        $html .= '<div class="form-check">';
                        $html .= '<input class="form-check-input" type="checkbox" id="' . $fieldId . '_' . $i . '" name="' . $fieldName . '[]" value="' . htmlspecialchars($option) . '" ' . $checked . ' ' . $disabled . '>';
                        $html .= '<label class="form-check-label" for="' . $fieldId . '_' . $i . '">' . htmlspecialchars($option) . '</label>';
                        $html .= '</div>';
                    }
                } else {
                    // Single checkbox
                    $checked = $value ? 'checked' : '';
                    $html .= '<div class="form-check">';
                    $html .= '<input class="form-check-input" type="checkbox" id="' . $fieldId . '" name="' . $fieldName . '" value="1" ' . $checked . ' ' . $disabled . '>';
                    $html .= '<label class="form-check-label" for="' . $fieldId . '">Yes</label>';
                    $html .= '</div>';
                }
                break;

            case 'rating':
                $ratingOptions = $field->options ?? ['min' => 1, 'max' => 5, 'labels' => []];
                $min = $ratingOptions['min'] ?? 1;
                $max = $ratingOptions['max'] ?? 5;
                $labels = $ratingOptions['labels'] ?? [];

                $html .= '<div class="rating-field d-flex align-items-center gap-2">';
                for ($i = $min; $i <= $max; $i++) {
                    $checked = ((int) $value === $i) ? 'checked' : '';
                    $label = $labels[$i - $min] ?? $i;
                    $html .= '<div class="form-check form-check-inline">';
                    $html .= '<input class="form-check-input" type="radio" id="' . $fieldId . '_' . $i . '" name="' . $fieldName . '" value="' . $i . '" ' . $checked . ' ' . $disabled . '>';
                    $html .= '<label class="form-check-label" for="' . $fieldId . '_' . $i . '">' . htmlspecialchars((string) $label) . '</label>';
                    $html .= '</div>';
                }
                $html .= '</div>';
                break;
        }

        if ($field->help_text) {
            $html .= '<small class="form-text text-muted">' . htmlspecialchars($field->help_text) . '</small>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Export template as JSON for backup/sharing.
     */
    public function exportTemplate(int $templateId): ?array
    {
        $template = $this->getTemplate($templateId);

        if (!$template) {
            return null;
        }

        return [
            'name' => $template->name,
            'code' => $template->code,
            'material_type' => $template->material_type,
            'description' => $template->description,
            'sections' => array_map(function ($section) {
                return [
                    'name' => $section->name,
                    'description' => $section->description,
                    'is_required' => $section->is_required,
                    'sort_order' => $section->sort_order,
                    'fields' => array_map(function ($field) {
                        return [
                            'field_name' => $field->field_name,
                            'field_label' => $field->field_label,
                            'field_type' => $field->field_type,
                            'options' => $field->options,
                            'default_value' => $field->default_value,
                            'placeholder' => $field->placeholder,
                            'help_text' => $field->help_text,
                            'is_required' => $field->is_required,
                            'validation_rules' => $field->validation_rules,
                            'sort_order' => $field->sort_order,
                        ];
                    }, $section->fields),
                ];
            }, $template->sections),
        ];
    }

    /**
     * Import template from JSON.
     */
    public function importTemplate(array $data, ?int $userId = null): ?int
    {
        try {
            

            // Create template
            $templateId = $this->createTemplate([
                'name' => $data['name'],
                'code' => $data['code'] . '_' . time(), // Ensure unique code
                'material_type' => $data['material_type'],
                'description' => $data['description'] ?? null,
            ], $userId);

            if (!$templateId) {
                throw new \Exception('Failed to create template');
            }

            // Create sections and fields
            foreach ($data['sections'] ?? [] as $sectionData) {
                $sectionId = $this->addSection($templateId, [
                    'name' => $sectionData['name'],
                    'description' => $sectionData['description'] ?? null,
                    'is_required' => $sectionData['is_required'] ?? 0,
                    'sort_order' => $sectionData['sort_order'] ?? 0,
                ]);

                if (!$sectionId) {
                    throw new \Exception('Failed to create section');
                }

                foreach ($sectionData['fields'] ?? [] as $fieldData) {
                    $fieldId = $this->addField($sectionId, $fieldData);
                    if (!$fieldId) {
                        throw new \Exception('Failed to create field');
                    }
                }
            }

            

            $this->logger->info('Template imported', ['template_id' => $templateId]);

            return $templateId;
        } catch (\Exception $e) {
            
            $this->logger->error('Failed to import template', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
