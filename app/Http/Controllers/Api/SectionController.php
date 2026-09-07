<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignStudentRequest;
use App\Http\Requests\PromoteStudentsRequest;
use App\Http\Requests\StoreSectionRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\HasApiResponse;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\StudentSectionAssignment;
use App\Tenancy\Exceptions\ClosedAcademicYearException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SectionController extends Controller
{
    use HasApiResponse;

    /**
     * Display a listing of sections with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Section::with([
            'gradeLevel',
            'academicYear',
            'homeroomTeacher:id,name,email',
        ])->withCount('studentAssignments');

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->query('academic_year_id'));
        }

        if ($request->filled('grade_level_id')) {
            $query->where('grade_level_id', $request->query('grade_level_id'));
        }

        $sections = $query->orderBy('name')->get();

        return $this->respondWithSuccess($sections, 'Sections retrieved successfully.');
    }

    /**
     * Store a newly created section with homeroom teacher assigned.
     */
    public function store(StoreSectionRequest $request): JsonResponse
    {
        $academicYear = AcademicYear::findOrFail($request->input('academic_year_id'));

        if ($academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot create sections in a closed academic year.');
        }

        $section = Section::create($request->validated());
        $section->load(['gradeLevel', 'academicYear', 'homeroomTeacher:id,name,email']);

        return $this->respondWithSuccess($section, 'Section created successfully.', Response::HTTP_CREATED);
    }

    /**
     * Display the specified section with homeroom teacher and enrolled students.
     */
    public function show(Section $section): JsonResponse
    {
        $section->load([
            'gradeLevel',
            'academicYear',
            'homeroomTeacher:id,name,email',
            'studentAssignments.student:id,name,email',
        ]);

        return $this->respondWithSuccess($section, 'Section details retrieved.');
    }

    /**
     * Update the specified section.
     */
    public function update(Request $request, Section $section): JsonResponse
    {
        if ($section->academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot update sections in a closed academic year.');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:50'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'homeroom_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $section->update($validated);
        $section->load(['gradeLevel', 'academicYear', 'homeroomTeacher:id,name,email']);

        return $this->respondWithSuccess($section, 'Section updated successfully.');
    }

    /**
     * Remove the specified section.
     */
    public function destroy(Section $section): JsonResponse
    {
        if ($section->academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot delete sections in a closed academic year.');
        }

        $section->delete();

        return $this->respondWithSuccess(null, 'Section deleted successfully.');
    }

    /**
     * Assign a student to this section for the section's academic year.
     */
    public function assignStudent(AssignStudentRequest $request, Section $section): JsonResponse
    {
        if ($section->academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot enroll students into a section belonging to a closed academic year.');
        }

        if (! $section->hasAvailableCapacity()) {
            return ApiResponse::error(
                'Section capacity reached.',
                'SECTION_CAPACITY_EXCEEDED',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $validated = $request->validated();

        $assignment = StudentSectionAssignment::updateOrCreate(
            [
                'academic_year_id' => $section->academic_year_id,
                'student_id' => $validated['student_id'],
            ],
            [
                'school_id' => $section->school_id,
                'section_id' => $section->id,
                'roll_number' => $validated['roll_number'] ?? null,
                'status' => 'enrolled',
                'enrolled_at' => $validated['enrolled_at'] ?? now()->toDateString(),
            ]
        );

        $assignment->load(['student:id,name,email', 'section.gradeLevel']);

        return $this->respondWithSuccess($assignment, 'Student assigned to section successfully.', Response::HTTP_CREATED);
    }

    /**
     * Promotion concept: promotes students into next year's section without mutating history.
     */
    public function promote(PromoteStudentsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $targetYear = AcademicYear::findOrFail($validated['target_academic_year_id']);

        if ($targetYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot promote students into a closed academic year.');
        }

        $targetSection = Section::findOrFail($validated['target_section_id']);
        $promotedAssignments = [];

        foreach ($validated['student_ids'] as $studentId) {
            $assignment = StudentSectionAssignment::updateOrCreate(
                [
                    'academic_year_id' => $targetYear->id,
                    'student_id' => $studentId,
                ],
                [
                    'school_id' => $targetSection->school_id,
                    'section_id' => $targetSection->id,
                    'status' => 'enrolled',
                    'enrolled_at' => now()->toDateString(),
                ]
            );
            $promotedAssignments[] = $assignment;
        }

        return $this->respondWithSuccess([
            'target_academic_year' => $targetYear->name,
            'target_section' => $targetSection->name,
            'promoted_count' => count($promotedAssignments),
        ], 'Students promoted to new academic year section successfully.');
    }
}
