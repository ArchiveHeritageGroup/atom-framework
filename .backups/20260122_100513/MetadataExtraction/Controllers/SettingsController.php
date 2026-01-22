<?php

declare(strict_types=1);

namespace AtomExtensions\Extensions\MetadataExtraction\Controllers;

use AtomExtensions\Helpers\CultureHelper;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Settings Controller.
 *
 * Handles metadata extraction settings.
 * Pure Laravel Query Builder implementation.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class SettingsController
{
    protected const SCOPE = 'metadata_extraction';

    /**
     * Handle settings page.
     */
    public function handleSettings($request, $action): void
    {
        $action->response->setTitle('Metadata extraction settings - ' . $action->response->getTitle());

        // Create the form
        $action->form = new \sfForm();
        $action->form->getValidatorSchema()->setOption('allow_extra_fields', true);

        // Add all settings fields
        $this->addFormFields($action->form);

        // Load current values
        $this->loadFormDefaults($action->form);

        // Handle POST
        if ($request->isMethod('post')) {
            $action->form->bind($request->getPostParameters());

            if ($action->form->isValid()) {
                $this->saveSettings($action->form);
                $action->getUser()->setFlash('notice', 'Metadata extraction settings saved.');
                $action->redirect('settings/metadataExtraction');
            }
        }
    }

    /**
     * Add form fields.
     */
    protected function addFormFields(\sfForm $form): void
    {
        // Boolean checkboxes
        $checkboxSettings = [
            'metadata_extraction_enabled',
            'extract_exif',
            'extract_iptc',
            'extract_xmp',
            'overwrite_title',
            'overwrite_description',
            'auto_generate_keywords',
            'extract_gps_coordinates',
            'add_technical_metadata',
        ];

        foreach ($checkboxSettings as $name) {
            $form->setWidget($name, new \sfWidgetFormInputCheckbox());
            $form->setValidator($name, new \sfValidatorBoolean(['required' => false]));
        }

        // Target field dropdown
        $form->setWidget(
            'technical_metadata_target_field',
            new \sfWidgetFormSelect([
                'choices' => [
                    'physical_characteristics' => 'Physical characteristics',
                    'scope_and_content' => 'Scope and content',
                    'appraisal' => 'Appraisal, destruction and scheduling',
                    'archivists_notes' => 'Archivist\'s notes',
                    'general_note' => 'General note',
                ],
            ])
        );

        $form->setValidator(
            'technical_metadata_target_field',
            new \sfValidatorChoice([
                'choices' => [
                    'physical_characteristics',
                    'scope_and_content',
                    'appraisal',
                    'archivists_notes',
                    'general_note',
                ],
            ])
        );
    }

    /**
     * Load default values from database.
     */
    protected function loadFormDefaults(\sfForm $form): void
    {
        $defaults = [
            'metadata_extraction_enabled' => true,
            'extract_exif' => true,
            'extract_iptc' => true,
            'extract_xmp' => true,
            'overwrite_title' => false,
            'overwrite_description' => false,
            'auto_generate_keywords' => true,
            'extract_gps_coordinates' => true,
            'add_technical_metadata' => true,
            'technical_metadata_target_field' => 'physical_characteristics',
        ];

        foreach ($defaults as $name => $default) {
            $value = $this->getSetting($name);

            if ($value !== null) {
                // Convert string boolean to actual boolean
                if ($value === '1') {
                    $value = true;
                } elseif ($value === '0') {
                    $value = false;
                }

                $form->setDefault($name, $value);
            } else {
                $form->setDefault($name, $default);
            }
        }
    }

    /**
     * Save settings to database.
     */
    protected function saveSettings(\sfForm $form): void
    {
        $settingNames = [
            'metadata_extraction_enabled',
            'extract_exif',
            'extract_iptc',
            'extract_xmp',
            'overwrite_title',
            'overwrite_description',
            'auto_generate_keywords',
            'extract_gps_coordinates',
            'add_technical_metadata',
            'technical_metadata_target_field',
        ];

        foreach ($settingNames as $name) {
            $value = $form->getValue($name);

            // Convert boolean to string
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            
            // Handle null/empty checkbox values
            if ($value === null) {
                $value = '0';
            }

            $this->saveSetting($name, (string) $value);
        }
    }

    /**
     * Get a setting value from database.
     */
    protected function getSetting(string $name): ?string
    {
        return DB::table('setting')
            ->join('setting_i18n', 'setting.id', '=', 'setting_i18n.id')
            ->where('setting.name', $name)
            ->where('setting.scope', self::SCOPE)
            ->where('setting_i18n.culture', CultureHelper::getCulture())
            ->value('setting_i18n.value');
    }

    /**
     * Save a setting to database.
     */
    protected function saveSetting(string $name, string $value): void
    {
        // Check if setting exists
        $existing = DB::table('setting')
            ->where('name', $name)
            ->where('scope', self::SCOPE)
            ->first();

        if ($existing) {
            // Update existing
            $existingI18n = DB::table('setting_i18n')
                ->where('id', $existing->id)
                ->where('culture', CultureHelper::getCulture())
                ->first();

            if ($existingI18n) {
                DB::table('setting_i18n')
                    ->where('id', $existing->id)
                    ->where('culture', CultureHelper::getCulture())
                    ->update(['value' => $value]);
            } else {
                DB::table('setting_i18n')->insert([
                    'id' => $existing->id,
                    'culture' => 'en',
                    'value' => $value,
                ]);
            }
        } else {
            // Create new setting
            $settingId = DB::table('object')->insertGetId([
                'class_name' => 'QubitSetting',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            DB::table('setting')->insert([
                'id' => $settingId,
                'name' => $name,
                'scope' => self::SCOPE,
            ]);

            DB::table('setting_i18n')->insert([
                'id' => $settingId,
                'culture' => 'en',
                'value' => $value,
            ]);
        }
    }
}