<?php

namespace AtomFramework\Http\Controllers;

use AtomFramework\Helpers\ResponseHelper;
use Illuminate\Http\JsonResponse;

/**
 * API-specific base controller for AHG plugins.
 *
 * Extends AhgController with JSON response conveniences.
 * Replaces the AhgApiAction pattern for standalone mode.
 *
 * Usage:
 *   class myApiActions extends AhgApiController {
 *       public function executeList($request) {
 *           $items = SomeService::getAll();
 *           return $this->success($items, 'Items loaded');
 *       }
 *   }
 */
class AhgApiController extends AhgController
{
    /**
     * Return a success JSON response.
     */
    protected function success($data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        $this->explicitResponse = new JsonResponse(
            ResponseHelper::success($data, $message),
            $status
        );

        return $this->explicitResponse;
    }

    /**
     * Return an error JSON response.
     */
    protected function error(string $message, int $code = 400, $errors = null): JsonResponse
    {
        $this->explicitResponse = new JsonResponse(
            ResponseHelper::error($message, $code, $errors),
            $code
        );

        return $this->explicitResponse;
    }

    /**
     * Return a paginated JSON response.
     */
    protected function paginate($query, $request, ?string $message = null): JsonResponse
    {
        $page = max(1, (int) ($request->getParameter('page', 1) ?? 1));
        $perPage = max(1, min(100, (int) ($request->getParameter('limit', 25) ?? 25)));

        // If $query is a Laravel Query Builder instance, paginate it
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

        $this->explicitResponse = new JsonResponse(
            ResponseHelper::paginated($items, $total, $page, $perPage, $message),
            200
        );

        return $this->explicitResponse;
    }

    /**
     * Return a not found JSON response.
     */
    protected function notFound(string $resource = 'Resource'): JsonResponse
    {
        return $this->error("{$resource} not found", 404);
    }

    /**
     * Return a validation error JSON response.
     */
    protected function validationError(array $errors): JsonResponse
    {
        $this->explicitResponse = new JsonResponse(
            ResponseHelper::validationError($errors),
            422
        );

        return $this->explicitResponse;
    }

    /**
     * Return a forbidden JSON response.
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, 403);
    }

    /**
     * Return an unauthorized JSON response.
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, 401);
    }
}
