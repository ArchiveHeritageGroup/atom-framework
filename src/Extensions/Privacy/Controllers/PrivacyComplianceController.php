<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\Privacy\Controllers;

use AtomFramework\Extensions\Privacy\Services\PrivacyComplianceService;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Privacy Compliance Controller
 *
 * Handles POPIA/PAIA/GDPR compliance, ROPA management, DSAR tracking,
 * and breach incident logging for South African privacy requirements.
 * Mimics AtoM 2.10 admin module layout and functionality.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class PrivacyComplianceController
{
    private $view;

    public function __construct($view)
    {
        $this->view = $view;
    }

    /**
     * Privacy Dashboard
     * Route: GET /admin/privacy
     */
    public function index(Request $request, Response $response): Response
    {
        // Get ROPA summary
        $ropaStats = PrivacyComplianceService::getROPASummary();

        // Get pending DSARs
        $pendingDsars = PrivacyComplianceService::getPendingDSARs();

        // Get recent breaches
        $recentBreaches = DB::table('privacy_breach_incident')
            ->orderBy('incident_date', 'desc')
            ->limit(5)
            ->get()
            ->toArray();

        // Get DSAR statistics
        $dsarStats = $this->getDsarStatistics();

        // Get compliance score
        $complianceScore = $this->calculateComplianceScore();

        return $this->view->render($response, 'privacy/index.blade.php', [
            'ropaStats' => $ropaStats,
            'pendingDsars' => $pendingDsars,
            'recentBreaches' => $recentBreaches,
            'dsarStats' => $dsarStats,
            'complianceScore' => $complianceScore,
            'pageTitle' => 'Privacy Compliance Dashboard',
        ]);
    }

    /**
     * ROPA (Record of Processing Activities) List
     * Route: GET /admin/privacy/ropa
     */
    public function ropaList(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = (int) ($params['page'] ?? 1);
        $perPage = 25;
        $status = $params['status'] ?? null;

        $query = DB::table('privacy_processing_activity')
            ->select('*');

        if ($status) {
            $query->where('status', $status);
        }

        $total = $query->count();

        $activities = $query
            ->orderBy('updated_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return $this->view->render($response, 'privacy/ropa/index.blade.php', [
            'activities' => $activities,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'status' => $status,
            'pageTitle' => 'Record of Processing Activities (ROPA)',
        ]);
    }

    /**
     * Create ROPA Entry Form
     * Route: GET /admin/privacy/ropa/new
     */
    public function createRopa(Request $request, Response $response): Response
    {
        $lawfulBases = PrivacyComplianceService::getLawfulBases();
        $dataCategories = PrivacyComplianceService::getDataCategories();

        return $this->view->render($response, 'privacy/ropa/create.blade.php', [
            'lawfulBases' => $lawfulBases,
            'dataCategories' => $dataCategories,
            'pageTitle' => 'New Processing Activity',
        ]);
    }

    /**
     * Store ROPA Entry
     * Route: POST /admin/privacy/ropa
     */
    public function storeRopa(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        $activityData = [
            'name' => $data['name'],
            'purpose' => $data['purpose'],
            'lawful_basis' => $data['lawful_basis'],
            'popia_condition' => $data['popia_condition'] ?? null,
            'data_categories' => !empty($data['data_categories']) ? json_encode($data['data_categories']) : null,
            'data_subjects' => $data['data_subjects'] ?? null,
            'recipients' => $data['recipients'] ?? null,
            'third_countries' => $data['third_countries'] ?? null,
            'retention_period' => $data['retention_period'] ?? null,
            'security_measures' => $data['security_measures'] ?? null,
            'dpia_required' => isset($data['dpia_required']) ? 1 : 0,
            'dpia_completed' => isset($data['dpia_completed']) ? 1 : 0,
            'dpia_date' => !empty($data['dpia_date']) ? $data['dpia_date'] : null,
            'responsible_person' => $data['responsible_person'] ?? null,
            'status' => $data['status'] ?? 'draft',
        ];

        $id = PrivacyComplianceService::createProcessingActivity($activityData, $currentUser['id'] ?? 0);

        if ($id) {
            return $this->redirect($response, '/admin/privacy/ropa?success=created');
        }

        return $this->redirect($response, '/admin/privacy/ropa/new?error=failed');
    }

    /**
     * View ROPA Entry
     * Route: GET /admin/privacy/ropa/{id}
     */
    public function viewRopa(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        $activity = DB::table('privacy_processing_activity')
            ->where('id', $id)
            ->first();

        if (!$activity) {
            return $this->redirect($response, '/admin/privacy/ropa?error=notfound');
        }

        return $this->view->render($response, 'privacy/ropa/view.blade.php', [
            'activity' => $activity,
            'pageTitle' => 'Processing Activity: ' . $activity->name,
        ]);
    }

    /**
     * Edit ROPA Entry
     * Route: GET /admin/privacy/ropa/{id}/edit
     */
    public function editRopa(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        $activity = DB::table('privacy_processing_activity')
            ->where('id', $id)
            ->first();

        if (!$activity) {
            return $this->redirect($response, '/admin/privacy/ropa?error=notfound');
        }

        $lawfulBases = PrivacyComplianceService::getLawfulBases();
        $dataCategories = PrivacyComplianceService::getDataCategories();

        return $this->view->render($response, 'privacy/ropa/edit.blade.php', [
            'activity' => $activity,
            'lawfulBases' => $lawfulBases,
            'dataCategories' => $dataCategories,
            'pageTitle' => 'Edit Processing Activity: ' . $activity->name,
        ]);
    }

    /**
     * Update ROPA Entry
     * Route: PUT /admin/privacy/ropa/{id}
     */
    public function updateRopa(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        $updateData = [
            'name' => $data['name'],
            'purpose' => $data['purpose'],
            'lawful_basis' => $data['lawful_basis'],
            'popia_condition' => $data['popia_condition'] ?? null,
            'data_categories' => !empty($data['data_categories']) ? json_encode($data['data_categories']) : null,
            'data_subjects' => $data['data_subjects'] ?? null,
            'recipients' => $data['recipients'] ?? null,
            'third_countries' => $data['third_countries'] ?? null,
            'retention_period' => $data['retention_period'] ?? null,
            'security_measures' => $data['security_measures'] ?? null,
            'dpia_required' => isset($data['dpia_required']) ? 1 : 0,
            'dpia_completed' => isset($data['dpia_completed']) ? 1 : 0,
            'dpia_date' => !empty($data['dpia_date']) ? $data['dpia_date'] : null,
            'responsible_person' => $data['responsible_person'] ?? null,
            'status' => $data['status'] ?? 'draft',
        ];

        $success = PrivacyComplianceService::updateProcessingActivity($id, $updateData, $currentUser['id'] ?? 0);

        if ($success) {
            return $this->redirect($response, "/admin/privacy/ropa/{$id}?success=updated");
        }

        return $this->redirect($response, "/admin/privacy/ropa/{$id}/edit?error=failed");
    }

    /**
     * DSAR (Data Subject Access Request) List
     * Route: GET /admin/privacy/dsar
     */
    public function dsarList(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = (int) ($params['page'] ?? 1);
        $perPage = 25;
        $status = $params['status'] ?? null;

        $query = DB::table('privacy_dsar_request as dr')
            ->leftJoin('user as u', 'dr.assigned_to', '=', 'u.id')
            ->select([
                'dr.*',
                'u.username as assigned_username',
            ]);

        if ($status) {
            $query->where('dr.status', $status);
        }

        $total = $query->count();

        $requests = $query
            ->orderBy('dr.deadline', 'asc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        // Get pending count
        $pendingCount = DB::table('privacy_dsar_request')
            ->where('status', 'pending')
            ->count();

        // Get overdue count
        $overdueCount = DB::table('privacy_dsar_request')
            ->whereIn('status', ['pending', 'in_progress'])
            ->where('deadline', '<', date('Y-m-d'))
            ->count();

        return $this->view->render($response, 'privacy/dsar/index.blade.php', [
            'requests' => $requests,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'status' => $status,
            'pendingCount' => $pendingCount,
            'overdueCount' => $overdueCount,
            'pageTitle' => 'Data Subject Access Requests',
        ]);
    }

    /**
     * Create DSAR Form
     * Route: GET /admin/privacy/dsar/new
     */
    public function createDsar(Request $request, Response $response): Response
    {
        $requestTypes = PrivacyComplianceService::getDsarTypes();

        $users = DB::table('user')
            ->whereNotNull('username')
            ->where('active', 1)
            ->orderBy('username')
            ->get(['id', 'username'])
            ->toArray();

        return $this->view->render($response, 'privacy/dsar/create.blade.php', [
            'requestTypes' => $requestTypes,
            'users' => $users,
            'pageTitle' => 'New DSAR',
        ]);
    }

    /**
     * Store DSAR
     * Route: POST /admin/privacy/dsar
     */
    public function storeDsar(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        $dsarData = [
            'request_type' => $data['request_type'],
            'subject_name' => $data['subject_name'],
            'subject_email' => $data['subject_email'] ?? null,
            'subject_phone' => $data['subject_phone'] ?? null,
            'subject_id_number' => $data['subject_id_number'] ?? null,
            'description' => $data['description'] ?? null,
            'assigned_to' => !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            'priority' => $data['priority'] ?? 'normal',
        ];

        $id = PrivacyComplianceService::createDSAR($dsarData, $currentUser['id'] ?? 0);

        if ($id) {
            return $this->redirect($response, '/admin/privacy/dsar?success=created');
        }

        return $this->redirect($response, '/admin/privacy/dsar/new?error=failed');
    }

    /**
     * View DSAR
     * Route: GET /admin/privacy/dsar/{id}
     */
    public function viewDsar(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        $dsar = DB::table('privacy_dsar_request as dr')
            ->leftJoin('user as u', 'dr.assigned_to', '=', 'u.id')
            ->leftJoin('user as cb', 'dr.created_by', '=', 'cb.id')
            ->where('dr.id', $id)
            ->select([
                'dr.*',
                'u.username as assigned_username',
                'cb.username as created_by_username',
            ])
            ->first();

        if (!$dsar) {
            return $this->redirect($response, '/admin/privacy/dsar?error=notfound');
        }

        // Get log entries
        $logs = DB::table('privacy_dsar_log as dl')
            ->leftJoin('user as u', 'dl.user_id', '=', 'u.id')
            ->where('dl.dsar_id', $id)
            ->select([
                'dl.*',
                'u.username',
            ])
            ->orderBy('dl.created_at', 'desc')
            ->get()
            ->toArray();

        // Calculate days remaining
        $deadline = new \DateTime($dsar->deadline);
        $today = new \DateTime();
        $daysRemaining = $today->diff($deadline)->days;
        if ($today > $deadline) {
            $daysRemaining = -$daysRemaining;
        }

        return $this->view->render($response, 'privacy/dsar/view.blade.php', [
            'dsar' => $dsar,
            'logs' => $logs,
            'daysRemaining' => $daysRemaining,
            'pageTitle' => 'DSAR: ' . $dsar->reference_number,
        ]);
    }

    /**
     * Update DSAR Status
     * Route: POST /admin/privacy/dsar/{id}/status
     */
    public function updateDsarStatus(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        $success = PrivacyComplianceService::updateDSARStatus(
            $id,
            $data['status'],
            $data['notes'] ?? null,
            $currentUser['id'] ?? 0
        );

        if ($success) {
            return $this->redirect($response, "/admin/privacy/dsar/{$id}?success=updated");
        }

        return $this->redirect($response, "/admin/privacy/dsar/{$id}?error=update_failed");
    }

    /**
     * Log DSAR Activity
     * Route: POST /admin/privacy/dsar/{id}/log
     */
    public function logDsarActivity(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        PrivacyComplianceService::logDSARActivity(
            $id,
            $data['action'],
            $data['notes'] ?? null,
            $currentUser['id'] ?? 0
        );

        return $this->redirect($response, "/admin/privacy/dsar/{$id}?success=logged");
    }

    /**
     * Breach Incidents List
     * Route: GET /admin/privacy/breaches
     */
    public function breachesList(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = (int) ($params['page'] ?? 1);
        $perPage = 25;

        $query = DB::table('privacy_breach_incident as bi')
            ->leftJoin('user as u', 'bi.reported_by', '=', 'u.id')
            ->select([
                'bi.*',
                'u.username as reported_by_username',
            ]);

        $total = $query->count();

        $breaches = $query
            ->orderBy('bi.incident_date', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return $this->view->render($response, 'privacy/breaches/index.blade.php', [
            'breaches' => $breaches,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pageTitle' => 'Breach Incidents',
        ]);
    }

    /**
     * Report Breach Form
     * Route: GET /admin/privacy/breaches/new
     */
    public function createBreach(Request $request, Response $response): Response
    {
        $severityLevels = PrivacyComplianceService::getBreachSeverityLevels();

        return $this->view->render($response, 'privacy/breaches/create.blade.php', [
            'severityLevels' => $severityLevels,
            'pageTitle' => 'Report Breach Incident',
        ]);
    }

    /**
     * Store Breach
     * Route: POST /admin/privacy/breaches
     */
    public function storeBreach(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        $breachData = [
            'incident_date' => $data['incident_date'],
            'discovered_date' => $data['discovered_date'] ?? date('Y-m-d'),
            'description' => $data['description'],
            'data_types_affected' => $data['data_types_affected'] ?? null,
            'subjects_affected_count' => !empty($data['subjects_affected_count']) ? (int) $data['subjects_affected_count'] : null,
            'severity' => $data['severity'] ?? 'medium',
            'root_cause' => $data['root_cause'] ?? null,
            'containment_actions' => $data['containment_actions'] ?? null,
            'notification_required' => isset($data['notification_required']) ? 1 : 0,
        ];

        $id = PrivacyComplianceService::recordBreachIncident($breachData, $currentUser['id'] ?? 0);

        if ($id) {
            return $this->redirect($response, '/admin/privacy/breaches?success=reported');
        }

        return $this->redirect($response, '/admin/privacy/breaches/new?error=failed');
    }

    /**
     * View Breach
     * Route: GET /admin/privacy/breaches/{id}
     */
    public function viewBreach(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        $breach = DB::table('privacy_breach_incident as bi')
            ->leftJoin('user as u', 'bi.reported_by', '=', 'u.id')
            ->where('bi.id', $id)
            ->select([
                'bi.*',
                'u.username as reported_by_username',
            ])
            ->first();

        if (!$breach) {
            return $this->redirect($response, '/admin/privacy/breaches?error=notfound');
        }

        // Check notification deadline (72 hours for regulator)
        $discoveredDate = new \DateTime($breach->discovered_date);
        $notificationDeadline = clone $discoveredDate;
        $notificationDeadline->add(new \DateInterval('PT72H'));
        $now = new \DateTime();
        $hoursRemaining = ($notificationDeadline->getTimestamp() - $now->getTimestamp()) / 3600;

        return $this->view->render($response, 'privacy/breaches/view.blade.php', [
            'breach' => $breach,
            'notificationDeadline' => $notificationDeadline,
            'hoursRemaining' => $hoursRemaining,
            'pageTitle' => 'Breach: ' . $breach->reference_number,
        ]);
    }

    /**
     * Update Breach
     * Route: PUT /admin/privacy/breaches/{id}
     */
    public function updateBreach(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        $updateData = [
            'regulator_notified' => isset($data['regulator_notified']) ? 1 : 0,
            'regulator_notification_date' => !empty($data['regulator_notification_date']) ? $data['regulator_notification_date'] : null,
            'subjects_notified' => isset($data['subjects_notified']) ? 1 : 0,
            'subjects_notification_date' => !empty($data['subjects_notification_date']) ? $data['subjects_notification_date'] : null,
            'remediation_actions' => $data['remediation_actions'] ?? null,
            'lessons_learned' => $data['lessons_learned'] ?? null,
            'status' => $data['status'] ?? 'open',
            'closed_date' => 'closed' === ($data['status'] ?? '') ? date('Y-m-d') : null,
        ];

        $success = PrivacyComplianceService::updateBreachIncident($id, $updateData, $currentUser['id'] ?? 0);

        if ($success) {
            return $this->redirect($response, "/admin/privacy/breaches/{$id}?success=updated");
        }

        return $this->redirect($response, "/admin/privacy/breaches/{$id}?error=failed");
    }

    /**
     * Privacy Templates List
     * Route: GET /admin/privacy/templates
     */
    public function templatesList(Request $request, Response $response): Response
    {
        $templates = PrivacyComplianceService::getAllTemplates();

        $categories = [
            'privacy_notice' => 'Privacy Notices',
            'paia_manual' => 'PAIA Manuals',
            'dpia' => 'DPIA Templates',
            'consent_form' => 'Consent Forms',
            'breach_notification' => 'Breach Notifications',
            'dsar_response' => 'DSAR Responses',
            'retention_schedule' => 'Retention Schedules',
            'processing_agreement' => 'Processing Agreements',
        ];

        return $this->view->render($response, 'privacy/templates/index.blade.php', [
            'templates' => $templates,
            'categories' => $categories,
            'pageTitle' => 'Privacy Templates',
        ]);
    }

    /**
     * Create Template Form
     * Route: GET /admin/privacy/templates/new
     */
    public function createTemplate(Request $request, Response $response): Response
    {
        $categories = PrivacyComplianceService::getTemplateCategories();

        return $this->view->render($response, 'privacy/templates/create.blade.php', [
            'categories' => $categories,
            'pageTitle' => 'New Privacy Template',
        ]);
    }

    /**
     * Store Template
     * Route: POST /admin/privacy/templates
     */
    public function storeTemplate(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        $templateData = [
            'name' => $data['name'],
            'code' => $data['code'],
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'content' => $data['content'],
            'variables' => !empty($data['variables']) ? json_encode(explode(',', $data['variables'])) : null,
            'language' => $data['language'] ?? 'en',
        ];

        $id = PrivacyComplianceService::createTemplate($templateData, $currentUser['id'] ?? 0);

        if ($id) {
            return $this->redirect($response, '/admin/privacy/templates?success=created');
        }

        return $this->redirect($response, '/admin/privacy/templates/new?error=failed');
    }

    /**
     * Initialize Default Templates
     * Route: POST /admin/privacy/templates/initialize
     */
    public function initializeTemplates(Request $request, Response $response): Response
    {
        $count = PrivacyComplianceService::initializeDefaultTemplates();

        return $this->redirect($response, "/admin/privacy/templates?success=initialized&count={$count}");
    }

    /**
     * Export Compliance Report
     * Route: GET /admin/privacy/export
     */
    public function exportReport(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $format = $params['format'] ?? 'json';

        $report = [
            'generated_at' => date('c'),
            'ropa' => PrivacyComplianceService::getROPASummary(),
            'dsar_statistics' => $this->getDsarStatistics(),
            'breach_summary' => $this->getBreachSummary(),
            'compliance_score' => $this->calculateComplianceScore(),
        ];

        if ('csv' === $format) {
            return $this->exportCsv($response, $report);
        }

        $response->getBody()->write(json_encode($report, JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Disposition', 'attachment; filename="privacy-compliance-report-' . date('Y-m-d') . '.json"');
    }

    /**
     * Get DSAR Statistics
     */
    private function getDsarStatistics(): array
    {
        return [
            'total' => DB::table('privacy_dsar_request')->count(),
            'pending' => DB::table('privacy_dsar_request')->where('status', 'pending')->count(),
            'in_progress' => DB::table('privacy_dsar_request')->where('status', 'in_progress')->count(),
            'completed' => DB::table('privacy_dsar_request')->where('status', 'completed')->count(),
            'overdue' => DB::table('privacy_dsar_request')
                ->whereIn('status', ['pending', 'in_progress'])
                ->where('deadline', '<', date('Y-m-d'))
                ->count(),
            'average_completion_days' => $this->calculateAverageCompletionDays(),
        ];
    }

    /**
     * Calculate Average DSAR Completion Days
     */
    private function calculateAverageCompletionDays(): ?float
    {
        $completed = DB::table('privacy_dsar_request')
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->select([
                DB::raw('AVG(DATEDIFF(completed_at, created_at)) as avg_days'),
            ])
            ->first();

        return $completed ? round((float) $completed->avg_days, 1) : null;
    }

    /**
     * Get Breach Summary
     */
    private function getBreachSummary(): array
    {
        return [
            'total' => DB::table('privacy_breach_incident')->count(),
            'open' => DB::table('privacy_breach_incident')->where('status', 'open')->count(),
            'closed' => DB::table('privacy_breach_incident')->where('status', 'closed')->count(),
            'high_severity' => DB::table('privacy_breach_incident')->where('severity', 'high')->count(),
            'regulator_notified' => DB::table('privacy_breach_incident')->where('regulator_notified', 1)->count(),
        ];
    }

    /**
     * Calculate Compliance Score
     */
    private function calculateComplianceScore(): array
    {
        $score = 0;
        $maxScore = 100;
        $breakdown = [];

        // ROPA completeness (30 points)
        $ropaTotal = DB::table('privacy_processing_activity')->count();
        $ropaApproved = DB::table('privacy_processing_activity')->where('status', 'approved')->count();
        $ropaScore = $ropaTotal > 0 ? round(($ropaApproved / $ropaTotal) * 30) : 0;
        $score += $ropaScore;
        $breakdown['ropa'] = ['score' => $ropaScore, 'max' => 30, 'description' => 'ROPA Completeness'];

        // DSAR response rate (25 points)
        $dsarTotal = DB::table('privacy_dsar_request')->count();
        $dsarOnTime = DB::table('privacy_dsar_request')
            ->where('status', 'completed')
            ->whereRaw('completed_at <= deadline')
            ->count();
        $dsarScore = $dsarTotal > 0 ? round(($dsarOnTime / $dsarTotal) * 25) : 25;
        $score += $dsarScore;
        $breakdown['dsar'] = ['score' => $dsarScore, 'max' => 25, 'description' => 'DSAR Response Rate'];

        // Breach handling (25 points)
        $breachTotal = DB::table('privacy_breach_incident')->count();
        $breachHandled = DB::table('privacy_breach_incident')
            ->where('status', 'closed')
            ->count();
        $breachScore = $breachTotal > 0 ? round(($breachHandled / $breachTotal) * 25) : 25;
        $score += $breachScore;
        $breakdown['breach'] = ['score' => $breachScore, 'max' => 25, 'description' => 'Breach Handling'];

        // DPIA completion (20 points)
        $dpiaRequired = DB::table('privacy_processing_activity')->where('dpia_required', 1)->count();
        $dpiaCompleted = DB::table('privacy_processing_activity')
            ->where('dpia_required', 1)
            ->where('dpia_completed', 1)
            ->count();
        $dpiaScore = $dpiaRequired > 0 ? round(($dpiaCompleted / $dpiaRequired) * 20) : 20;
        $score += $dpiaScore;
        $breakdown['dpia'] = ['score' => $dpiaScore, 'max' => 20, 'description' => 'DPIA Completion'];

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'percentage' => round(($score / $maxScore) * 100),
            'breakdown' => $breakdown,
            'rating' => $this->getComplianceRating($score),
        ];
    }

    /**
     * Get Compliance Rating
     */
    private function getComplianceRating(int $score): string
    {
        if ($score >= 90) {
            return 'Excellent';
        }
        if ($score >= 75) {
            return 'Good';
        }
        if ($score >= 60) {
            return 'Satisfactory';
        }
        if ($score >= 40) {
            return 'Needs Improvement';
        }

        return 'Critical';
    }

    /**
     * Export CSV
     */
    private function exportCsv(Response $response, array $data): Response
    {
        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['Category', 'Metric', 'Value']);

        // ROPA
        foreach ($data['ropa'] as $key => $value) {
            fputcsv($output, ['ROPA', $key, is_array($value) ? json_encode($value) : $value]);
        }

        // DSAR
        foreach ($data['dsar_statistics'] as $key => $value) {
            fputcsv($output, ['DSAR', $key, $value]);
        }

        // Breach
        foreach ($data['breach_summary'] as $key => $value) {
            fputcsv($output, ['Breach', $key, $value]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $response->getBody()->write($csv);

        return $response
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Disposition', 'attachment; filename="privacy-compliance-report-' . date('Y-m-d') . '.csv"');
    }

    /**
     * Redirect Helper
     */
    private function redirect(Response $response, string $url): Response
    {
        return $response
            ->withHeader('Location', $url)
            ->withStatus(302);
    }
}
