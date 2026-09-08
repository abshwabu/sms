<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\HasApiResponse;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends Controller
{
    use HasApiResponse;

    /**
     * Get role-tailored landing dashboard data for the authenticated user.
     * Acceptance criterion: Each role sees only the widgets/data relevant to their
     * scope on first login, no manual navigation required to see the essentials.
     */
    public function index(Request $request, DashboardService $dashboardService): JsonResponse
    {
        $user = $request->user();
        $requestedRole = $request->query('role');
        $childId = $request->query('child_id') ? (int) $request->query('child_id') : null;
        $day = $request->query('day');

        // Security check: non-admins cannot impersonate or view unauthorized roles
        if ($requestedRole) {
            if ($requestedRole === RoleEnum::SUPER_ADMIN->value && ! $user->isSuperAdmin()) {
                return ApiResponse::error(
                    'Unauthorized to view platform super-admin dashboard.',
                    'FORBIDDEN_DASHBOARD_ROLE',
                    Response::HTTP_FORBIDDEN
                );
            }

            if ($requestedRole !== $user->role && ! $user->isSchoolAdmin()) {
                return ApiResponse::error(
                    'You do not have permission to view other role dashboards.',
                    'FORBIDDEN_DASHBOARD_ROLE',
                    Response::HTTP_FORBIDDEN
                );
            }
        }

        $data = $dashboardService->getDashboardForUser(
            user: $user,
            requestedRole: $requestedRole,
            childId: $childId,
            requestedDay: $day
        );

        return $this->respondWithSuccess($data, 'Role-based dashboard data retrieved successfully.');
    }
}
