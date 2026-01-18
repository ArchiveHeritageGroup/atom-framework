<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\Security\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

/**
 * Access Justification Template Service
 * 
 * Provides PAIA/POPIA aligned justification templates for access requests.
 * Supports South African archival legislation requirements.
 * 
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class AccessJustificationService
{
    private static ?Logger $logger = null;

    /**
     * PAIA Section references for different request types
     */
    private const PAIA_SECTIONS = [
        'personal' => [
            'section' => 'Section 23',
            'description' => 'Request for access to personal information',
            'reference' => 'PAIA 2000 Section 23 - Right of access to records',
        ],
        'third_party' => [
            'section' => 'Section 34',
            'description' => 'Request involving mandatory protection of third party information',
            'reference' => 'PAIA 2000 Section 34 - Mandatory protection of privacy of third party',
        ],
        'research' => [
            'section' => 'Section 46',
            'description' => 'Request for research or educational purposes',
            'reference' => 'PAIA 2000 Section 46 - Mandatory disclosure in public interest',
        ],
        'legal' => [
            'section' => 'Section 70',
            'description' => 'Request for legal proceedings',
            'reference' => 'PAIA 2000 Section 70 - Legal proceedings',
        ],
    ];

    /**
     * POPIA lawful processing grounds
     */
    private const POPIA_GROUNDS = [
        'consent' => [
            'ground' => 'Consent',
            'section' => 'Section 11(1)(a)',
            'description' => 'Processing with data subject consent',
        ],
        'contract' => [
            'ground' => 'Contract',
            'section' => 'Section 11(1)(b)',
            'description' => 'Processing necessary for contract performance',
        ],
        'legal_obligation' => [
            'ground' => 'Legal Obligation',
            'section' => 'Section 11(1)(c)',
            'description' => 'Processing required by law',
        ],
        'legitimate_interest' => [
            'ground' => 'Legitimate Interest',
            'section' => 'Section 11(1)(d)',
            'description' => 'Processing for legitimate interests of responsible party',
        ],
        'public_function' => [
            'ground' => 'Public Function',
            'section' => 'Section 11(1)(e)',
            'description' => 'Processing for proper administration of public function',
        ],
        'vital_interest' => [
            'ground' => 'Vital Interest',
            'section' => 'Section 11(1)(f)',
            'description' => 'Processing to protect vital interests of data subject',
        ],
    ];

    private static function getLogger(): Logger
    {
        if (null === self::$logger) {
            self::$logger = new Logger('access_justification');
            $logPath = '/var/log/atom/access_justification.log';
            $logDir = dirname($logPath);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            if (is_writable($logDir)) {
                self::$logger->pushHandler(new RotatingFileHandler($logPath, 30, Logger::DEBUG));
            }
        }
        return self::$logger;
    }

    /**
     * Get all available justification templates
     */
    public static function getAllTemplates(): array
    {
        try {
            $templates = DB::table('access_justification_template')
                ->where('active', 1)
                ->orderBy('category')
                ->orderBy('name')
                ->get()
                ->toArray();

            // Include default templates if none exist
            if (empty($templates)) {
                return self::getDefaultTemplates();
            }

            return $templates;

        } catch (\Exception $e) {
            return self::getDefaultTemplates();
        }
    }

    /**
     * Get templates by category
     */
    public static function getTemplatesByCategory(string $category): array
    {
        try {
            return DB::table('access_justification_template')
                ->where('category', $category)
                ->where('active', 1)
                ->orderBy('name')
                ->get()
                ->toArray();

        } catch (\Exception $e) {
            $defaults = self::getDefaultTemplates();
            return array_filter($defaults, fn($t) => $t['category'] === $category);
        }
    }

    /**
     * Get template by ID
     */
    public static function getTemplate(int $templateId): ?object
    {
        return DB::table('access_justification_template')
            ->where('id', $templateId)
            ->first();
    }

    /**
     * Create a new justification template
     */
    public static function createTemplate(
        string $name,
        string $category,
        string $templateText,
        ?string $paiaSection = null,
        ?string $popiaGround = null,
        ?string $requiredEvidence = null,
        int $createdBy = 0
    ): ?int {
        try {
            return DB::table('access_justification_template')->insertGetId([
                'name' => $name,
                'category' => $category,
                'template_text' => $templateText,
                'paia_section' => $paiaSection,
                'popia_ground' => $popiaGround,
                'required_evidence' => $requiredEvidence,
                'active' => 1,
                'created_by' => $createdBy,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to create template', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Update a justification template
     */
    public static function updateTemplate(
        int $templateId,
        array $data,
        int $updatedBy = 0
    ): bool {
        try {
            $data['updated_by'] = $updatedBy;
            $data['updated_at'] = date('Y-m-d H:i:s');

            return DB::table('access_justification_template')
                ->where('id', $templateId)
                ->update($data) !== false;

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to update template', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Populate template with request data
     */
    public static function populateTemplate(int $templateId, array $data): string
    {
        $template = self::getTemplate($templateId);

        if (!$template) {
            return '';
        }

        $text = $template->template_text;

        // Replace placeholders
        $replacements = [
            '{{requester_name}}' => $data['requester_name'] ?? '',
            '{{requester_email}}' => $data['requester_email'] ?? '',
            '{{object_title}}' => $data['object_title'] ?? '',
            '{{object_identifier}}' => $data['object_identifier'] ?? '',
            '{{classification_level}}' => $data['classification_level'] ?? '',
            '{{purpose}}' => $data['purpose'] ?? '',
            '{{date}}' => date('d F Y'),
            '{{organization}}' => $data['organization'] ?? '',
            '{{paia_section}}' => $template->paia_section ?? '',
            '{{popia_ground}}' => $template->popia_ground ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Validate justification against template requirements
     */
    public static function validateJustification(
        string $justification,
        int $templateId
    ): array {
        $template = self::getTemplate($templateId);

        if (!$template) {
            return [
                'valid' => false,
                'errors' => ['Template not found'],
            ];
        }

        $errors = [];
        $warnings = [];

        // Check minimum length
        if (strlen($justification) < 50) {
            $errors[] = 'Justification is too short (minimum 50 characters required)';
        }

        // Check for required evidence if specified
        if ($template->required_evidence) {
            $evidenceItems = json_decode($template->required_evidence, true) ?? [];
            foreach ($evidenceItems as $item) {
                if (stripos($justification, $item) === false) {
                    $warnings[] = "Justification should include reference to: {$item}";
                }
            }
        }

        // PAIA compliance check
        if ($template->paia_section) {
            if (stripos($justification, 'PAIA') === false && stripos($justification, 'Promotion of Access') === false) {
                $warnings[] = 'Consider referencing PAIA legislation for completeness';
            }
        }

        // POPIA compliance check
        if ($template->popia_ground) {
            if (stripos($justification, 'POPIA') === false && stripos($justification, 'Protection of Personal') === false) {
                $warnings[] = 'Consider referencing POPIA legislation for personal data requests';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'template' => [
                'name' => $template->name,
                'paia_section' => $template->paia_section,
                'popia_ground' => $template->popia_ground,
            ],
        ];
    }

    /**
     * Get PAIA sections reference
     */
    public static function getPaiaSections(): array
    {
        return self::PAIA_SECTIONS;
    }

    /**
     * Get POPIA lawful processing grounds
     */
    public static function getPopiaGrounds(): array
    {
        return self::POPIA_GROUNDS;
    }

    /**
     * Get default templates (used when database table doesn't exist)
     */
    public static function getDefaultTemplates(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'PAIA Personal Information Request',
                'category' => 'personal',
                'template_text' => "I, {{requester_name}}, hereby request access to records containing my personal information in accordance with Section 23 of the Promotion of Access to Information Act 2 of 2000 (PAIA).\n\nPurpose of Request: {{purpose}}\n\nI confirm that I am the data subject to whom this personal information relates, and I undertake to use this information solely for the purposes stated above.\n\nDate: {{date}}",
                'paia_section' => 'Section 23',
                'popia_ground' => 'consent',
                'required_evidence' => json_encode(['Identity verification', 'Proof of relationship']),
                'active' => 1,
            ],
            [
                'id' => 2,
                'name' => 'PAIA Research Access Request',
                'category' => 'research',
                'template_text' => "Research Access Request in terms of PAIA Section 46\n\nResearcher: {{requester_name}}\nOrganization: {{organization}}\n\nI request access to {{object_title}} for bona fide research purposes.\n\nResearch Purpose: {{purpose}}\n\nI undertake to:\n- Use the information only for the stated research purpose\n- Maintain confidentiality of any personal information\n- Comply with all applicable ethical research standards\n- Properly cite the source in any publications\n\nDate: {{date}}",
                'paia_section' => 'Section 46',
                'popia_ground' => 'legitimate_interest',
                'required_evidence' => json_encode(['Research proposal', 'Ethics clearance', 'Institutional affiliation']),
                'active' => 1,
            ],
            [
                'id' => 3,
                'name' => 'Legal Proceedings Access Request',
                'category' => 'legal',
                'template_text' => "Legal Access Request in terms of PAIA Section 70\n\nRequester: {{requester_name}}\nCase Reference: [Please insert]\n\nI request access to {{object_title}} ({{object_identifier}}) for use in legal proceedings.\n\nNature of Legal Matter: {{purpose}}\n\nI confirm that this request is made in connection with bona fide legal proceedings and that the information is required for the proper conduct of such proceedings.\n\nDate: {{date}}",
                'paia_section' => 'Section 70',
                'popia_ground' => 'legal_obligation',
                'required_evidence' => json_encode(['Court order or subpoena', 'Legal practitioner credentials', 'Case reference']),
                'active' => 1,
            ],
            [
                'id' => 4,
                'name' => 'Genealogical Research Request',
                'category' => 'personal',
                'template_text' => "Genealogical Research Request\n\nRequester: {{requester_name}}\n\nI request access to records relating to my family history for genealogical research purposes.\n\nRelationship to records: {{purpose}}\n\nI understand that access to personal information of third parties may be limited in terms of POPIA and PAIA, and I undertake to:\n- Respect the privacy of living individuals\n- Use information only for personal genealogical research\n- Not publish personal information without appropriate consent\n\nDate: {{date}}",
                'paia_section' => 'Section 34',
                'popia_ground' => 'consent',
                'required_evidence' => json_encode(['Proof of family relationship', 'Identity document']),
                'active' => 1,
            ],
            [
                'id' => 5,
                'name' => 'Public Interest Disclosure Request',
                'category' => 'public_interest',
                'template_text' => "Public Interest Disclosure Request in terms of PAIA Section 46\n\nRequester: {{requester_name}}\nOrganization: {{organization}}\n\nI request access to {{object_title}} on the grounds that disclosure is in the public interest.\n\nPublic Interest Justification: {{purpose}}\n\nI submit that the public interest in disclosure outweighs any harm that may result from disclosure, based on the following grounds:\n[Please elaborate on public interest grounds]\n\nDate: {{date}}",
                'paia_section' => 'Section 46',
                'popia_ground' => 'public_function',
                'required_evidence' => json_encode(['Public interest justification', 'Media credentials if applicable']),
                'active' => 1,
            ],
            [
                'id' => 6,
                'name' => 'Security Clearance Upgrade Request',
                'category' => 'clearance',
                'template_text' => "Security Clearance Upgrade Request\n\nRequester: {{requester_name}}\nCurrent Clearance Level: {{classification_level}}\n\nI request an upgrade to my security clearance level for the following reasons:\n\n{{purpose}}\n\nI confirm that I:\n- Have completed all required security vetting procedures\n- Understand my responsibilities regarding classified information\n- Will comply with all handling instructions for the requested clearance level\n\nDate: {{date}}",
                'paia_section' => null,
                'popia_ground' => null,
                'required_evidence' => json_encode(['Security vetting certificate', 'Line manager approval', 'Need-to-know justification']),
                'active' => 1,
            ],
        ];
    }

    /**
     * Initialize default templates in database
     */
    public static function initializeDefaultTemplates(): int
    {
        $defaults = self::getDefaultTemplates();
        $count = 0;

        foreach ($defaults as $template) {
            try {
                $exists = DB::table('access_justification_template')
                    ->where('name', $template['name'])
                    ->exists();

                if (!$exists) {
                    unset($template['id']);
                    $template['created_at'] = date('Y-m-d H:i:s');
                    $template['updated_at'] = date('Y-m-d H:i:s');

                    DB::table('access_justification_template')->insert($template);
                    $count++;
                }
            } catch (\Exception $e) {
                self::getLogger()->warning('Failed to insert template', [
                    'name' => $template['name'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Get template categories
     */
    public static function getCategories(): array
    {
        return [
            'personal' => 'Personal Information Request',
            'research' => 'Research Access',
            'legal' => 'Legal Proceedings',
            'public_interest' => 'Public Interest',
            'clearance' => 'Security Clearance',
            'third_party' => 'Third Party Access',
        ];
    }
}
