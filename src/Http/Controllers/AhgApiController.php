<?php

namespace AtomFramework\Http\Controllers;

use AtomFramework\Helpers\ResponseHelper;
use Illuminate\Http\JsonResponse;

/**
 * API-specific base controller for AHG plugins.
 *
 * Extends AhgController with the SAME API as AhgApiAction so that
 * API action classes can swap base class with minimal changes.
 *
 * Dual-stack compatible: works through both Symfony index.php and heratio.php.
 */
class AhgApiController extends AhgController
{
    /** @var float Request start time for duration tracking */
    protected $startTime;

    /** @var array|null Authenticated API key info */
    protected $apiKeyInfo = null;

    /** @var object|null API repository instance */
    protected $repository;

    /** @var object|null API key service instance */
    protected $apiKeyService;

    /** @var bool Whether bootstrap has been loaded */
    protected $bootstrapped = false;

    // ─── Boot ────────────────────────────────────────────────────────

    public function boot(): void
    {
        $this->startTime = microtime(true);
        $this->loadBootstrap();
        $this->setCorsHeaders();

        if (class_exists('\AhgAPIPlugin\Service\ApiKeyService', true)) {
            $this->apiKeyService = new \AhgAPIPlugin\Service\ApiKeyService();
        }
    }

    protected function loadBootstrap(): void
    {
        if ($this->bootstrapped) {
            return;
        }

        $rootDir = $this->config('sf_root_dir', '');
        if (empty($rootDir) && class_exists('\sfConfig', false)) {
            $rootDir = \sfConfig::get('sf_root_dir', '');
        }

        if ($rootDir) {
            $apiLib = $rootDir . '/atom-ahg-plugins/ahgAPIPlugin/lib';
            if (is_dir($apiLib)) {
                $repoFile = $apiLib . '/repository/ApiRepository.php';
                if (file_exists($repoFile)) {
                    require_once $repoFile;
                }
                $serviceFile = $apiLib . '/service/ApiKeyService.php';
                if (file_exists($serviceFile)) {
                    require_once $serviceFile;
                }
            }
        }

        spl_autoload_register(function ($class) {
            if (strpos($class, 'AhgAPI\\Services\\') === 0) {
                $relativePath = str_replace('AhgAPI\\Services\\', '', $class);
                $rootDir = $this->config('sf_root_dir', '');
                if (empty($rootDir) && class_exists('\sfConfig', false)) {
                    $rootDir = \sfConfig::get('sf_root_dir', '');
                }
                $filePath = $rootDir . '/atom-ahg-plugins/ahgAPIPlugin/lib/Services/' . $relativePath . '.php';
                if (file_exists($filePath)) {
                    require_once $filePath;
                    return true;
                }
            }
            return false;
        });

        $this->bootstrapped = true;
    }

    protected function setCorsHeaders(): void
    {
        $this->getResponse()->setHttpHeader('Content-Type', 'application/json; charset=utf-8');
        $this->getResponse()->setHttpHeader('Access-Control-Allow-Origin', '*');
        $this->getResponse()->setHttpHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $this->getResponse()->setHttpHeader('Access-Control-Allow-Headers', 'Content-Type, X-API-Key, Authorization');
    }

    // ─── Authentication ──────────────────────────────────────────────

    protected function authenticate(): bool
    {
        $sfUser = $this->getUser();

        if ($sfUser->isAuthenticated()) {
            $userId = $sfUser->getAttribute('user_id');
            $this->apiKeyInfo = [
                'type' => 'session',
                'id' => null,
                'user_id' => $userId,
                'scopes' => ['read', 'write', 'delete'],
                'rate_limit' => 10000,
            ];
            return true;
        }

        if ($this->apiKeyService) {
            $this->apiKeyInfo = $this->apiKeyService->authenticate();
            if ($this->apiKeyInfo) {
                if (class_exists('\QubitUser', false)) {
                    $user = \QubitUser::getById($this->apiKeyInfo['user_id']);
                    if ($user) {
                        $sfUser->signIn($user);
                        return true;
                    }
                } else {
                    return true;
                }
            }
        }

        return false;
    }

    protected function hasScope(string $scope): bool
    {
        return in_array($scope, $this->apiKeyInfo['scopes'] ?? []);
    }

    protected function isAdmin(): bool
    {
        return $this->getUser()->isAdministrator();
    }

    // ─── HTTP Method Dispatch ────────────────────────────────────────

    protected function process($request)
    {
        $method = strtoupper(
            is_object($request) && method_exists($request, 'getMethod')
                ? $request->getMethod()
                : ($_SERVER['REQUEST_METHOD'] ?? 'GET')
        );

        if (!method_exists($this, $method)) {
            return $this->error(405, 'Method Not Allowed', "Method {$method} not supported");
        }

        $data = null;
        if (in_array($method, ['POST', 'PUT'])) {
            $data = $this->getJsonBody();
            if (null === $data) {
                $content = is_object($request) && method_exists($request, 'getContent')
                    ? $request->getContent()
                    : '';
                if (!empty($content)) {
                    return $this->error(400, 'Bad Request', 'Invalid JSON body');
                }
                $data = [];
            }
        }

        try {
            $result = $this->$method($request, $data);
            $this->logRequest(200);
            return $result;
        } catch (\Exception $e) {
            $this->logRequest(500);
            return $this->error(500, 'Server Error', $e->getMessage());
        }
    }

    // ─── Response Helpers ────────────────────────────────────────────

    protected function success($data, int $statusCode = 200)
    {
        $this->getResponse()->setStatusCode($statusCode);

        return $this->renderText(json_encode(
            ['success' => true, 'data' => $data],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ));
    }

    protected function error(int $statusCode, string $error = 'Error', string $message = '')
    {
        $this->getResponse()->setStatusCode($statusCode);
        $this->logRequest($statusCode);

        return $this->renderText(json_encode(
            ['success' => false, 'error' => $error, 'message' => $message],
            JSON_PRETTY_PRINT
        ));
    }

    protected function paginate($query, $request, ?string $message = null)
    {
        $page = max(1, (int) ($request->getParameter('page', 1) ?? 1));
        $perPage = max(1, min(100, (int) ($request->getParameter('limit', 25) ?? 25)));

        if ($query instanceof \Illuminate\Database\Query\Builder) {
            $total = $query->count();
            $items = $query->offset(($page - 1) * $perPage)->limit($perPage)->get()->toArray();
        } elseif (is_array($query)) {
            $total = count($query);
            $items = array_slice($query, ($page - 1) * $perPage, $perPage);
        } else {
            $total = 0;
            $items = [];
        }

        return $this->success([
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ],
        ]);
    }

    protected function notFound(string $message = 'Not Found')
    {
        return $this->error(404, 'Not Found', $message);
    }

    protected function unauthorized(string $message = 'Unauthorized')
    {
        return $this->error(401, 'Unauthorized', $message);
    }

    protected function forbidden(string $message = 'Forbidden')
    {
        return $this->error(403, 'Forbidden', $message);
    }

    // ─── Request Helpers ─────────────────────────────────────────────

    protected function getJsonBody(): array
    {
        $request = $this->getRequest();
        if (null === $request) {
            return [];
        }

        $content = method_exists($request, 'getContent') ? $request->getContent() : '';
        if (empty($content)) {
            return [];
        }

        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    // ─── Logging ─────────────────────────────────────────────────────

    protected function logRequest(int $statusCode): void
    {
        if (!$this->apiKeyService || !method_exists($this->apiKeyService, 'logRequest')) {
            return;
        }

        $duration = (int) ((microtime(true) - ($this->startTime ?? microtime(true))) * 1000);
        $request = $this->getRequest();

        $this->apiKeyService->logRequest([
            'api_key_id' => $this->apiKeyInfo['id'] ?? null,
            'user_id' => $this->apiKeyInfo['user_id'] ?? null,
            'method' => $request ? $request->getMethod() : 'GET',
            'endpoint' => $request ? $request->getPathInfo() : '',
            'status_code' => $statusCode,
            'duration_ms' => $duration,
        ]);
    }
}
