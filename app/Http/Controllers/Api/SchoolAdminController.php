<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasApiResponse;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;

class SchoolAdminController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected TenantManager $tenantManager
    ) {}

    /**
     * Get list of users belonging to the active school.
     * Guarded: School Admin / Super Admin only.
     */
    public function users(): JsonResponse
    {
        $schoolId = $this->tenantManager->getTenantId();

        $users = User::where('school_id', $schoolId)
            ->select(['id', 'school_id', 'name', 'email', 'phone', 'role', 'status', 'last_login_at', 'created_at'])
            ->orderBy('name')
            ->get();

        return $this->respondWithSuccess($users, 'School users retrieved successfully.');
    }

    /**
     * Get school administration dashboard stats.
     * Guarded: School Admin / Super Admin only.
     */
    public function stats(): JsonResponse
    {
        $schoolId = $this->tenantManager->getTenantId();

        $stats = [
            'total_users' => User::where('school_id', $schoolId)->count(),
            'teachers' => User::where('school_id', $schoolId)->where('role', 'teacher')->count(),
            'students' => User::where('school_id', $schoolId)->where('role', 'student')->count(),
            'parents' => User::where('school_id', $schoolId)->where('role', 'parent')->count(),
        ];

        return $this->respondWithSuccess($stats, 'School admin statistics retrieved successfully.');
    }
}
