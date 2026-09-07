<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAcademicYearRequest;
use App\Http\Traits\HasApiResponse;
use App\Models\AcademicYear;
use App\Tenancy\Exceptions\ClosedAcademicYearException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AcademicYearController extends Controller
{
    use HasApiResponse;

    /**
     * Display a listing of academic years for the active school.
     */
    public function index(): JsonResponse
    {
        $years = AcademicYear::withCount(['terms', 'sections', 'studentAssignments'])
            ->orderBy('start_date', 'desc')
            ->get();

        return $this->respondWithSuccess($years, 'Academic years retrieved successfully.');
    }

    /**
     * Store a newly created academic year.
     */
    public function store(StoreAcademicYearRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $academicYear = AcademicYear::create($validated);

        if ($academicYear->is_active) {
            $academicYear->activate();
        }

        return $this->respondWithSuccess(
            $academicYear,
            'Academic year created successfully.',
            Response::HTTP_CREATED
        );
    }

    /**
     * Display the specified academic year with terms and sections.
     */
    public function show(AcademicYear $academicYear): JsonResponse
    {
        $academicYear->load([
            'terms',
            'sections.gradeLevel',
            'sections.homeroomTeacher:id,name,email',
        ]);
        $academicYear->loadCount(['studentAssignments']);

        return $this->respondWithSuccess($academicYear, 'Academic year details retrieved.');
    }

    /**
     * Update the specified academic year.
     */
    public function update(StoreAcademicYearRequest $request, AcademicYear $academicYear): JsonResponse
    {
        if ($academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot modify a closed academic year. Historical records are read-only.');
        }

        $academicYear->update($request->validated());

        if ($academicYear->is_active) {
            $academicYear->activate();
        }

        return $this->respondWithSuccess($academicYear, 'Academic year updated successfully.');
    }

    /**
     * Close the academic year, locking it as read-only history.
     */
    public function close(AcademicYear $academicYear): JsonResponse
    {
        $academicYear->close();

        return $this->respondWithSuccess(
            $academicYear,
            'Academic year closed successfully. Historical records are now read-only.'
        );
    }

    /**
     * Activate the academic year.
     */
    public function activate(AcademicYear $academicYear): JsonResponse
    {
        if ($academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot activate a closed academic year.');
        }

        $academicYear->activate();

        return $this->respondWithSuccess($academicYear, 'Academic year set as active.');
    }
}
