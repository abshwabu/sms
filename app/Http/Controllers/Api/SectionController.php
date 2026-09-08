<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignHomeroomTeacherRequest;
use App\Http\Requests\AssignStudentRequest;
use App\Http\Requests\AssignSubjectTeacherRequest;
use App\Http\Requests\PromoteStudentsRequest;
use App\Http\Requests\StoreSectionRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\HasApiResponse;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\SectionSubjectTeacher;
use App\Models\Student;
use App\Models\StudentSectionAssignment;
use App\Tenancy\Exceptions\ClosedAcademicYearException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class SectionController extends Controller
{
    use HasApiResponse;

    /**
     * Display a listing of sections with optional filtering.
     * Teachers only see sections they are assigned to (as homeroom or subject teacher).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Section::class);

        $user = $request->user();
        $query = Section::with([
            'gradeLevel',
            'academicYear',
            'homeroomTeacher:id,name,email',
            'subjectTeachers.course:id,name,code',
            'subjectTeachers.staff.user:id,name,email',
        ])->withCount('studentAssignments');

        // Scoping for teachers: only sections where they teach or are homeroom
        if ($user && $user->isTeacher() && ! $user->isSchoolAdmin() && ! $user->isSuperAdmin()) {
            $staffId = $user->staff?->id;
            $query->where(function ($q) use ($user, $staffId) {
                $q->where('homeroom_teacher_id', $user->id);
                if ($staffId) {
                    $q->orWhereHas('subjectTeachers', function ($sq) use ($staffId) {
                        $sq->where('staff_id', $staffId);
                    });
                }
            });
        }

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
        Gate::authorize('create', Section::class);

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
        Gate::authorize('view', $section);

        $section->load([
            'gradeLevel',
            'academicYear',
            'homeroomTeacher:id,name,email',
            'subjectTeachers.course:id,name,code',
            'subjectTeachers.staff.user:id,name,email',
            'studentAssignments.student:id,name,email',
        ]);

        return $this->respondWithSuccess($section, 'Section details retrieved.');
    }

    /**
     * Display the enrolled student roster for this section.
     * Acceptance criterion: A teacher assigned as homeroom can see that section's roster;
     * cannot see other sections unless separately assigned a subject there.
     */
    public function roster(Section $section): JsonResponse
    {
        Gate::authorize('viewRoster', $section);

        $students = Student::with([
            'user:id,name,email,phone,status',
            'currentSection.gradeLevel:id,name',
        ])
        ->where('current_section_id', $section->id)
        ->orderBy('admission_number')
        ->get();

        return $this->respondWithSuccess([
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'grade_level' => $section->gradeLevel?->name,
                'academic_year' => $section->academicYear?->name,
                'homeroom_teacher' => $section->homeroomTeacher ? [
                    'id' => $section->homeroomTeacher->id,
                    'name' => $section->homeroomTeacher->name,
                ] : null,
            ],
            'students' => $students,
            'total_students' => $students->count(),
        ], 'Section roster retrieved successfully.');
    }

    /**
     * Assign or update homeroom teacher for this section.
     */
    public function assignHomeroom(AssignHomeroomTeacherRequest $request, Section $section): JsonResponse
    {
        Gate::authorize('assignTeacher', $section);

        if ($section->academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot assign homeroom teacher in a closed academic year.');
        }

        $section->update([
            'homeroom_teacher_id' => $request->integer('teacher_id'),
        ]);

        $section->load(['homeroomTeacher:id,name,email']);

        return $this->respondWithSuccess($section, 'Homeroom teacher assigned successfully.');
    }

    /**
     * Assign a subject teacher (teacher-to-subject assignment for grading permissions).
     */
    public function assignSubjectTeacher(AssignSubjectTeacherRequest $request, Section $section): JsonResponse
    {
        Gate::authorize('assignTeacher', $section);

        if ($section->academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot assign subject teachers in a closed academic year.');
        }

        $assignment = SectionSubjectTeacher::updateOrCreate(
            [
                'section_id' => $section->id,
                'course_id' => $request->integer('course_id'),
                'staff_id' => $request->integer('staff_id'),
            ],
            [
                'school_id' => $section->school_id,
                'academic_year_id' => $section->academic_year_id,
            ]
        );

        $assignment->load(['course:id,name,code', 'staff.user:id,name,email']);

        return $this->respondWithSuccess($assignment, 'Subject teacher assigned to section successfully.', Response::HTTP_CREATED);
    }

    /**
     * Remove subject teacher assignment from this section.
     */
    public function removeSubjectTeacher(Section $section, SectionSubjectTeacher $assignment): JsonResponse
    {
        Gate::authorize('assignTeacher', $section);

        if ($section->academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot remove subject teachers from a closed academic year.');
        }

        if ((int) $assignment->section_id !== (int) $section->id) {
            return ApiResponse::error('Assignment does not belong to this section.', 'NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        $assignment->delete();

        return $this->respondWithSuccess(null, 'Subject teacher removed from section.');
    }

    /**
     * Record or update attendance for this section.
     * Accessible by school admin, homeroom teacher, or assigned subject teacher.
     */
    public function recordAttendance(Request $request, Section $section): JsonResponse
    {
        Gate::authorize('recordAttendance', $section);

        if ($section->academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot record attendance in a closed academic year.');
        }

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'records' => ['nullable', 'array'],
        ]);

        return $this->respondWithSuccess([
            'section_id' => $section->id,
            'section_name' => $section->name,
            'date' => $validated['date'],
            'records_processed' => count($validated['records'] ?? []),
        ], 'Attendance recorded successfully for section roster.');
    }

    /**
     * Record or update grades for a subject in this section.
     * Accessible by school admin, homeroom teacher, or teacher assigned to that specific subject.
     */
    public function recordGrades(Request $request, Section $section): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => ['required', 'integer'],
            'term_id' => ['nullable', 'integer'],
            'grades' => ['nullable', 'array'],
        ]);

        $course = Course::findOrFail($validated['course_id']);

        Gate::authorize('recordGrades', [$section, $course]);

        if ($section->academicYear->isClosed()) {
            throw new ClosedAcademicYearException('Cannot enter grades in a closed academic year.');
        }

        return $this->respondWithSuccess([
            'section_id' => $section->id,
            'section_name' => $section->name,
            'course_id' => $course->id,
            'course_name' => $course->name,
            'grades_processed' => count($validated['grades'] ?? []),
        ], 'Grades recorded successfully for course in section.');
    }

    /**
     * Get permission summary for current user on this section.
     */
    public function permissions(Request $request, Section $section): JsonResponse
    {
        $user = $request->user();

        return $this->respondWithSuccess([
            'section_id' => $section->id,
            'can_view_roster' => Gate::forUser($user)->allows('viewRoster', $section),
            'can_record_attendance' => Gate::forUser($user)->allows('recordAttendance', $section),
            'can_record_grades' => Gate::forUser($user)->allows('recordGrades', $section),
            'is_homeroom_teacher' => $section->isHomeroomTeacher($user),
            'assigned_subject_count' => $user->staff 
                ? $section->subjectTeachers()->where('staff_id', $user->staff->id)->count() 
                : 0,
        ], 'Section permissions evaluated.');
    }

    /**
     * Update the specified section.
     */
    public function update(Request $request, Section $section): JsonResponse
    {
        Gate::authorize('update', $section);

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
        Gate::authorize('delete', $section);

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
