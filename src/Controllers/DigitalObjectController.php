<?php

declare(strict_types=1);

namespace AtomExtensions\Controllers;

use AtomExtensions\Helpers\CultureHelper;

use AtomExtensions\Services\DigitalObjectService;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Digital Object Controller
 * 
 * Handles digital object upload, import, and management via Laravel.
 * Provides both JSON API and traditional form handling.
 * Pure Laravel Query Builder implementation.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class DigitalObjectController
{
    protected DigitalObjectService $service;
    
    public function __construct()
    {
        $this->service = new DigitalObjectService();
    }
    
    /**
     * Show the add digital object form
     */
    public function showAddForm(int $objectId): string
    {
        // Get the information object
        $resource = $this->getInformationObject($objectId);
        if (!$resource) {
            return $this->renderError('Object not found', 404);
        }
        
        // Check authorization
        if (!$this->checkAccess($objectId, 'update')) {
            return $this->renderError('Unauthorized', 403);
        }
        
        // Check if already has digital object
        $existingDo = DB::table('digital_object')
            ->where('object_id', $objectId)
            ->first();
            
        if ($existingDo) {
            $slug = DB::table('slug')
                ->where('object_id', $existingDo->id)
                ->value('slug');
            header('Location: /index.php/digitalobject/edit?slug=' . $slug);
            exit;
        }
        
        // Check upload limits
        $limitCheck = $this->service->checkUploadLimits($objectId);
        
        // Get repository for display
        $repository = $this->getRepository($objectId);
        
        // Build resource description
        $resourceDescription = '';
        if ($resource->identifier) {
            $resourceDescription .= $resource->identifier . ' - ';
        }
        $resourceDescription .= $resource->title ?? '';
        
        // Get upload limits for display
        $maxUploadSize = $this->service->getMaxUploadSize();
        $maxUploadFormatted = $this->service->formatBytes($maxUploadSize);
        
        // Render the template
        return $this->renderTemplate('add', [
            'resource' => $resource,
            'resourceId' => $objectId,
            'resourceDescription' => $resourceDescription,
            'repository' => $repository,
            'uploadAllowed' => $limitCheck['allowed'],
            'uploadMessage' => $limitCheck['message'],
            'maxUploadSize' => $maxUploadSize,
            'maxUploadFormatted' => $maxUploadFormatted,
            'csrfToken' => $this->generateCsrfToken(),
        ]);
    }
    
    /**
     * Handle digital object upload (form POST)
     */
    public function handleUpload($request): string
    {
        $objectId = (int) $request->input('object_id');
        
        // Validate CSRF
        if (!$this->validateCsrfToken($request->input('_token'))) {
            return $this->renderError('Invalid security token', 403);
        }
        
        // Get the information object
        $resource = $this->getInformationObject($objectId);
        if (!$resource) {
            return $this->renderError('Object not found', 404);
        }
        
        // Check authorization
        if (!$this->checkAccess($objectId, 'update')) {
            return $this->renderError('Unauthorized', 403);
        }
        
        $result = ['success' => false, 'message' => 'No file or URL provided'];
        
        // Handle file upload
        if ($request->hasFile('file')) {
            $result = $this->service->uploadFromFile(
                $objectId,
                $request->file('file'),
                [
                    'createDerivatives' => true,
                    'extractMetadata' => $request->boolean('extract_metadata', true),
                ]
            );
        }
        // Handle URL import
        elseif ($url = $request->input('url')) {
            if ($url !== 'http://' && !empty($url)) {
                $result = $this->service->importFromUrl($objectId, $url, [
                    'createDerivatives' => true,
                ]);
            }
        }
        
        if ($result['success']) {
            // Get slug for redirect
            $slug = DB::table('slug')
                ->where('object_id', $objectId)
                ->value('slug');
            
            header('Location: /index.php/' . $slug);
            exit;
        }
        
        // Show error on the form
        return $this->showAddForm($objectId) . $this->renderFlash('error', $result['message']);
    }
    
    /**
     * API: Upload digital object (JSON response)
     */
    public function apiUpload($request): array
    {
        $objectId = (int) ($request->input('object_id') ?? 0);
        
        if (!$objectId) {
            return ['success' => false, 'message' => 'object_id required', 'code' => 400];
        }
        
        $resource = $this->getInformationObject($objectId);
        if (!$resource) {
            return ['success' => false, 'message' => 'Object not found', 'code' => 404];
        }
        
        if (!$this->checkAccess($objectId, 'update')) {
            return ['success' => false, 'message' => 'Unauthorized', 'code' => 403];
        }
        
        // Handle file upload
        if ($request->hasFile('file')) {
            $result = $this->service->uploadFromFile(
                $objectId,
                $request->file('file'),
                [
                    'replace' => $request->boolean('replace', false),
                    'createDerivatives' => $request->boolean('create_derivatives', true),
                    'extractMetadata' => $request->boolean('extract_metadata', true),
                ]
            );
        }
        // Handle URL import
        elseif ($url = $request->input('url')) {
            $result = $this->service->importFromUrl($objectId, $url, [
                'replace' => $request->boolean('replace', false),
                'createDerivatives' => $request->boolean('create_derivatives', true),
            ]);
        }
        else {
            return ['success' => false, 'message' => 'No file or URL provided', 'code' => 400];
        }
        
        $result['code'] = $result['success'] ? 200 : 400;
        return $result;
    }
    
    /**
     * API: Get digital object info
     */
    public function apiGetInfo(int $id): array
    {
        $info = $this->service->getInfo($id);
        
        if (!$info) {
            return ['success' => false, 'message' => 'Not found', 'code' => 404];
        }
        
        return ['success' => true, 'data' => $info, 'code' => 200];
    }
    
    /**
     * API: Delete digital object
     */
    public function apiDelete(int $id): array
    {
        $digitalObject = DB::table('digital_object')
            ->where('id', $id)
            ->first();
            
        if (!$digitalObject) {
            return ['success' => false, 'message' => 'Not found', 'code' => 404];
        }
        
        if ($digitalObject->object_id && !$this->checkAccess($digitalObject->object_id, 'delete')) {
            return ['success' => false, 'message' => 'Unauthorized', 'code' => 403];
        }
        
        $result = $this->service->delete($id);
        $result['code'] = $result['success'] ? 200 : 400;
        
        return $result;
    }
    
    /**
     * API: Regenerate derivatives
     */
    public function apiRegenerateDerivatives(int $id): array
    {
        $digitalObject = DB::table('digital_object')
            ->where('id', $id)
            ->first();
            
        if (!$digitalObject) {
            return ['success' => false, 'message' => 'Not found', 'code' => 404];
        }
        
        if ($digitalObject->object_id && !$this->checkAccess($digitalObject->object_id, 'update')) {
            return ['success' => false, 'message' => 'Unauthorized', 'code' => 403];
        }
        
        $result = $this->service->regenerateDerivatives($id);
        $result['code'] = $result['success'] ? 200 : 400;
        
        return $result;
    }
    
    /**
     * API: Check upload limits
     */
    public function apiCheckLimits(int $objectId): array
    {
        $resource = $this->getInformationObject($objectId);
        if (!$resource) {
            return ['success' => false, 'message' => 'Object not found', 'code' => 404];
        }
        
        $limits = $this->service->checkUploadLimits($objectId);
        $limits['max_upload_size'] = $this->service->getMaxUploadSize();
        $limits['max_upload_formatted'] = $this->service->formatBytes($limits['max_upload_size']);
        
        return ['success' => true, 'data' => $limits, 'code' => 200];
    }
    
    /**
     * Get information object by ID
     */
    protected function getInformationObject(int $id): ?object
    {
        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($join) {
                $join->on('io.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
            ->where('io.id', $id)
            ->select(
                'io.id',
                'io.identifier',
                'io.parent_id',
                'io.repository_id',
                'i18n.title',
                's.slug'
            )
            ->first();
    }
    
    /**
     * Get repository for information object (with inheritance)
     */
    protected function getRepository(int $objectId): ?object
    {
        // First check direct repository
        $io = DB::table('information_object')
            ->where('id', $objectId)
            ->first();
            
        if (!$io) {
            return null;
        }
        
        // If has repository_id, return it
        if ($io->repository_id) {
            return DB::table('actor as a')
                ->leftJoin('actor_i18n as i18n', function ($join) {
                    $join->on('a.id', '=', 'i18n.id')
                        ->where('i18n.culture', '=', CultureHelper::getCulture());
                })
                ->leftJoin('slug as s', 'a.id', '=', 's.object_id')
                ->where('a.id', $io->repository_id)
                ->select('a.id', 'i18n.authorized_form_of_name as name', 's.slug')
                ->first();
        }
        
        // Walk up hierarchy to find inherited repository
        $parentId = $io->parent_id;
        $maxDepth = 50;
        $depth = 0;
        
        while ($parentId && $depth < $maxDepth) {
            $parent = DB::table('information_object')
                ->where('id', $parentId)
                ->first();
                
            if (!$parent) {
                break;
            }
            
            if ($parent->repository_id) {
                return DB::table('actor as a')
                    ->leftJoin('actor_i18n as i18n', function ($join) {
                        $join->on('a.id', '=', 'i18n.id')
                            ->where('i18n.culture', '=', CultureHelper::getCulture());
                    })
                    ->leftJoin('slug as s', 'a.id', '=', 's.object_id')
                    ->where('a.id', $parent->repository_id)
                    ->select('a.id', 'i18n.authorized_form_of_name as name', 's.slug')
                    ->first();
            }
            
            $parentId = $parent->parent_id;
            $depth++;
        }
        
        return null;
    }
    
    /**
     * Check user access for an object
     */
    protected function checkAccess(int $objectId, string $action): bool
    {
        // Get current user from session
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            return false;
        }
        
        // Check user groups
        $userGroups = DB::table('acl_user_group')
            ->where('user_id', $userId)
            ->pluck('group_id')
            ->toArray();
        
        // Administrator (100) and Editor (101) have full access
        if (array_intersect([100, 101], $userGroups)) {
            return true;
        }
        
        // Contributor (102) can update/create but not delete
        if (in_array(102, $userGroups)) {
            if (in_array($action, ['read', 'create', 'update'])) {
                return true;
            }
        }
        
        // Check object-specific permissions
        $permission = DB::table('acl_permission')
            ->where('object_id', $objectId)
            ->where('user_id', $userId)
            ->where('action', $action)
            ->first();
        
        if ($permission) {
            return (bool) $permission->grant_deny;
        }
        
        // Check group permissions
        if (!empty($userGroups)) {
            $groupPermission = DB::table('acl_permission')
                ->where('object_id', $objectId)
                ->whereIn('group_id', $userGroups)
                ->where('action', $action)
                ->first();
            
            if ($groupPermission) {
                return (bool) $groupPermission->grant_deny;
            }
        }
        
        // Default: authenticated users can read
        return $action === 'read';
    }
    
    /**
     * Render a template
     */
    protected function renderTemplate(string $template, array $data = []): string
    {
        $templatePath = __DIR__ . '/../resources/views/digitalobject/' . $template . '.php';
        
        if (!file_exists($templatePath)) {
            return $this->renderError('Template not found: ' . $template, 500);
        }
        
        extract($data);
        
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }
    
    /**
     * Render error page
     */
    protected function renderError(string $message, int $code = 400): string
    {
        http_response_code($code);
        $escapedMessage = htmlspecialchars($message, ENT_QUOTES);
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Error {$code}</title>
    <link rel="stylesheet" href="/plugins/ahgThemeB5Plugin/css/min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-danger">
            <h4>Error {$code}</h4>
            <p>{$escapedMessage}</p>
            <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Render flash message
     */
    protected function renderFlash(string $type, string $message): string
    {
        $class = $type === 'error' ? 'danger' : $type;
        $escapedMessage = htmlspecialchars($message, ENT_QUOTES);
        return <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function() {
    var flash = document.createElement('div');
    flash.className = 'alert alert-{$class} alert-dismissible fade show position-fixed';
    flash.style.cssText = 'top:20px;right:20px;z-index:9999;max-width:400px;';
    flash.innerHTML = '{$escapedMessage}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    document.body.appendChild(flash);
    setTimeout(function() { flash.remove(); }, 5000);
});
</script>
HTML;
    }
    
    /**
     * Generate CSRF token
     */
    protected function generateCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    protected function validateCsrfToken(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
    }
}