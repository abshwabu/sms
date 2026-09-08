<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DropElectiveRequest;
use App\Http\Requests\SelectElectiveRequest;
use App\Http\Requests\StoreSubjectOfferingRequest;
use App\Http\Requests\UpdateSubjectOfferingRequest;
use App\Http\Traits\HasApiResponse;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Services\ElectiveService;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ElectiveController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected ElectiveService $electiveService,
        protected TenantManager $tenantManager
    ) {}

    /**
     * List subject offerings for a grade level and academic year.
     */
    public function indexOfferings(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', SubjectOffering::class);

        $gradeLevelId = $request->query('grade_level_id');
        $academicYearId = $request->query('academic_year_id');

        if (! $gradeLevelId) {
            return $this->respondWithError('grade_level_id query parameter is required.', 'VALIDATION_ERROR', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $school = $this->tenantManager->getTenant();
        $academicYear = $academicYearId
            ? AcademicYear::withoutGlobalScopes()->where('school_id', $school->id)->findOrFail($academicYearId)
            : AcademicYear::withoutGlobalScopes()->where('school_id', $school->id)->where('is_active', true)->firstOrFail();

        $gradeLevel = GradeLevel::withoutGlobalScopes()->where('school_id', $school->id)->findOrFail($gradeLevelId);

        $offerings = $this->electiveService->getGradeOfferings($gradeLevel, $academicYear);

        return $this->respondWithSuccess($offerings, 'Subject offerings retrieved successfully.');
    }

    /**
     * Create or configure a subject offering.
     */
    public function storeOffering(StoreSubjectOfferingRequest $request): JsonResponse
    {
        Gate::authorize('create', SubjectOffering::class);

        $offering = $this->electiveService->configureOffering($request->validated(), $request->user());

        return $this->respondWithSuccess($offering, 'Subject offering configured successfully.', Response::HTTP_CREATED);
    }

    /**
     * Update an existing subject offering.
     */
    public function updateOffering(UpdateSubjectOfferingRequest $request, SubjectOffering $subjectOffering): JsonResponse
    {
        Gate::authorize('update', $subjectOffering);

        $offering = $this->electiveService->updateOffering($subjectOffering, $request->validated(), $request->user());

        return $this->respondWithSuccess($offering, 'Subject offering updated successfully.');
    }

    /**
     * List students enrolled in a specific subject offering.
     */
    public function getOfferingStudents(SubjectOffering $subjectOffering): JsonResponse
    {
        Gate::authorize('view', $subjectOffering);

        $students = $subjectOffering->studentSelections()
            ->with(['student.user', 'student.currentSection'])
            ->get()
            ->map(fn ($sel) => [
                'selection_id' => $sel->id,
                'student_id' => $sel->student_id,
                'name' => $sel->student?->user?->name,
                'admission_number' => $sel->student?->admission_number,
                'section' => $sel->student?->currentSection?->name,
                'selected_at' => $sel->selected_at?->toIso8601String(),
                'status' => $sel->status,
            ]);

        return $this->respondWithSuccess([
            'offering' => [
                'id' => $subjectOffering->id,
                'subject_id' => $subjectOffering->subject_id,
                'subject_name' => $subjectOffering->subject?->name,
                'max_students' => $subjectOffering->max_students,
                'enrolled_count' => $students->count(),
            ],
            'students' => $students,
        ], 'Offering enrollments retrieved successfully.');
    }

    /**
     * Admin manually enrolls a student into an elective offering.
     */
    public function adminEnroll(Request $request, SubjectOffering $subjectOffering): JsonResponse
    {
        Gate::authorize('update', $subjectOffering);

        $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'ignore_window' => ['sometimes', 'boolean'],
        ]);

        $student = Student::withoutGlobalScopes()
            ->where('school_id', $subjectOffering->school_id)
            ->findOrFail($request->input('student_id'));

        $selection = $this->electiveService->enrollStudent(
            student: $student,
            subject: $subjectOffering->subject,
            academicYear: $subjectOffering->academicYear,
            actor: $request->user(),
            ignoreWindow: (bool) $request->input('ignore_window', true)
        );

        return $this->respondWithSuccess($selection, 'Student enrolled in elective successfully.', Response::HTTP_CREATED);
    }

    /**
     * Admin manually drops a student from an elective offering.
     */
    public function adminDrop(Request $request, SubjectOffering $subjectOffering): JsonResponse
    {
        Gate::authorize('update', $subjectOffering);

        $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
        ]);

        $student = Student::withoutGlobalScopes()
            ->where('school_id', $subjectOffering->school_id)
            ->findOrFail($request->input('student_id'));

        $this->electiveService->dropStudent(
            student: $student,
            subject: $subjectOffering->subject,
            academicYear: $subjectOffering->academicYear,
            actor: $request->user(),
            ignoreWindow: true
        );

        return $this->respondWithSuccess(null, 'Student dropped from elective successfully.');
    }

    /**
     * Student views available electives for their grade level.
     */
    public function getAvailableForStudent(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isStudent() || ! $user->student) {
            return $this->respondWithError('Only students can access this endpoint.', 'FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $academicYearId = $request->query('academic_year_id');
        $academicYear = $academicYearId
            ? AcademicYear::withoutGlobalScopes()->where('school_id', $user->school_id)->find($academicYearId)
            : null;

        $offerings = $this->electiveService->getAvailableElectivesForStudent($user->student, $academicYear);

        return $this->respondWithSuccess($offerings, 'Available electives retrieved successfully.');
    }

    /**
     * Student views their current elective selections.
     */
    public function getMySelections(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isStudent() || ! $user->student) {
            return $this->respondWithError('Only students can access this endpoint.', 'FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $academicYearId = $request->query('academic_year_id');
        $academicYear = $academicYearId
            ? AcademicYear::withoutGlobalScopes()->where('school_id', $user->school_id)->find($academicYearId)
            : null;

        $selections = $this->electiveService->getStudentSelections($user->student, $academicYear);

        return $this->respondWithSuccess($selections, 'Enrolled electives retrieved successfully.');
    }

    /**
     * Student selects an elective subject.
     */
    public function studentSelect(SelectElectiveRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isStudent() || ! $user->student) {
            return $this->respondWithError('Only students can access this endpoint.', 'FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $school = $this->tenantManager->getTenant();
        $academicYearId = $request->input('academic_year_id');
        $academicYear = $academicYearId
            ? AcademicYear::withoutGlobalScopes()->where('school_id', $school->id)->findOrFail($academicYearId)
            : AcademicYear::withoutGlobalScopes()->where('school_id', $school->id)->where('is_active', true)->firstOrFail();

        $subject = Subject::withoutGlobalScopes()->where('school_id', $school->id)->findOrFail($request->input('subject_id'));

        $selection = $this->electiveService->enrollStudent(
            student: $user->student,
            subject: $subject,
            academicYear: $academicYear,
            actor: $user
        );

        return $this->respondWithSuccess($selection, 'Elective subject selected successfully.', Response::HTTP_CREATED);
    }

    /**
     * Student drops an elective subject.
     */
    public function studentDrop(DropElectiveRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isStudent() || ! $user->student) {
            return $this->respondWithError('Only students can access this endpoint.', 'FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $school = $this->tenantManager->getTenant();
        $academicYearId = $request->input('academic_year_id');
        $academicYear = $academicYearId
            ? AcademicYear::withoutGlobalScopes()->where('school_id', $school->id)->findOrFail($academicYearId)
            : AcademicYear::withoutGlobalScopes()->where('school_id', $school->id)->where('is_active', true)->firstOrFail();

        $subject = Subject::withoutGlobalScopes()->where('school_id', $school->id)->findOrFail($request->input('subject_id'));

        $this->electiveService->dropStudent(
            student: $user->student,
            subject: $subject,
            academicYear: $academicYear,
            actor: $user
        );

        return $this->respondWithSuccess(null, 'Elective subject dropped successfully.');
    }

    /**
     * Parent views available electives for their child.
     */
    public function getAvailableForChild(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        if (! $user->isParent() || ! $user->parentProfile?->isLinkedTo($student)) {
            return $this->respondWithError('You are not authorized to view electives for this student.', 'FORBIDDEN_PARENT_ACCESS', Response::HTTP_FORBIDDEN);
        }

        $academicYearId = $request->query('academic_year_id');
        $academicYear = $academicYearId
            ? AcademicYear::withoutGlobalScopes()->where('school_id', $student->school_id)->find($academicYearId)
            : null;

        $offerings = $this->electiveService->getAvailableElectivesForStudent($student, $academicYear);

        return $this->respondWithSuccess($offerings, 'Child available electives retrieved successfully.');
    }

    /**
     * Parent views current elective selections for their child.
     */
    public function getChildSelections(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        if (! $user->isParent() || ! $user->parentProfile?->isLinkedTo($student)) {
            return $this->respondWithError('You are not authorized to view electives for this student.', 'FORBIDDEN_PARENT_ACCESS', Response::HTTP_FORBIDDEN);
        }

        $academicYearId = $request->query('academic_year_id');
        $academicYear = $academicYearId
            ? AcademicYear::withoutGlobalScopes()->where('school_id', $student->school_id)->find($academicYearId)
            : null;

        $selections = $this->electiveService->getStudentSelections($student, $academicYear);

        return $this->respondWithSuccess($selections, 'Child enrolled electives retrieved successfully.');
    }

    /**
     * Parent selects an elective on behalf of child.
     */
    public function parentSelect(SelectElectiveRequest $request, Student $student): JsonResponse
    {
        $user = $request->user();
        if (! $user->isParent() || ! $user->parentProfile?->isLinkedTo($student)) {
            return $this->respondWithError('You are not authorized to manage electives for this student.', 'FORBIDDEN_PARENT_ACCESS', Response::HTTP_FORBIDDEN);
        }

        $school = $this->tenantManager->getTenant();
        $academicYearId = $request->input('academic_year_id');
        $academicYear = $academicYearId
            ? AcademicYear::withoutGlobalScopes()->where('school_id', $school->id)->findOrFail($academicYearId)
            : AcademicYear::withoutGlobalScopes()->where('school_id', $school->id)->where('is_active', true)->firstOrFail();

        $subject = Subject::withoutGlobalScopes()->where('school_id', $school->id)->findOrFail($request->input('subject_id'));

        $selection = $this->electiveService->enrollStudent(
            student: $student,
            subject: $subject,
            academicYear: $academicYear,
            actor: $user
        );

        return $this->respondWithSuccess($selection, 'Elective subject selected for child successfully.', Response::HTTP_CREATED);
    }

    /**
     * Parent drops an elective on behalf of child.
     */
    public function parentDrop(DropElectiveRequest $request, Student $student): JsonResponse
    {
        $user = $request->user();
        if (! $user->isParent() || ! $user->parentProfile?->isLinkedTo($student)) {
            return $this->respondWithError('You are not authorized to manage electives for this student.', 'FORBIDDEN_PARENT_ACCESS', Response::HTTP_FORBIDDEN);
        }

        $school = $this->tenantManager->getTenant();
        $academicYearId = $request->input('academic_year_id');
        $academicYear = $academicYearId
            ? AcademicYear::withoutGlobalScopes()->where('school_id', $school->id)->findOrFail($academicYearId)
            : AcademicYear::withoutGlobalScopes()->where('school_id', $school->id)->where('is_active', true)->firstOrFail();

        $subject = Subject::withoutGlobalScopes()->where('school_id', $school->id)->findOrFail($request->input('subject_id'));

        $this->electiveService->dropStudent(
            student: $student,
            subject: $subject,
            academicYear: $academicYear,
            actor: $user
        );

        return $this->respondWithSuccess(null, 'Elective subject dropped for child successfully.');
    }
}
