<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\Security\Controllers;

use AtomExtensions\Helpers\CultureHelper;

use AtomFramework\Extensions\Security\Services\AccessJustificationService;
use AtomFramework\Extensions\Security\Services\SecurityComplianceService;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Security Compliance Controller
 *
 * Handles NARSSA/POPIA compliance reporting, retention schedules,
 * and audit export functionality for South African archival requirements.
 * Mimics AtoM 2.10 admin module layout and functionality.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class SecurityComplianceController
{
    private $view;

    public function __construct($view)
    {
        $this->view = $view;
    }

    /**
     * Compliance Dashboard
     * Route: GET /admin/security/compliance
     */
    public function index(Request $request, Response $response): Response
    {
        $currentUser = $request->getAttribute('user');

        // Get compliance report summary
        $report = SecurityComplianceService::generateComplianceReport();

        // Get pending reviews count
        $pendingReviews = count($report['pending_reviews'] ?? []);

        // Get upcoming declassifications
        $upcomingDeclassifications = count($report['declassification_schedule'] ?? []);

        // Get retention schedules
        $retentionSchedules = DB::table('security_retention_schedule as srs')
            ->leftJoin('security_classification as sc', 'srs.classification_id', '=', 'sc.id')
            ->leftJoin('security_classification as sc2', 'srs.declassify_to_id', '=', 'sc2.id')
            ->where('srs.active', 1)
            ->select([
                'srs.*',
                'sc.code as classification_code',
                'sc.name as classification_name',
                'sc2.code as declassify_to_code',
                'sc2.name as declassify_to_name',
            ])
            ->orderBy('sc.level', 'desc')
            ->get()
            ->toArray();

        // Recent compliance actions
        $recentActions = DB::table('security_compliance_log as scl')
            ->leftJoin('user as u', 'scl.user_id', '=', 'u.id')
            ->select([
                'scl.*',
                'u.username',
            ])
            ->orderBy('scl.created_at', 'desc')
            ->limit(20)
            ->get()
            ->toArray();

        return $this->view->render($response, 'security/compliance/index.blade.php', [
            'report' => $report,
            'pendingReviews' => $pendingReviews,
            'upcomingDeclassifications' => $upcomingDeclassifications,
            'retentionSchedules' => $retentionSchedules,
            'recentActions' => $recentActions,
            'pageTitle' => 'Security Compliance Dashboard',
        ]);
    }

    /**
     * Compliance Report
     * Route: GET /admin/security/compliance/report
     */
    public function report(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $repositoryId = !empty($params['repository_id']) ? (int) $params['repository_id'] : null;
        $fromDate = $params['from_date'] ?? null;
        $toDate = $params['to_date'] ?? null;

        $report = SecurityComplianceService::generateComplianceReport($repositoryId, $fromDate, $toDate);

        // Get repositories for filter
        $repositories = DB::table('repository as r')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('r.id', '=', 'ai.id')
                    ->where('ai.culture', '=', CultureHelper::getCulture());
            })
            ->select(['r.id', 'ai.authorized_form_of_name as name'])
            ->orderBy('ai.authorized_form_of_name')
            ->get()
            ->toArray();

        return $this->view->render($response, 'security/compliance/report.blade.php', [
            'report' => $report,
            'repositories' => $repositories,
            'repositoryId' => $repositoryId,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'pageTitle' => 'NARSSA/POPIA Compliance Report',
        ]);
    }

    /**
     * Export Compliance Report
     * Route: GET /admin/security/compliance/export
     */
    public function exportReport(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $format = $params['format'] ?? 'json';
        $repositoryId = !empty($params['repository_id']) ? (int) $params['repository_id'] : null;
        $fromDate = $params['from_date'] ?? null;
        $toDate = $params['to_date'] ?? null;

        $report = SecurityComplianceService::generateComplianceReport($repositoryId, $fromDate, $toDate);

        if ('csv' === $format) {
            return $this->exportCsv($response, $report, 'compliance-report');
        }

        if ('pdf' === $format) {
            return $this->exportPdf($response, $report, 'compliance-report');
        }

        // Default JSON
        $response->getBody()->write(json_encode($report, JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Disposition', 'attachment; filename="compliance-report-' . date('Y-m-d') . '.json"');
    }

    /**
     * Retention Schedules Management
     * Route: GET /admin/security/compliance/retention
     */
    public function retentionSchedules(Request $request, Response $response): Response
    {
        $schedules = DB::table('security_retention_schedule as srs')
            ->leftJoin('security_classification as sc', 'srs.classification_id', '=', 'sc.id')
            ->leftJoin('security_classification as sc2', 'srs.declassify_to_id', '=', 'sc2.id')
            ->select([
                'srs.*',
                'sc.code as classification_code',
                'sc.name as classification_name',
                'sc.level as classification_level',
                'sc2.code as declassify_to_code',
                'sc2.name as declassify_to_name',
            ])
            ->orderBy('sc.level', 'desc')
            ->get()
            ->toArray();

        $classifications = DB::table('security_classification')
            ->where('active', 1)
            ->orderBy('level', 'desc')
            ->get()
            ->toArray();

        return $this->view->render($response, 'security/compliance/retention.blade.php', [
            'schedules' => $schedules,
            'classifications' => $classifications,
            'pageTitle' => 'Retention Schedules',
        ]);
    }

    /**
     * Add/Edit Retention Schedule Form
     * Route: GET /admin/security/compliance/retention/edit/{id?}
     */
    public function editRetentionSchedule(Request $request, Response $response, array $args): Response
    {
        $id = isset($args['id']) ? (int) $args['id'] : null;
        $schedule = null;

        if ($id) {
            $schedule = DB::table('security_retention_schedule')->where('id', $id)->first();
        }

        $classifications = DB::table('security_classification')
            ->where('active', 1)
            ->orderBy('level', 'desc')
            ->get()
            ->toArray();

        return $this->view->render($response, 'security/compliance/retention_edit.blade.php', [
            'schedule' => $schedule,
            'classifications' => $classifications,
            'pageTitle' => $id ? 'Edit Retention Schedule' : 'Add Retention Schedule',
        ]);
    }

    /**
     * Save Retention Schedule
     * Route: POST /admin/security/compliance/retention/save
     */
    public function saveRetentionSchedule(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');
        $id = !empty($data['id']) ? (int) $data['id'] : null;

        $scheduleData = [
            'classification_id' => (int) $data['classification_id'],
            'retention_years' => (int) $data['retention_years'],
            'action' => $data['action'] ?? 'declassify',
            'declassify_to_id' => !empty($data['declassify_to_id']) ? (int) $data['declassify_to_id'] : null,
            'legal_basis' => $data['legal_basis'] ?? null,
            'narssa_reference' => $data['narssa_reference'] ?? null,
            'description' => $data['description'] ?? null,
            'active' => isset($data['active']) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $currentUser['id'] ?? 0,
        ];

        if ($id) {
            DB::table('security_retention_schedule')->where('id', $id)->update($scheduleData);
        } else {
            $scheduleData['created_at'] = date('Y-m-d H:i:s');
            $scheduleData['created_by'] = $currentUser['id'] ?? 0;
            DB::table('security_retention_schedule')->insert($scheduleData);
        }

        return $this->redirect($response, '/admin/security/compliance/retention?success=saved');
    }

    /**
     * Pending Reviews List
     * Route: GET /admin/security/compliance/reviews
     */
    public function pendingReviews(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = (int) ($params['page'] ?? 1);
        $perPage = 25;

        $query = DB::table('object_security_classification as osc')
            ->join('security_classification as sc', 'osc.classification_id', '=', 'sc.id')
            ->join('information_object as io', 'osc.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('osc.active', 1)
            ->where(function ($q) {
                $q->whereNull('osc.review_date')
                    ->orWhere('osc.review_date', '<=', date('Y-m-d'));
            })
            ->select([
                'osc.*',
                'sc.code as classification_code',
                'sc.name as classification_name',
                'sc.level as classification_level',
                'ioi.title',
                'io.identifier',
                'slug.slug',
            ]);

        $total = $query->count();

        $reviews = $query
            ->orderBy('osc.review_date', 'asc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return $this->view->render($response, 'security/compliance/reviews.blade.php', [
            'reviews' => $reviews,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pageTitle' => 'Pending Security Reviews',
        ]);
    }

    /**
     * Declassification Schedule
     * Route: GET /admin/security/compliance/declassification
     */
    public function declassificationSchedule(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = (int) ($params['page'] ?? 1);
        $perPage = 25;
        $period = $params['period'] ?? '90'; // Days

        $query = DB::table('object_security_classification as osc')
            ->join('security_classification as sc', 'osc.classification_id', '=', 'sc.id')
            ->leftJoin('security_classification as sc2', 'osc.declassify_to_id', '=', 'sc2.id')
            ->join('information_object as io', 'osc.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('osc.active', 1)
            ->whereNotNull('osc.declassify_date')
            ->where('osc.declassify_date', '>=', date('Y-m-d'))
            ->where('osc.declassify_date', '<=', date('Y-m-d', strtotime("+{$period} days")))
            ->select([
                'osc.*',
                'sc.code as current_code',
                'sc.name as current_name',
                'sc2.code as target_code',
                'sc2.name as target_name',
                'ioi.title',
                'io.identifier',
                'slug.slug',
            ]);

        $total = $query->count();

        $items = $query
            ->orderBy('osc.declassify_date', 'asc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return $this->view->render($response, 'security/compliance/declassification.blade.php', [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'period' => $period,
            'pageTitle' => 'Declassification Schedule',
        ]);
    }

    /**
     * Suggest Declassification Date (AJAX)
     * Route: GET /admin/security/compliance/suggest-declassification/{objectId}
     */
    public function suggestDeclassification(Request $request, Response $response, array $args): Response
    {
        $objectId = (int) $args['objectId'];
        $suggestion = SecurityComplianceService::suggestDeclassificationDate($objectId);

        $response->getBody()->write(json_encode($suggestion ?? ['error' => 'No suggestion available']));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Access Logs Export
     * Route: GET /admin/security/compliance/access-logs
     */
    public function accessLogs(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = (int) ($params['page'] ?? 1);
        $perPage = 50;
        $fromDate = $params['from_date'] ?? null;
        $toDate = $params['to_date'] ?? null;
        $userId = !empty($params['user_id']) ? (int) $params['user_id'] : null;

        $query = DB::table('access_request_log as arl')
            ->leftJoin('access_request as ar', 'arl.request_id', '=', 'ar.id')
            ->leftJoin('user as u', 'arl.actor_id', '=', 'u.id')
            ->select([
                'arl.*',
                'u.username as actor_username',
                'ar.request_type',
                'ar.status as request_status',
            ]);

        if ($fromDate) {
            $query->where('arl.created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('arl.created_at', '<=', $toDate . ' 23:59:59');
        }
        if ($userId) {
            $query->where('arl.actor_id', $userId);
        }

        $total = $query->count();

        $logs = $query
            ->orderBy('arl.created_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        // Get users for filter
        $users = DB::table('user')
            ->whereNotNull('username')
            ->orderBy('username')
            ->get(['id', 'username'])
            ->toArray();

        return $this->view->render($response, 'security/compliance/access_logs.blade.php', [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'userId' => $userId,
            'users' => $users,
            'pageTitle' => 'Access Logs',
        ]);
    }

    /**
     * Export Access Logs
     * Route: GET /admin/security/compliance/access-logs/export
     */
    public function exportAccessLogs(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $format = $params['format'] ?? 'json';
        $fromDate = $params['from_date'] ?? null;
        $toDate = $params['to_date'] ?? null;
        $userId = !empty($params['user_id']) ? (int) $params['user_id'] : null;

        $export = SecurityComplianceService::exportAccessLogs($format, $fromDate, $toDate, $userId);

        if ('csv' === $format) {
            return $this->exportLogsCsv($response, $export['logs'], 'access-logs');
        }

        $response->getBody()->write(json_encode($export, JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Disposition', 'attachment; filename="access-logs-' . date('Y-m-d') . '.json"');
    }

    /**
     * Clearance Logs
     * Route: GET /admin/security/compliance/clearance-logs
     */
    public function clearanceLogs(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = (int) ($params['page'] ?? 1);
        $perPage = 50;
        $fromDate = $params['from_date'] ?? null;
        $toDate = $params['to_date'] ?? null;

        $query = DB::table('user_security_clearance_log as uscl')
            ->leftJoin('user as u', 'uscl.user_id', '=', 'u.id')
            ->leftJoin('user as cb', 'uscl.changed_by', '=', 'cb.id')
            ->leftJoin('security_classification as sc', 'uscl.classification_id', '=', 'sc.id')
            ->select([
                'uscl.*',
                'u.username',
                'cb.username as changed_by_username',
                'sc.code as classification_code',
                'sc.name as classification_name',
            ]);

        if ($fromDate) {
            $query->where('uscl.created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('uscl.created_at', '<=', $toDate . ' 23:59:59');
        }

        $total = $query->count();

        $logs = $query
            ->orderBy('uscl.created_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return $this->view->render($response, 'security/compliance/clearance_logs.blade.php', [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'pageTitle' => 'Clearance Logs',
        ]);
    }

    /**
     * Export Clearance Logs
     * Route: GET /admin/security/compliance/clearance-logs/export
     */
    public function exportClearanceLogs(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $format = $params['format'] ?? 'json';
        $fromDate = $params['from_date'] ?? null;
        $toDate = $params['to_date'] ?? null;

        $export = SecurityComplianceService::exportClearanceLogs($format, $fromDate, $toDate);

        if ('csv' === $format) {
            return $this->exportLogsCsv($response, $export['logs'], 'clearance-logs');
        }

        $response->getBody()->write(json_encode($export, JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Disposition', 'attachment; filename="clearance-logs-' . date('Y-m-d') . '.json"');
    }

    /**
     * Link Access Conditions to Object
     * Route: POST /{slug}/security/access-conditions
     */
    public function linkAccessConditions(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        $object = $this->getObjectBySlug($slug);

        if (!$object) {
            return $this->redirect($response, '/?error=notfound');
        }

        $success = SecurityComplianceService::linkAccessConditions(
            $object->id,
            (int) ($data['classification_id'] ?? 0),
            $data['access_conditions'] ?? null,
            $data['reproduction_conditions'] ?? null,
            $currentUser['id'] ?? 0
        );

        if ($success) {
            return $this->redirect($response, "/{$slug}/security?success=conditions_linked");
        }

        return $this->redirect($response, "/{$slug}/security?error=link_failed");
    }

    /**
     * Justification Templates Management
     * Route: GET /admin/security/compliance/justification-templates
     */
    public function justificationTemplates(Request $request, Response $response): Response
    {
        $templates = AccessJustificationService::getAllTemplates();

        return $this->view->render($response, 'security/compliance/justification_templates.blade.php', [
            'templates' => $templates,
            'pageTitle' => 'Justification Templates',
        ]);
    }

    /**
     * Edit Justification Template
     * Route: GET /admin/security/compliance/justification-templates/edit/{id?}
     */
    public function editJustificationTemplate(Request $request, Response $response, array $args): Response
    {
        $id = isset($args['id']) ? (int) $args['id'] : null;
        $template = null;

        if ($id) {
            $template = AccessJustificationService::getTemplate($id);
        }

        $categories = AccessJustificationService::getTemplateCategories();

        return $this->view->render($response, 'security/compliance/justification_template_edit.blade.php', [
            'template' => $template,
            'categories' => $categories,
            'pageTitle' => $id ? 'Edit Justification Template' : 'Add Justification Template',
        ]);
    }

    /**
     * Save Justification Template
     * Route: POST /admin/security/compliance/justification-templates/save
     */
    public function saveJustificationTemplate(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');
        $id = !empty($data['id']) ? (int) $data['id'] : null;

        $templateData = [
            'name' => $data['name'],
            'code' => $data['code'],
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'template_text' => $data['template_text'],
            'paia_section' => $data['paia_section'] ?? null,
            'popia_ground' => $data['popia_ground'] ?? null,
            'requires_approval' => isset($data['requires_approval']) ? 1 : 0,
            'approval_level' => !empty($data['approval_level']) ? (int) $data['approval_level'] : null,
            'evidence_required' => isset($data['evidence_required']) ? 1 : 0,
            'required_fields' => !empty($data['required_fields']) ? json_encode(explode(',', $data['required_fields'])) : null,
        ];

        if ($id) {
            $success = AccessJustificationService::updateTemplate($id, $templateData);
        } else {
            $success = AccessJustificationService::createTemplate($templateData) > 0;
        }

        if ($success) {
            return $this->redirect($response, '/admin/security/compliance/justification-templates?success=saved');
        }

        return $this->redirect($response, '/admin/security/compliance/justification-templates?error=save_failed');
    }

    /**
     * Initialize Default Templates
     * Route: POST /admin/security/compliance/justification-templates/initialize
     */
    public function initializeDefaultTemplates(Request $request, Response $response): Response
    {
        $count = AccessJustificationService::initializeDefaultTemplates();

        return $this->redirect($response, "/admin/security/compliance/justification-templates?success=initialized&count={$count}");
    }

    /**
     * Helper: Get object by slug
     */
    private function getObjectBySlug(string $slug): ?object
    {
        return DB::table('information_object as io')
            ->join('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', CultureHelper::getCulture());
            })
            ->where('slug.slug', $slug)
            ->select(['io.*', 'ioi.title', 'slug.slug'])
            ->first();
    }

    /**
     * Helper: Export CSV
     */
    private function exportCsv(Response $response, array $data, string $filename): Response
    {
        $output = fopen('php://temp', 'r+');

        // Flatten for CSV
        $flatData = $this->flattenForExport($data);
        if (!empty($flatData)) {
            fputcsv($output, array_keys((array) $flatData[0]));
            foreach ($flatData as $row) {
                fputcsv($output, (array) $row);
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $response->getBody()->write($csv);

        return $response
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}-" . date('Y-m-d') . ".csv\"");
    }

    /**
     * Helper: Export Logs CSV
     */
    private function exportLogsCsv(Response $response, array $logs, string $filename): Response
    {
        $output = fopen('php://temp', 'r+');

        if (!empty($logs)) {
            fputcsv($output, array_keys((array) $logs[0]));
            foreach ($logs as $row) {
                fputcsv($output, (array) $row);
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $response->getBody()->write($csv);

        return $response
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}-" . date('Y-m-d') . ".csv\"");
    }

    /**
     * Helper: Export PDF (placeholder)
     */
    private function exportPdf(Response $response, array $data, string $filename): Response
    {
        // TODO: Implement PDF generation with TCPDF or similar
        $response->getBody()->write(json_encode(['error' => 'PDF export not yet implemented', 'data' => $data]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Helper: Flatten data for export
     */
    private function flattenForExport(array $data): array
    {
        $flat = [];

        if (isset($data['classification_access'])) {
            foreach ($data['classification_access'] as $item) {
                $flat[] = (object) [
                    'classification_code' => $item['classification']['code'] ?? '',
                    'classification_name' => $item['classification']['name'] ?? '',
                    'object_count' => $item['object_count'] ?? 0,
                    'user_count' => $item['user_count'] ?? 0,
                ];
            }
        }

        return $flat;
    }

    /**
     * Helper: Redirect
     */
    private function redirect(Response $response, string $url): Response
    {
        return $response
            ->withHeader('Location', $url)
            ->withStatus(302);
    }
}
