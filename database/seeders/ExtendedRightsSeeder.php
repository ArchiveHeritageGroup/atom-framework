<?php

namespace AtomFramework\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExtendedRightsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRightsStatements();
        $this->seedCreativeCommonsLicenses();
        $this->seedTkLabels();
    }

    protected function seedRightsStatements(): void
    {
        $statements = [
            // In Copyright
            ['uri' => 'http://rightsstatements.org/vocab/InC/1.0/', 'code' => 'InC', 'category' => 'in-copyright', 'icon_filename' => 'InC.png', 'sort_order' => 1,
             'i18n' => ['en' => ['name' => 'In Copyright', 'definition' => 'This Item is protected by copyright and/or related rights.']]],
            ['uri' => 'http://rightsstatements.org/vocab/InC-OW-EU/1.0/', 'code' => 'InC-OW-EU', 'category' => 'in-copyright', 'icon_filename' => 'InC-OW-EU.png', 'sort_order' => 2,
             'i18n' => ['en' => ['name' => 'In Copyright - EU Orphan Work', 'definition' => 'This Item has been identified as an orphan work in the EU.']]],
            ['uri' => 'http://rightsstatements.org/vocab/InC-EDU/1.0/', 'code' => 'InC-EDU', 'category' => 'in-copyright', 'icon_filename' => 'InC-EDU.png', 'sort_order' => 3,
             'i18n' => ['en' => ['name' => 'In Copyright - Educational Use Permitted', 'definition' => 'This Item is protected by copyright but educational use is permitted without authorization.']]],
            ['uri' => 'http://rightsstatements.org/vocab/InC-NC/1.0/', 'code' => 'InC-NC', 'category' => 'in-copyright', 'icon_filename' => 'InC-NC.png', 'sort_order' => 4,
             'i18n' => ['en' => ['name' => 'In Copyright - Non-Commercial Use Permitted', 'definition' => 'This Item is protected by copyright but non-commercial use is permitted.']]],
            ['uri' => 'http://rightsstatements.org/vocab/InC-RUU/1.0/', 'code' => 'InC-RUU', 'category' => 'in-copyright', 'icon_filename' => 'InC-RUU.png', 'sort_order' => 5,
             'i18n' => ['en' => ['name' => 'In Copyright - Rights-holder(s) Unlocatable or Unidentifiable', 'definition' => 'This Item is protected by copyright but the rights-holder(s) cannot be identified or located.']]],
            
            // No Copyright
            ['uri' => 'http://rightsstatements.org/vocab/NoC-CR/1.0/', 'code' => 'NoC-CR', 'category' => 'no-copyright', 'icon_filename' => 'NoC-CR.png', 'sort_order' => 10,
             'i18n' => ['en' => ['name' => 'No Copyright - Contractual Restrictions', 'definition' => 'Use of this Item is not restricted by copyright but there are contractual restrictions.']]],
            ['uri' => 'http://rightsstatements.org/vocab/NoC-NC/1.0/', 'code' => 'NoC-NC', 'category' => 'no-copyright', 'icon_filename' => 'NoC-NC.png', 'sort_order' => 11,
             'i18n' => ['en' => ['name' => 'No Copyright - Non-Commercial Use Only', 'definition' => 'This Item is not protected by copyright but use is restricted to non-commercial purposes.']]],
            ['uri' => 'http://rightsstatements.org/vocab/NoC-OKLR/1.0/', 'code' => 'NoC-OKLR', 'category' => 'no-copyright', 'icon_filename' => 'NoC-OKLR.png', 'sort_order' => 12,
             'i18n' => ['en' => ['name' => 'No Copyright - Other Known Legal Restrictions', 'definition' => 'This Item is not protected by copyright but there are other legal restrictions.']]],
            ['uri' => 'http://rightsstatements.org/vocab/NoC-US/1.0/', 'code' => 'NoC-US', 'category' => 'no-copyright', 'icon_filename' => 'NoC-US.png', 'sort_order' => 13,
             'i18n' => ['en' => ['name' => 'No Copyright - United States', 'definition' => 'This Item is not protected by copyright in the United States.']]],
            
            // Other
            ['uri' => 'http://rightsstatements.org/vocab/CNE/1.0/', 'code' => 'CNE', 'category' => 'other', 'icon_filename' => 'CNE.png', 'sort_order' => 20,
             'i18n' => ['en' => ['name' => 'Copyright Not Evaluated', 'definition' => 'The copyright status of this Item has not been evaluated.']]],
            ['uri' => 'http://rightsstatements.org/vocab/UND/1.0/', 'code' => 'UND', 'category' => 'other', 'icon_filename' => 'UND.png', 'sort_order' => 21,
             'i18n' => ['en' => ['name' => 'Copyright Undetermined', 'definition' => 'The copyright status of this Item could not be determined.']]],
            ['uri' => 'http://rightsstatements.org/vocab/NKC/1.0/', 'code' => 'NKC', 'category' => 'other', 'icon_filename' => 'NKC.png', 'sort_order' => 22,
             'i18n' => ['en' => ['name' => 'No Known Copyright', 'definition' => 'The organization that has made the Item available believes that no copyright restrictions apply.']]],
        ];

        foreach ($statements as $data) {
            $i18n = $data['i18n'];
            unset($data['i18n']);
            
            $id = DB::table('rights_statement')->insertGetId(array_merge($data, [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            
            foreach ($i18n as $culture => $trans) {
                DB::table('rights_statement_i18n')->insert([
                    'rights_statement_id' => $id,
                    'culture' => $culture,
                    'name' => $trans['name'],
                    'definition' => $trans['definition'],
                    'scope_note' => $trans['scope_note'] ?? null,
                ]);
            }
        }
    }

    protected function seedCreativeCommonsLicenses(): void
    {
        $licenses = [
            ['uri' => 'https://creativecommons.org/publicdomain/zero/1.0/', 'code' => 'CC0-1.0', 'version' => '1.0',
             'allows_adaptation' => true, 'allows_commercial' => true, 'requires_attribution' => false, 'requires_sharealike' => false,
             'icon_filename' => 'cc-zero.png', 'sort_order' => 1,
             'i18n' => ['en' => ['name' => 'CC0 1.0 Universal (Public Domain Dedication)', 'description' => 'No rights reserved.']]],
            ['uri' => 'https://creativecommons.org/licenses/by/4.0/', 'code' => 'CC-BY-4.0', 'version' => '4.0',
             'allows_adaptation' => true, 'allows_commercial' => true, 'requires_attribution' => true, 'requires_sharealike' => false,
             'icon_filename' => 'cc-by.png', 'sort_order' => 2,
             'i18n' => ['en' => ['name' => 'Attribution 4.0 International', 'description' => 'You must give appropriate credit.']]],
            ['uri' => 'https://creativecommons.org/licenses/by-sa/4.0/', 'code' => 'CC-BY-SA-4.0', 'version' => '4.0',
             'allows_adaptation' => true, 'allows_commercial' => true, 'requires_attribution' => true, 'requires_sharealike' => true,
             'icon_filename' => 'cc-by-sa.png', 'sort_order' => 3,
             'i18n' => ['en' => ['name' => 'Attribution-ShareAlike 4.0 International', 'description' => 'You must give credit and share under the same license.']]],
            ['uri' => 'https://creativecommons.org/licenses/by-nc/4.0/', 'code' => 'CC-BY-NC-4.0', 'version' => '4.0',
             'allows_adaptation' => true, 'allows_commercial' => false, 'requires_attribution' => true, 'requires_sharealike' => false,
             'icon_filename' => 'cc-by-nc.png', 'sort_order' => 4,
             'i18n' => ['en' => ['name' => 'Attribution-NonCommercial 4.0 International', 'description' => 'Non-commercial use only with attribution.']]],
            ['uri' => 'https://creativecommons.org/licenses/by-nc-sa/4.0/', 'code' => 'CC-BY-NC-SA-4.0', 'version' => '4.0',
             'allows_adaptation' => true, 'allows_commercial' => false, 'requires_attribution' => true, 'requires_sharealike' => true,
             'icon_filename' => 'cc-by-nc-sa.png', 'sort_order' => 5,
             'i18n' => ['en' => ['name' => 'Attribution-NonCommercial-ShareAlike 4.0 International', 'description' => 'Non-commercial, share alike with attribution.']]],
            ['uri' => 'https://creativecommons.org/licenses/by-nd/4.0/', 'code' => 'CC-BY-ND-4.0', 'version' => '4.0',
             'allows_adaptation' => false, 'allows_commercial' => true, 'requires_attribution' => true, 'requires_sharealike' => false,
             'icon_filename' => 'cc-by-nd.png', 'sort_order' => 6,
             'i18n' => ['en' => ['name' => 'Attribution-NoDerivatives 4.0 International', 'description' => 'No derivatives allowed, attribution required.']]],
            ['uri' => 'https://creativecommons.org/licenses/by-nc-nd/4.0/', 'code' => 'CC-BY-NC-ND-4.0', 'version' => '4.0',
             'allows_adaptation' => false, 'allows_commercial' => false, 'requires_attribution' => true, 'requires_sharealike' => false,
             'icon_filename' => 'cc-by-nc-nd.png', 'sort_order' => 7,
             'i18n' => ['en' => ['name' => 'Attribution-NonCommercial-NoDerivatives 4.0 International', 'description' => 'Most restrictive CC license.']]],
            ['uri' => 'https://creativecommons.org/publicdomain/mark/1.0/', 'code' => 'PDM-1.0', 'version' => '1.0',
             'allows_adaptation' => true, 'allows_commercial' => true, 'requires_attribution' => false, 'requires_sharealike' => false,
             'icon_filename' => 'publicdomain.png', 'sort_order' => 8,
             'i18n' => ['en' => ['name' => 'Public Domain Mark 1.0', 'description' => 'This work is free of known copyright restrictions.']]],
        ];

        foreach ($licenses as $data) {
            $i18n = $data['i18n'];
            unset($data['i18n']);
            
            $id = DB::table('creative_commons_license')->insertGetId(array_merge($data, [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            
            foreach ($i18n as $culture => $trans) {
                DB::table('creative_commons_license_i18n')->insert([
                    'creative_commons_license_id' => $id,
                    'culture' => $culture,
                    'name' => $trans['name'],
                    'description' => $trans['description'],
                ]);
            }
        }
    }

    protected function seedTkLabels(): void
    {
        // TK Label Categories
        $categories = [
            ['code' => 'attribution', 'color' => '#0d6efd', 'sort_order' => 1, 'i18n' => ['en' => ['name' => 'Attribution', 'description' => 'Labels related to attribution and acknowledgment.']]],
            ['code' => 'protocol', 'color' => '#198754', 'sort_order' => 2, 'i18n' => ['en' => ['name' => 'Protocol', 'description' => 'Labels related to cultural protocols and permissions.']]],
            ['code' => 'provenance', 'color' => '#ffc107', 'sort_order' => 3, 'i18n' => ['en' => ['name' => 'Provenance', 'description' => 'Labels related to origin and history.']]],
        ];

        $categoryIds = [];
        foreach ($categories as $data) {
            $i18n = $data['i18n'];
            unset($data['i18n']);
            
            $id = DB::table('tk_label_category')->insertGetId(array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $categoryIds[$data['code']] = $id;
            
            foreach ($i18n as $culture => $trans) {
                DB::table('tk_label_category_i18n')->insert([
                    'tk_label_category_id' => $id,
                    'culture' => $culture,
                    'name' => $trans['name'],
                    'description' => $trans['description'],
                ]);
            }
        }

        // TK Labels
        $labels = [
            // Attribution Labels
            ['category' => 'attribution', 'code' => 'TK-A', 'uri' => 'https://localcontexts.org/label/tk-attribution/', 'icon_filename' => 'tk-a.png', 'sort_order' => 1,
             'i18n' => ['en' => ['name' => 'TK Attribution (TK A)', 'description' => 'This Label is being used to correct historical mistakes or exclusions.']]],
            ['category' => 'attribution', 'code' => 'TK-CL', 'uri' => 'https://localcontexts.org/label/tk-clan/', 'icon_filename' => 'tk-cl.png', 'sort_order' => 2,
             'i18n' => ['en' => ['name' => 'TK Clan (TK CL)', 'description' => 'This Label indicates that this material is associated with a clan.']]],
            ['category' => 'attribution', 'code' => 'TK-F', 'uri' => 'https://localcontexts.org/label/tk-family/', 'icon_filename' => 'tk-f.png', 'sort_order' => 3,
             'i18n' => ['en' => ['name' => 'TK Family (TK F)', 'description' => 'This Label indicates family ownership over traditional knowledge.']]],
            
            // Protocol Labels
            ['category' => 'protocol', 'code' => 'TK-MC', 'uri' => 'https://localcontexts.org/label/tk-men-general/', 'icon_filename' => 'tk-mc.png', 'sort_order' => 10,
             'i18n' => ['en' => ['name' => 'TK Men General (TK MC)', 'description' => 'This material has gender restrictions for men.']]],
            ['category' => 'protocol', 'code' => 'TK-WG', 'uri' => 'https://localcontexts.org/label/tk-women-general/', 'icon_filename' => 'tk-wg.png', 'sort_order' => 11,
             'i18n' => ['en' => ['name' => 'TK Women General (TK WG)', 'description' => 'This material has gender restrictions for women.']]],
            ['category' => 'protocol', 'code' => 'TK-SS', 'uri' => 'https://localcontexts.org/label/tk-secret-sacred/', 'icon_filename' => 'tk-ss.png', 'sort_order' => 12,
             'i18n' => ['en' => ['name' => 'TK Secret/Sacred (TK SS)', 'description' => 'This material is secret or sacred to a community.']]],
            ['category' => 'protocol', 'code' => 'TK-CV', 'uri' => 'https://localcontexts.org/label/tk-community-voice/', 'icon_filename' => 'tk-cv.png', 'sort_order' => 13,
             'i18n' => ['en' => ['name' => 'TK Community Voice (TK CV)', 'description' => 'Community protocols apply to this material.']]],
            ['category' => 'protocol', 'code' => 'TK-CS', 'uri' => 'https://localcontexts.org/label/tk-culturally-sensitive/', 'icon_filename' => 'tk-cs.png', 'sort_order' => 14,
             'i18n' => ['en' => ['name' => 'TK Culturally Sensitive (TK CS)', 'description' => 'This material contains culturally sensitive content.']]],
            ['category' => 'protocol', 'code' => 'TK-NC', 'uri' => 'https://localcontexts.org/label/tk-non-commercial/', 'icon_filename' => 'tk-nc.png', 'sort_order' => 15,
             'i18n' => ['en' => ['name' => 'TK Non-Commercial (TK NC)', 'description' => 'This material should not be used for commercial purposes.']]],
            
            // Provenance Labels
            ['category' => 'provenance', 'code' => 'TK-V', 'uri' => 'https://localcontexts.org/label/tk-verified/', 'icon_filename' => 'tk-v.png', 'sort_order' => 20,
             'i18n' => ['en' => ['name' => 'TK Verified (TK V)', 'description' => 'This material has been verified by community authority.']]],
            ['category' => 'provenance', 'code' => 'TK-CO', 'uri' => 'https://localcontexts.org/label/tk-community-use-only/', 'icon_filename' => 'tk-co.png', 'sort_order' => 21,
             'i18n' => ['en' => ['name' => 'TK Community Use Only (TK CO)', 'description' => 'This material is for community use only.']]],
            ['category' => 'provenance', 'code' => 'TK-OC', 'uri' => 'https://localcontexts.org/label/tk-outreach/', 'icon_filename' => 'tk-oc.png', 'sort_order' => 22,
             'i18n' => ['en' => ['name' => 'TK Outreach (TK OC)', 'description' => 'This material is approved for outreach and education.']]],
        ];

        foreach ($labels as $data) {
            $i18n = $data['i18n'];
            $categoryCode = $data['category'];
            unset($data['i18n'], $data['category']);
            
            $id = DB::table('tk_label')->insertGetId(array_merge($data, [
                'tk_label_category_id' => $categoryIds[$categoryCode],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            
            foreach ($i18n as $culture => $trans) {
                DB::table('tk_label_i18n')->insert([
                    'tk_label_id' => $id,
                    'culture' => $culture,
                    'name' => $trans['name'],
                    'description' => $trans['description'],
                    'usage_guide' => $trans['usage_guide'] ?? null,
                ]);
            }
        }
    }
}
