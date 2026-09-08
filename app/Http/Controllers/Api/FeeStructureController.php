<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeeStructureRequest;
use App\Http\Requests\UpdateFeeStructureRequest;
use App\Http\Traits\HasApiResponse;
use App\Models\FeeStructure;
use App\Services\BillingService;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class FeeStructureController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected BillingService $billingService,
        protected TenantManager $tenantManager
    ) {}

    /**
     * List fee structures configured for this tenant school.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', FeeStructure::class);

        $query = FeeStructure::query()->with(['academicYear', 'term', 'gradeLevel']);

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->query('academic_year_id'));
        }

        if ($request->filled('term_id')) {
            $query->where('term_id', $request->query('term_id'));
        }

        if ($request->filled('grade_level_id')) {
            $query->where('grade_level_id', $request->query('grade_level_id'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        $feeStructures = $query->orderBy('academic_year_id', 'desc')
            ->orderBy('term_id')
            ->orderBy('name')
            ->get();

        return $this->respondWithSuccess($feeStructures, 'Fee structures retrieved successfully.');
    }

    /**
     * Create a new fee structure configuration.
     */
    public function store(StoreFeeStructureRequest $request): JsonResponse
    {
        Gate::authorize('create', FeeStructure::class);

        $school = $this->tenantManager->getTenant();
        $feeStructure = $this->billingService->createFeeStructure($school, $request->validated());

        return $this->respondWithSuccess(
            $feeStructure->load(['academicYear', 'term', 'gradeLevel']),
            'Fee structure created successfully.',
            Response::HTTP_CREATED
        );
    }

    /**
     * Show a fee structure item.
     */
    public function show(FeeStructure $feeStructure): JsonResponse
    {
        Gate::authorize('view', $feeStructure);

        return $this->respondWithSuccess(
            $feeStructure->load(['academicYear', 'term', 'gradeLevel']),
            'Fee structure details retrieved.'
        );
    }

    /**
     * Update an existing fee structure.
     */
    public function update(UpdateFeeStructureRequest $request, FeeStructure $feeStructure): JsonResponse
    {
        Gate::authorize('update', $feeStructure);

        $feeStructure->update($request->validated());

        return $this->respondWithSuccess(
            $feeStructure->fresh(['academicYear', 'term', 'gradeLevel']),
            'Fee structure updated successfully.'
        );
    }

    /**
     * Delete a fee structure.
     */
    public function destroy(FeeStructure $feeStructure): JsonResponse
    {
        Gate::authorize('delete', $feeStructure);

        $feeStructure->delete();

        return $this->respondWithSuccess(null, 'Fee structure deleted successfully.');
    }
}
