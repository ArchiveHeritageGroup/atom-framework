<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\Privacy\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

/**
 * Privacy Compliance Service
 * 
 * Comprehensive privacy compliance management for POPIA (South Africa),
 * PAIA (South Africa), and GDPR (EU) requirements.
 * 
 * Implements:
 * - Record of Processing Activities (ROPA)
 * - Data Subject Access Requests (DSAR) tracking
 * - Breach log and incident register
 * - Template library for privacy documents
 * 
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class PrivacyComplianceService
{
    private static ?Logger $logger = null;

    /**
     * POPIA lawful processing conditions (Section 11)
     */
    public const POPIA_LAWFUL_GROUNDS = [
        'consent' => [
            'code' => 'POPIA-11(1)(a)',
            'label' => 'Consent',
            'description' => 'Data subject has consented to the processing',
        ],
        'contract' => [
            'code' => 'POPIA-11(1)(b)',
            'label' => 'Contractual Necessity',
            'description' => 'Processing necessary for contract performance',
        ],
        'legal_obligation' => [
            'code' => 'POPIA-11(1)(c)',
            'label' => 'Legal Obligation',
            'description' => 'Processing required by law',
        ],
        'legitimate_interest' => [
            'code' => 'POPIA-11(1)(d)',
            'label' => 'Legitimate Interest',
            'description' => 'Processing for legitimate interests of responsible party',
        ],
        'public_function' => [
            'code' => 'POPIA-11(1)(e)',
            'label' => 'Public Function',
            'description' => 'Processing for proper administration of public function',
        ],
        'vital_interest' => [
            'code' => 'POPIA-11(1)(f)',
            'label' => 'Vital Interest',
            'description' => 'Processing to protect vital interests of data subject',
        ],
    ];

    /**
     * DSAR request types
     */
    public const DSAR_TYPES = [
        'access' => 'Right of Access (POPIA S23 / GDPR Art.15)',
        'rectification' => 'Right to Rectification (POPIA S24 / GDPR Art.16)',
        'erasure' => 'Right to Erasure (POPIA S24 / GDPR Art.17)',
        'restriction' => 'Right to Restriction (GDPR Art.18)',
        'portability' => 'Right to Data Portability (GDPR Art.20)',
        'objection' => 'Right to Object (POPIA S11(3) / GDPR Art.21)',
        'automated_decision' => 'Automated Decision Making (POPIA S71 / GDPR Art.22)',
    ];

    private static function getLogger(): Logger
    {
        if (null === self::$logger) {
            self::$logger = new Logger('privacy_compliance');
            $logPath = '/var/log/atom/privacy_compliance.log';
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

    // ==================== ROPA (Record of Processing Activities) ====================

    /**
     * Create a processing activity record
     */
    public static function createProcessingActivity(array $data, int $createdBy = 0): ?int
    {
        try {
            return DB::table('privacy_processing_activity')->insertGetId([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'repository_id' => $data['repository_id'] ?? null,
                'purpose' => $data['purpose'],
                'lawful_basis' => $data['lawful_basis'],
                'lawful_basis_detail' => $data['lawful_basis_detail'] ?? null,
                'data_categories' => json_encode($data['data_categories'] ?? []),
                'data_subjects' => json_encode($data['data_subjects'] ?? []),
                'recipients' => json_encode($data['recipients'] ?? []),
                'international_transfers' => $data['international_transfers'] ?? 0,
                'transfer_safeguards' => $data['transfer_safeguards'] ?? null,
                'retention_period' => $data['retention_period'] ?? null,
                'retention_basis' => $data['retention_basis'] ?? null,
                'security_measures' => json_encode($data['security_measures'] ?? []),
                'classification_link' => $data['classification_link'] ?? null,
                'dpia_required' => $data['dpia_required'] ?? 0,
                'dpia_completed' => $data['dpia_completed'] ?? 0,
                'dpia_date' => $data['dpia_date'] ?? null,
                'responsible_person' => $data['responsible_person'] ?? null,
                'information_officer' => $data['information_officer'] ?? null,
                'last_review_date' => $data['last_review_date'] ?? date('Y-m-d'),
                'next_review_date' => $data['next_review_date'] ?? date('Y-m-d', strtotime('+1 year')),
                'status' => $data['status'] ?? 'active',
                'created_by' => $createdBy,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to create processing activity', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get all processing activities (ROPA)
     */
    public static function getProcessingActivities(?int $repositoryId = null): array
    {
        $query = DB::table('privacy_processing_activity')
            ->orderBy('name');

        if ($repositoryId) {
            $query->where('repository_id', $repositoryId);
        }

        return $query->get()->toArray();
    }

    /**
     * Get processing activity by ID
     */
    public static function getProcessingActivity(int $id): ?object
    {
        return DB::table('privacy_processing_activity')
            ->where('id', $id)
            ->first();
    }

    /**
     * Update processing activity
     */
    public static function updateProcessingActivity(int $id, array $data, int $updatedBy = 0): bool
    {
        try {
            // Encode JSON fields
            if (isset($data['data_categories'])) {
                $data['data_categories'] = json_encode($data['data_categories']);
            }
            if (isset($data['data_subjects'])) {
                $data['data_subjects'] = json_encode($data['data_subjects']);
            }
            if (isset($data['recipients'])) {
                $data['recipients'] = json_encode($data['recipients']);
            }
            if (isset($data['security_measures'])) {
                $data['security_measures'] = json_encode($data['security_measures']);
            }

            $data['updated_by'] = $updatedBy;
            $data['updated_at'] = date('Y-m-d H:i:s');

            return DB::table('privacy_processing_activity')
                ->where('id', $id)
                ->update($data) !== false;

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to update processing activity', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ==================== DSAR (Data Subject Access Requests) ====================

    /**
     * Create a DSAR request
     */
    public static function createDsarRequest(array $data, int $createdBy = 0): ?int
    {
        try {
            // Calculate deadline (POPIA: 30 days, GDPR: 1 month)
            $deadline = $data['deadline'] ?? date('Y-m-d', strtotime('+30 days'));

            $requestId = DB::table('privacy_dsar_request')->insertGetId([
                'reference_number' => self::generateDsarReference(),
                'request_type' => $data['request_type'],
                'data_subject_name' => $data['data_subject_name'],
                'data_subject_email' => $data['data_subject_email'] ?? null,
                'data_subject_id_type' => $data['data_subject_id_type'] ?? null,
                'data_subject_id_number' => $data['data_subject_id_number'] ?? null,
                'id_verified' => $data['id_verified'] ?? 0,
                'id_verified_by' => $data['id_verified_by'] ?? null,
                'id_verified_date' => $data['id_verified_date'] ?? null,
                'request_details' => $data['request_details'],
                'scope' => json_encode($data['scope'] ?? []),
                'received_date' => $data['received_date'] ?? date('Y-m-d'),
                'deadline' => $deadline,
                'status' => 'pending',
                'assigned_to' => $data['assigned_to'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $createdBy,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Log the request creation
            self::logDsarAction($requestId, 'created', $createdBy, 'DSAR request created');

            return $requestId;

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to create DSAR request', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get all DSAR requests
     */
    public static function getDsarRequests(?string $status = null): array
    {
        $query = DB::table('privacy_dsar_request as d')
            ->leftJoin('user as u', 'd.assigned_to', '=', 'u.id')
            ->select([
                'd.*',
                'u.username as assigned_to_name',
            ])
            ->orderByDesc('d.created_at');

        if ($status) {
            $query->where('d.status', $status);
        }

        return $query->get()->toArray();
    }

    /**
     * Get DSAR request by ID
     */
    public static function getDsarRequest(int $id): ?object
    {
        return DB::table('privacy_dsar_request as d')
            ->leftJoin('user as u', 'd.assigned_to', '=', 'u.id')
            ->leftJoin('user as v', 'd.id_verified_by', '=', 'v.id')
            ->where('d.id', $id)
            ->select([
                'd.*',
                'u.username as assigned_to_name',
                'v.username as verified_by_name',
            ])
            ->first();
    }

    /**
     * Update DSAR request status
     */
    public static function updateDsarStatus(
        int $id,
        string $status,
        ?string $response = null,
        int $updatedBy = 0
    ): bool {
        try {
            $updateData = [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($status === 'completed') {
                $updateData['completed_date'] = date('Y-m-d H:i:s');
                $updateData['response'] = $response;
            }

            if ($status === 'rejected') {
                $updateData['rejection_reason'] = $response;
            }

            DB::table('privacy_dsar_request')
                ->where('id', $id)
                ->update($updateData);

            self::logDsarAction($id, $status, $updatedBy, $response);

            return true;

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to update DSAR status', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get DSAR log
     */
    public static function getDsarLog(int $requestId): array
    {
        return DB::table('privacy_dsar_log as l')
            ->leftJoin('user as u', 'l.user_id', '=', 'u.id')
            ->where('l.request_id', $requestId)
            ->select(['l.*', 'u.username'])
            ->orderByDesc('l.created_at')
            ->get()
            ->toArray();
    }

    /**
     * Log DSAR action
     */
    private static function logDsarAction(
        int $requestId,
        string $action,
        int $userId,
        ?string $details = null
    ): void {
        try {
            DB::table('privacy_dsar_log')->insert([
                'request_id' => $requestId,
                'action' => $action,
                'user_id' => $userId,
                'details' => $details,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            self::getLogger()->warning('Failed to log DSAR action', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate DSAR reference number
     */
    private static function generateDsarReference(): string
    {
        $year = date('Y');
        $month = date('m');

        // Get next sequence number for this month
        $lastRef = DB::table('privacy_dsar_request')
            ->where('reference_number', 'LIKE', "DSAR-{$year}{$month}-%")
            ->orderByDesc('id')
            ->value('reference_number');

        if ($lastRef) {
            $sequence = (int) substr($lastRef, -4) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('DSAR-%s%s-%04d', $year, $month, $sequence);
    }

    // ==================== Breach Log ====================

    /**
     * Create a breach incident record
     */
    public static function createBreachIncident(array $data, int $createdBy = 0): ?int
    {
        try {
            $incidentId = DB::table('privacy_breach_incident')->insertGetId([
                'reference_number' => self::generateBreachReference(),
                'incident_date' => $data['incident_date'],
                'discovery_date' => $data['discovery_date'],
                'reported_date' => date('Y-m-d'),
                'incident_type' => $data['incident_type'],
                'severity' => $data['severity'] ?? 'medium',
                'description' => $data['description'],
                'data_categories_affected' => json_encode($data['data_categories_affected'] ?? []),
                'data_subjects_affected' => $data['data_subjects_affected'] ?? null,
                'estimated_records_affected' => $data['estimated_records_affected'] ?? null,
                'cause' => $data['cause'] ?? null,
                'processing_activities_affected' => json_encode($data['processing_activities_affected'] ?? []),
                'immediate_actions' => $data['immediate_actions'] ?? null,
                'containment_status' => $data['containment_status'] ?? 'ongoing',
                'investigation_lead' => $data['investigation_lead'] ?? null,
                'regulator_notified' => $data['regulator_notified'] ?? 0,
                'regulator_notification_date' => $data['regulator_notification_date'] ?? null,
                'regulator_reference' => $data['regulator_reference'] ?? null,
                'data_subjects_notified' => $data['data_subjects_notified'] ?? 0,
                'data_subjects_notification_date' => $data['data_subjects_notification_date'] ?? null,
                'remediation_plan' => $data['remediation_plan'] ?? null,
                'lessons_learned' => $data['lessons_learned'] ?? null,
                'status' => 'open',
                'created_by' => $createdBy,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            self::logBreachAction($incidentId, 'created', $createdBy, 'Breach incident recorded');

            return $incidentId;

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to create breach incident', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get all breach incidents
     */
    public static function getBreachIncidents(?string $status = null): array
    {
        $query = DB::table('privacy_breach_incident as b')
            ->leftJoin('user as u', 'b.investigation_lead', '=', 'u.id')
            ->select([
                'b.*',
                'u.username as lead_name',
            ])
            ->orderByDesc('b.incident_date');

        if ($status) {
            $query->where('b.status', $status);
        }

        return $query->get()->toArray();
    }

    /**
     * Get breach incident by ID
     */
    public static function getBreachIncident(int $id): ?object
    {
        return DB::table('privacy_breach_incident as b')
            ->leftJoin('user as u', 'b.investigation_lead', '=', 'u.id')
            ->where('b.id', $id)
            ->select([
                'b.*',
                'u.username as lead_name',
            ])
            ->first();
    }

    /**
     * Update breach incident
     */
    public static function updateBreachIncident(int $id, array $data, int $updatedBy = 0): bool
    {
        try {
            // Encode JSON fields
            if (isset($data['data_categories_affected'])) {
                $data['data_categories_affected'] = json_encode($data['data_categories_affected']);
            }
            if (isset($data['processing_activities_affected'])) {
                $data['processing_activities_affected'] = json_encode($data['processing_activities_affected']);
            }

            $data['updated_at'] = date('Y-m-d H:i:s');

            DB::table('privacy_breach_incident')
                ->where('id', $id)
                ->update($data);

            self::logBreachAction($id, 'updated', $updatedBy);

            return true;

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to update breach incident', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Log breach action
     */
    private static function logBreachAction(
        int $incidentId,
        string $action,
        int $userId,
        ?string $details = null
    ): void {
        try {
            DB::table('privacy_breach_log')->insert([
                'incident_id' => $incidentId,
                'action' => $action,
                'user_id' => $userId,
                'details' => $details,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            self::getLogger()->warning('Failed to log breach action', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate breach reference number
     */
    private static function generateBreachReference(): string
    {
        $year = date('Y');

        $lastRef = DB::table('privacy_breach_incident')
            ->where('reference_number', 'LIKE', "BRE-{$year}-%")
            ->orderByDesc('id')
            ->value('reference_number');

        if ($lastRef) {
            $sequence = (int) substr($lastRef, -4) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('BRE-%s-%04d', $year, $sequence);
    }

    // ==================== Templates ====================

    /**
     * Get all privacy templates
     */
    public static function getTemplates(?string $category = null): array
    {
        $query = DB::table('privacy_template')
            ->where('active', 1)
            ->orderBy('category')
            ->orderBy('name');

        if ($category) {
            $query->where('category', $category);
        }

        return $query->get()->toArray();
    }

    /**
     * Get template by ID
     */
    public static function getTemplate(int $id): ?object
    {
        return DB::table('privacy_template')
            ->where('id', $id)
            ->first();
    }

    /**
     * Create privacy template
     */
    public static function createTemplate(array $data, int $createdBy = 0): ?int
    {
        try {
            return DB::table('privacy_template')->insertGetId([
                'name' => $data['name'],
                'category' => $data['category'],
                'template_type' => $data['template_type'] ?? 'document',
                'content' => $data['content'],
                'paia_reference' => $data['paia_reference'] ?? null,
                'popia_reference' => $data['popia_reference'] ?? null,
                'repository_id' => $data['repository_id'] ?? null,
                'version' => $data['version'] ?? '1.0',
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
     * Get template categories
     */
    public static function getTemplateCategories(): array
    {
        return [
            'privacy_notice' => 'Privacy Notice',
            'paia_manual' => 'PAIA Manual',
            'dpia' => 'Data Protection Impact Assessment',
            'consent_form' => 'Consent Form',
            'breach_notification' => 'Breach Notification',
            'dsar_response' => 'DSAR Response',
            'retention_schedule' => 'Retention Schedule',
            'processing_agreement' => 'Data Processing Agreement',
        ];
    }

    // ==================== Dashboard & Reporting ====================

    /**
     * Get privacy compliance dashboard summary
     */
    public static function getDashboardSummary(): array
    {
        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'processing_activities' => [
                'total' => DB::table('privacy_processing_activity')->where('status', 'active')->count(),
                'requiring_review' => DB::table('privacy_processing_activity')
                    ->where('next_review_date', '<=', date('Y-m-d'))
                    ->count(),
                'high_risk' => DB::table('privacy_processing_activity')
                    ->where('dpia_required', 1)
                    ->where('dpia_completed', 0)
                    ->count(),
            ],
            'dsar' => [
                'pending' => DB::table('privacy_dsar_request')->where('status', 'pending')->count(),
                'in_progress' => DB::table('privacy_dsar_request')->where('status', 'in_progress')->count(),
                'overdue' => DB::table('privacy_dsar_request')
                    ->where('status', '!=', 'completed')
                    ->where('status', '!=', 'rejected')
                    ->where('deadline', '<', date('Y-m-d'))
                    ->count(),
                'completed_this_month' => DB::table('privacy_dsar_request')
                    ->where('status', 'completed')
                    ->whereMonth('completed_date', date('m'))
                    ->whereYear('completed_date', date('Y'))
                    ->count(),
            ],
            'breaches' => [
                'open' => DB::table('privacy_breach_incident')
                    ->where('status', '!=', 'closed')
                    ->count(),
                'this_year' => DB::table('privacy_breach_incident')
                    ->whereYear('incident_date', date('Y'))
                    ->count(),
                'awaiting_notification' => DB::table('privacy_breach_incident')
                    ->where('regulator_notified', 0)
                    ->where('severity', 'high')
                    ->where('status', '!=', 'closed')
                    ->count(),
            ],
        ];
    }

    /**
     * Link processing activity to security classification
     */
    public static function linkToSecurityClassification(
        int $activityId,
        int $classificationId,
        ?string $notes = null
    ): bool {
        try {
            return DB::table('privacy_processing_activity')
                ->where('id', $activityId)
                ->update([
                    'classification_link' => $classificationId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]) !== false;

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to link to classification', [
                'activity_id' => $activityId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get lawful processing grounds
     */
    public static function getLawfulGrounds(): array
    {
        return self::POPIA_LAWFUL_GROUNDS;
    }

    /**
     * Get DSAR types
     */
    public static function getDsarTypes(): array
    {
        return self::DSAR_TYPES;
    }

    /**
     * Get lawful bases for dropdown
     */
    public static function getLawfulBases(): array
    {
        return [
            'consent' => 'Consent',
            'contract' => 'Contract',
            'legal_obligation' => 'Legal Obligation',
            'vital_interests' => 'Vital Interests',
            'public_task' => 'Public Task',
            'legitimate_interests' => 'Legitimate Interests',
        ];
    }

    /**
     * Get data categories
     */
    public static function getDataCategories(): array
    {
        return [
            'personal_identifiers' => 'Personal Identifiers (name, ID number, etc.)',
            'contact_information' => 'Contact Information (address, email, phone)',
            'financial_data' => 'Financial Data',
            'health_data' => 'Health Data (Special Category)',
            'biometric_data' => 'Biometric Data (Special Category)',
            'genetic_data' => 'Genetic Data (Special Category)',
            'racial_ethnic' => 'Racial/Ethnic Origin (Special Category)',
            'political_opinions' => 'Political Opinions (Special Category)',
            'religious_beliefs' => 'Religious/Philosophical Beliefs (Special Category)',
            'trade_union' => 'Trade Union Membership (Special Category)',
            'sexual_orientation' => 'Sexual Orientation (Special Category)',
            'criminal_records' => 'Criminal Records',
            'employment_data' => 'Employment Data',
            'education_data' => 'Education Data',
            'location_data' => 'Location Data',
            'online_identifiers' => 'Online Identifiers (IP, cookies)',
        ];
    }

    /**
     * Get ROPA summary statistics
     */
    public static function getROPASummary(): array
    {
        return [
            'total' => DB::table('privacy_processing_activity')->count(),
            'draft' => DB::table('privacy_processing_activity')->where('status', 'draft')->count(),
            'pending' => DB::table('privacy_processing_activity')->where('status', 'pending_review')->count(),
            'approved' => DB::table('privacy_processing_activity')->where('status', 'approved')->count(),
            'archived' => DB::table('privacy_processing_activity')->where('status', 'archived')->count(),
            'dpia_required' => DB::table('privacy_processing_activity')->where('dpia_required', 1)->count(),
            'dpia_pending' => DB::table('privacy_processing_activity')
                ->where('dpia_required', 1)
                ->where('dpia_completed', 0)
                ->count(),
        ];
    }

    /**
     * Get pending DSARs
     */
    public static function getPendingDSARs(int $limit = 10): array
    {
        return DB::table('privacy_dsar_request')
            ->whereIn('status', ['pending', 'in_progress', 'awaiting_info'])
            ->orderBy('deadline', 'asc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Create DSAR request (alias for controller compatibility)
     */
    public static function createDSAR(array $data, int $createdBy = 0): ?int
    {
        try {
            $deadline = date('Y-m-d', strtotime('+30 days'));

            return DB::table('privacy_dsar_request')->insertGetId([
                'reference_number' => self::generateDsarReference(),
                'request_type' => $data['request_type'],
                'subject_name' => $data['subject_name'],
                'subject_email' => $data['subject_email'] ?? null,
                'subject_phone' => $data['subject_phone'] ?? null,
                'subject_id_number' => $data['subject_id_number'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => 'pending',
                'priority' => $data['priority'] ?? 'normal',
                'assigned_to' => $data['assigned_to'] ?? null,
                'deadline' => $deadline,
                'created_by' => $createdBy,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to create DSAR', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Update DSAR status
     */
    public static function updateDSARStatus(int $id, string $status, ?string $notes = null, int $userId = 0): bool
    {
        try {
            DB::beginTransaction();

            $currentDsar = DB::table('privacy_dsar_request')->where('id', $id)->first();
            $previousStatus = $currentDsar->status ?? null;

            $updateData = [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($status === 'completed') {
                $updateData['completed_at'] = date('Y-m-d H:i:s');
            }

            DB::table('privacy_dsar_request')
                ->where('id', $id)
                ->update($updateData);

            // Log the status change
            DB::table('privacy_dsar_log')->insert([
                'dsar_id' => $id,
                'action' => 'status_change',
                'previous_status' => $previousStatus,
                'new_status' => $status,
                'notes' => $notes,
                'user_id' => $userId,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            self::getLogger()->error('Failed to update DSAR status', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Log DSAR activity
     */
    public static function logDSARActivity(int $dsarId, string $action, ?string $notes = null, int $userId = 0): bool
    {
        try {
            DB::table('privacy_dsar_log')->insert([
                'dsar_id' => $dsarId,
                'action' => $action,
                'notes' => $notes,
                'user_id' => $userId,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            return true;

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to log DSAR activity', [
                'dsar_id' => $dsarId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get breach severity levels
     */
    public static function getBreachSeverityLevels(): array
    {
        return [
            'low' => 'Low - Minor impact, no significant risk',
            'medium' => 'Medium - Moderate impact, some risk to individuals',
            'high' => 'High - Significant impact, serious risk to individuals',
            'critical' => 'Critical - Severe impact, immediate risk to individuals',
        ];
    }

    /**
     * Record breach incident
     */
    public static function recordBreachIncident(array $data, int $reportedBy = 0): ?int
    {
        try {
            return DB::table('privacy_breach_incident')->insertGetId([
                'reference_number' => self::generateBreachReference(),
                'incident_date' => $data['incident_date'],
                'discovered_date' => $data['discovered_date'] ?? date('Y-m-d'),
                'description' => $data['description'],
                'data_types_affected' => $data['data_types_affected'] ?? null,
                'subjects_affected_count' => $data['subjects_affected_count'] ?? null,
                'severity' => $data['severity'] ?? 'medium',
                'root_cause' => $data['root_cause'] ?? null,
                'containment_actions' => $data['containment_actions'] ?? null,
                'notification_required' => $data['notification_required'] ?? 0,
                'regulator_notified' => 0,
                'subjects_notified' => 0,
                'status' => 'open',
                'reported_by' => $reportedBy,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to record breach incident', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get all templates
     */
    public static function getAllTemplates(): array
    {
        return DB::table('privacy_template')
            ->where('active', 1)
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * Initialize default templates
     */
    public static function initializeDefaultTemplates(): int
    {
        $templates = [
            [
                'name' => 'Standard Privacy Notice',
                'code' => 'privacy_notice_standard',
                'category' => 'privacy_notice',
                'description' => 'Standard POPIA compliant privacy notice',
                'content' => 'This Privacy Notice explains how [Organization Name] collects, uses, and protects your personal information in accordance with the Protection of Personal Information Act (POPIA)...',
            ],
            [
                'name' => 'PAIA Section 51 Manual',
                'code' => 'paia_manual_s51',
                'category' => 'paia_manual',
                'description' => 'Template for PAIA Section 51 Manual',
                'content' => 'MANUAL IN TERMS OF SECTION 51 OF THE PROMOTION OF ACCESS TO INFORMATION ACT, 2 OF 2000...',
            ],
            [
                'name' => 'DPIA Template',
                'code' => 'dpia_template',
                'category' => 'dpia',
                'description' => 'Data Protection Impact Assessment template',
                'content' => 'DATA PROTECTION IMPACT ASSESSMENT\n\n1. Project Description\n2. Data Processing Activities\n3. Risk Assessment\n4. Mitigation Measures...',
            ],
            [
                'name' => 'Consent Form',
                'code' => 'consent_standard',
                'category' => 'consent_form',
                'description' => 'Standard consent form for data processing',
                'content' => 'CONSENT TO PROCESS PERSONAL INFORMATION\n\nI, [Data Subject Name], hereby consent to the processing of my personal information...',
            ],
            [
                'name' => 'Breach Notification - Regulator',
                'code' => 'breach_regulator',
                'category' => 'breach_notification',
                'description' => 'Template for notifying the Information Regulator of a breach',
                'content' => 'NOTIFICATION OF SECURITY COMPROMISE\n\nTo: Information Regulator\n\nWe hereby notify you of a security compromise...',
            ],
            [
                'name' => 'Breach Notification - Data Subject',
                'code' => 'breach_subject',
                'category' => 'breach_notification',
                'description' => 'Template for notifying data subjects of a breach',
                'content' => 'IMPORTANT: Security Incident Notification\n\nDear [Data Subject Name],\n\nWe are writing to inform you of a security incident...',
            ],
            [
                'name' => 'DSAR Response - Access',
                'code' => 'dsar_access_response',
                'category' => 'dsar_response',
                'description' => 'Template for responding to access requests',
                'content' => 'Dear [Data Subject Name],\n\nThank you for your request to access your personal information. We have processed your request and...',
            ],
            [
                'name' => 'Data Processing Agreement',
                'code' => 'dpa_standard',
                'category' => 'processing_agreement',
                'description' => 'Standard data processing agreement for operators',
                'content' => 'DATA PROCESSING AGREEMENT\n\nBetween [Responsible Party] and [Operator]\n\n1. Definitions\n2. Processing Instructions...',
            ],
        ];

        $count = 0;
        foreach ($templates as $template) {
            $exists = DB::table('privacy_template')
                ->where('code', $template['code'])
                ->exists();

            if (!$exists) {
                DB::table('privacy_template')->insert([
                    'name' => $template['name'],
                    'code' => $template['code'],
                    'category' => $template['category'],
                    'description' => $template['description'],
                    'content' => $template['content'],
                    'language' => 'en',
                    'version' => '1.0',
                    'active' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $count++;
            }
        }

        return $count;
    }
}
