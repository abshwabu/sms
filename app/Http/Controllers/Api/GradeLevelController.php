<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGradeLevelRequest;
use App\Http\Traits\HasApiResponse;
use App\Models\GradeLevel;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class GradeLevelController extends Controller
{
    use HasApiResponse;

    /**
     * Display a listing of grade levels ordered by sequence.
     */
    public function index(): JsonResponse
    {
        $gradeLevels = GradeLevel::ordered()->withCount('sections')->get();

        return $this->respondWithSuccess($gradeLevels, 'Grade levels retrieved successfully.');
    }

    /**
     * Store a newly created grade level.
     */
    public function store(StoreGradeLevelRequest $request): JsonResponse
    {
        $gradeLevel = GradeLevel::create($request->validated());

        return $this->respondWithSuccess($gradeLevel, 'Grade level created successfully.', Response::HTTP_CREATED);
    }

    /**
     * Display the specified grade level.
     */
    public function show(GradeLevel $gradeLevel): JsonResponse
    {
        $gradeLevel->load('sections.academicYear');

        return $this->respondWithSuccess($gradeLevel, 'Grade level details retrieved.');
    }

    /**
     * Update the specified grade level.
     */
    public function update(StoreGradeLevelRequest $request, GradeLevel $gradeLevel): JsonResponse
    {
        $gradeLevel->update($request->validated());

        return $this->respondWithSuccess($gradeLevel, 'Grade level updated successfully.');
    }

    /**
     * Remove the specified grade level.
     */
    public function destroy(GradeLevel $gradeLevel): JsonResponse
    {
        $gradeLevel->delete();

        return $this->respondWithSuccess(null, 'Grade level deleted successfully.');
    }
}
