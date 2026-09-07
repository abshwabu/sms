<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\HasApiResponse;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected TenantManager $tenantManager
    ) {}

    /**
     * Check API, database, and tenant context resolution status.
     */
    public function __invoke(): JsonResponse
    {
        $dbStatus = 'connected';
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $dbStatus = 'disconnected: ' . $e->getMessage();
        }

        $tenantData = null;
        if ($this->tenantManager->hasTenant()) {
            $school = $this->tenantManager->getTenant();
            $tenantData = [
                'resolved' => true,
                'id' => $school->id,
                'name' => $school->name,
                'subdomain' => $school->subdomain,
                'subscription_status' => $school->subscription_status,
                'timezone' => $school->timezone,
            ];
        } else {
            $tenantData = [
                'resolved' => false,
                'message' => 'No active tenant context. Provide X-School-Id header or access via subdomain.',
            ];
        }

        return $this->respondWithSuccess([
            'status' => 'healthy',
            'database' => $dbStatus,
            'tenant' => $tenantData,
        ], 'System health check completed.');
    }
}
