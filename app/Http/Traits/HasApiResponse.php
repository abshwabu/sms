<?php

namespace App\Http\Traits;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait HasApiResponse
{
    /**
     * Send a standardized success response.
     */
    protected function respondWithSuccess(
        mixed $data = null,
        ?string $message = null,
        int $status = Response::HTTP_OK,
        array $meta = [],
        array $links = []
    ): JsonResponse {
        return ApiResponse::success($data, $message, $status, $meta, $links);
    }

    /**
     * Send a standardized paginated response.
     */
    protected function respondWithPagination(
        LengthAwarePaginator $paginator,
        ?string $message = null,
        int $status = Response::HTTP_OK,
        array $meta = []
    ): JsonResponse {
        return ApiResponse::paginated($paginator, $message, $status, $meta);
    }

    /**
     * Send a standardized error response.
     */
    protected function respondWithError(
        string $message,
        string $code = 'BAD_REQUEST',
        int $status = Response::HTTP_BAD_REQUEST,
        mixed $details = null
    ): JsonResponse {
        return ApiResponse::error($message, $code, $status, $details);
    }
}
