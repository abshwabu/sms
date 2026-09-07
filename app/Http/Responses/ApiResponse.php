<?php

namespace App\Http\Responses;

use App\Tenancy\TenantManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    /**
     * Return a standardized success JSON response.
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = Response::HTTP_OK,
        array $meta = [],
        array $links = []
    ): JsonResponse {
        $tenantManager = app(TenantManager::class);

        $defaultMeta = [
            'timestamp' => now()->toIso8601String(),
            'version' => 'v1',
        ];

        if ($tenantManager->hasTenant()) {
            $school = $tenantManager->getTenant();
            $defaultMeta['tenant'] = [
                'id' => $school->id,
                'name' => $school->name,
                'subdomain' => $school->subdomain,
            ];
        }

        $payload = [
            'success' => true,
            'data' => $data,
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        $payload['meta'] = array_merge($defaultMeta, $meta);

        if (! empty($links)) {
            $payload['links'] = $links;
        }

        return response()->json($payload, $status);
    }

    /**
     * Return a standardized paginated JSON response.
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        ?string $message = null,
        int $status = Response::HTTP_OK,
        array $meta = []
    ): JsonResponse {
        $paginationMeta = [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];

        $links = [
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];

        return self::success(
            data: $paginator->items(),
            message: $message,
            status: $status,
            meta: array_merge($paginationMeta, $meta),
            links: $links
        );
    }

    /**
     * Return a standardized error JSON response.
     */
    public static function error(
        string $message,
        string $code = 'BAD_REQUEST',
        int $status = Response::HTTP_BAD_REQUEST,
        mixed $details = null
    ): JsonResponse {
        $payload = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($details !== null) {
            $payload['error']['details'] = $details;
        }

        return response()->json($payload, $status);
    }

    /**
     * Return a validation error response.
     */
    public static function validationError(
        mixed $errors,
        string $message = 'Validation failed.'
    ): JsonResponse {
        return self::error(
            message: $message,
            code: 'VALIDATION_ERROR',
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
            details: $errors
        );
    }
}
