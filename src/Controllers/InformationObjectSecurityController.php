<?php

declare(strict_types=1);

namespace TheAHG\Archive\Controllers;

use AtomExtensions\Helpers\CultureHelper;

use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use TheAHG\Archive\Services\SecurityClearanceService;

/**
 * Information Object Security Controller.
 *
 * Handles security classification for archival descriptions
 * Mimics AtoM 2.10 information object functionality
 */
class InformationObjectSecurityController
{
    private $view;

    public function __construct($view)
    {
        $this->view = $view;
    }

    /**
     * Show classification form for an information object.
     * Route: GET /{slug}/security
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];

        // Get information object by slug
        $object = $this->getObjectBySlug($slug);

        if (!$object) {
            return $this->redirect($response, '/?error=notfound');
        }

        $classification = SecurityClearanceService::getObjectClassification($object->id);
        $classifications = SecurityClearanceService::getClassificationsDropdown();

        // Get classification history
        $history = DB::table('object_classification_history as h')
            ->leftJoin('security_classification as prev', 'h.previous_classification_id', '=', 'prev.id')
            ->leftJoin('security_classification as new', 'h.new_classification_id', '=', 'new.id')
            ->leftJoin('user as cb', 'h.changed_by', '=', 'cb.id')
            ->where('h.object_id', $object->id)
            ->select([
                'h.*',
                'prev.name as previous_name',
                'new.name as new_name',
                'cb.username as changed_by_username',
            ])
            ->orderBy('h.created_at', 'desc')
            ->get()
            ->toArray();

        return $this->view->render($response, 'informationobject/security.blade.php', [
            'object' => $object,
            'classification' => $classification,
            'classifications' => $classifications,
            'history' => $history,
            'pageTitle' => 'Security Classification - '.($object->title ?? $object->identifier),
        ]);
    }

    /**
     * Show classify form.
     * Route: GET /{slug}/security/classify
     */
    public function classify(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        $object = $this->getObjectBySlug($slug);

        if (!$object) {
            return $this->redirect($response, '/?error=notfound');
        }

        $currentClassification = SecurityClearanceService::getObjectClassification($object->id);
        $classifications = SecurityClearanceService::getAllClassifications();

        return $this->view->render($response, 'informationobject/classify.blade.php', [
            'object' => $object,
            'currentClassification' => $currentClassification,
            'classifications' => $classifications,
            'pageTitle' => 'Classify - '.($object->title ?? $object->identifier),
        ]);
    }

    /**
     * Store/update classification.
     * Route: POST /{slug}/security/classify
     */
    public function store(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        $object = $this->getObjectBySlug($slug);

        if (!$object) {
            return $this->redirect($response, '/?error=notfound');
        }

        $action = $data['action'] ?? 'classify';

        if ('declassify' === $action) {
            $reason = $data['declassify_reason'] ?? 'Declassified';
            SecurityClearanceService::declassifyObject($object->id, $currentUser['id'], $reason);

            return $this->redirect($response, "/{$slug}/security?success=declassified");
        }

        $classificationId = (int) ($data['classification_id'] ?? 0);

        if (!$classificationId) {
            return $this->redirect($response, "/{$slug}/security/classify?error=invalid");
        }

        $success = SecurityClearanceService::classifyObject(
            $object->id,
            $classificationId,
            $currentUser['id'],
            $data['reason'] ?? null,
            !empty($data['review_date']) ? $data['review_date'] : null,
            !empty($data['declassify_date']) ? $data['declassify_date'] : null,
            !empty($data['declassify_to_id']) ? (int) $data['declassify_to_id'] : null,
            $data['handling_instructions'] ?? null,
            isset($data['inherit_to_children']) && $data['inherit_to_children']
        );

        if ($success) {
            return $this->redirect($response, "/{$slug}/security?success=classified");
        }

        return $this->redirect($response, "/{$slug}/security/classify?error=failed");
    }

    /**
     * Declassify object.
     * Route: POST /{slug}/security/declassify
     */
    public function declassify(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        $object = $this->getObjectBySlug($slug);

        if (!$object) {
            return $this->redirect($response, '/?error=notfound');
        }

        $reason = $data['reason'] ?? 'Manual declassification';

        $success = SecurityClearanceService::declassifyObject($object->id, $currentUser['id'], $reason);

        if ($success) {
            return $this->redirect($response, "/{$slug}/security?success=declassified");
        }

        return $this->redirect($response, "/{$slug}/security?error=declassify_failed");
    }

    /**
     * Security classification partial for edit form.
     * This is included in the main edit form as a fieldset.
     * Route: GET /{slug}/edit/security (AJAX)
     */
    public function editPartial(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        $object = $this->getObjectBySlug($slug);

        if (!$object) {
            $response->getBody()->write('');

            return $response;
        }

        $classification = SecurityClearanceService::getObjectClassification($object->id);
        $classifications = SecurityClearanceService::getAllClassifications();

        return $this->view->render($response, 'informationobject/_security_fieldset.blade.php', [
            'object' => $object,
            'classification' => $classification,
            'classifications' => $classifications,
        ]);
    }

    /**
     * Save security from main edit form.
     * Route: POST /{slug}/edit/security (AJAX)
     */
    public function saveFromEdit(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        $object = $this->getObjectBySlug($slug);

        if (!$object) {
            return $response->withStatus(404);
        }

        $classificationId = (int) ($data['security_classification_id'] ?? 0);

        if ($classificationId > 0) {
            SecurityClearanceService::classifyObject(
                $object->id,
                $classificationId,
                $currentUser['id'],
                $data['security_reason'] ?? null,
                !empty($data['security_review_date']) ? $data['security_review_date'] : null,
                !empty($data['security_declassify_date']) ? $data['security_declassify_date'] : null,
                null,
                $data['security_handling_instructions'] ?? null,
                isset($data['security_inherit_to_children']) && $data['security_inherit_to_children']
            );
        } else {
            // No classification selected - declassify if previously classified
            $existing = SecurityClearanceService::getObjectClassification($object->id);
            if ($existing) {
                SecurityClearanceService::declassifyObject($object->id, $currentUser['id'], 'Removed via edit form');
            }
        }

        $response->getBody()->write(json_encode(['success' => true]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Browse classified objects.
     * Route: GET /admin/security/objects
     */
    public function browseClassified(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = (int) ($params['page'] ?? 1);
        $levelFilter = $params['level'] ?? null;
        $perPage = 25;

        $query = DB::table('object_security_classification as osc')
            ->join('security_classification as sc', 'osc.classification_id', '=', 'sc.id')
            ->join('information_object as io', 'osc.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('user as cb', 'osc.classified_by', '=', 'cb.id')
            ->where('osc.active', 1)
            ->select([
                'osc.*',
                'sc.code as classification_code',
                'sc.name as classification_name',
                'sc.level as classification_level',
                'sc.color as classification_color',
                'sc.icon as classification_icon',
                'ioi.title',
                'io.identifier',
                'slug.slug',
                'cb.username as classified_by_username',
            ]);

        if ($levelFilter !== null) {
            $query->where('sc.level', (int) $levelFilter);
        }

        $total = $query->count();

        $objects = $query
            ->orderBy('sc.level', 'desc')
            ->orderBy('osc.classified_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        $classifications = SecurityClearanceService::getAllClassifications();
        $stats = SecurityClearanceService::getSecurityStatistics();

        return $this->view->render($response, 'security/objects/index.blade.php', [
            'objects' => $objects,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'levelFilter' => $levelFilter,
            'classifications' => $classifications,
            'stats' => $stats,
            'pageTitle' => 'Classified Objects',
        ]);
    }

    /**
     * Security dashboard.
     * Route: GET /admin/security
     */
    public function dashboard(Request $request, Response $response): Response
    {
        $stats = SecurityClearanceService::getSecurityStatistics();
        $pendingReviews = SecurityClearanceService::getObjectsDueForReview();
        $classifications = SecurityClearanceService::getAllClassifications();

        // Expiring clearances
        $expiringClearances = DB::table('user_security_clearance as usc')
            ->join('user as u', 'usc.user_id', '=', 'u.id')
            ->join('security_classification as sc', 'usc.classification_id', '=', 'sc.id')
            ->whereNotNull('usc.expires_at')
            ->where('usc.expires_at', '<=', date('Y-m-d', strtotime('+30 days')))
            ->where('usc.expires_at', '>', date('Y-m-d'))
            ->select([
                'usc.*',
                'u.username',
                'sc.name as classification_name',
            ])
            ->orderBy('usc.expires_at', 'asc')
            ->limit(10)
            ->get()
            ->toArray();

        // Recent activity
        $recentActivity = DB::table('security_access_log as sal')
            ->join('user as u', 'sal.user_id', '=', 'u.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('sal.object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', CultureHelper::getCulture());
            })
            ->select([
                'sal.*',
                'u.username',
                'ioi.title as object_title',
            ])
            ->orderBy('sal.created_at', 'desc')
            ->limit(20)
            ->get()
            ->toArray();

        return $this->view->render($response, 'security/dashboard.blade.php', [
            'stats' => $stats,
            'pendingReviews' => array_slice($pendingReviews, 0, 10),
            'expiringClearances' => $expiringClearances,
            'recentActivity' => $recentActivity,
            'classifications' => $classifications,
            'pageTitle' => 'Security Dashboard',
        ]);
    }

    /**
     * Get object by slug.
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
            ->select([
                'io.*',
                'ioi.title',
                'slug.slug',
            ])
            ->first();
    }

    /**
     * Redirect helper.
     */
    private function redirect(Response $response, string $url): Response
    {
        return $response
            ->withHeader('Location', $url)
            ->withStatus(302);
    }
}
