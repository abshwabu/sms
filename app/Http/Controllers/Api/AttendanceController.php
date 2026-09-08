<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordAttendanceRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\HasApiResponse;
use App\Models\SchoolCalendar;
use App\Models\Section;
use App\Models\Student;
use App\Services\AttendanceService;
use App\Tenancy\TenantManager;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AttendanceController extends Controller
{
    use HasApiResponse;

    /**
     * Record or update daily attendance for a section roster.
     * Accessible by School Admin, Homeroom Teacher, or assigned Subject Teacher.
     * Acceptance criterion: Teacher marks a full section's attendance in under 5 clicks.
     */
    public function recordSectionAttendance(
        RecordAttendanceRequest $request,
        Section $section,
        AttendanceService $attendanceService
    ): JsonResponse {
        Gate::authorize('recordAttendance', $section);

        $result = $attendanceService->recordDailyAttendance(
            section: $section,
            date: $request->input('date'),
            records: $request->input('records', []),
            defaultStatus: $request->input('default_status'),
            marker: $request->user()
        );

        return $this->respondWithSuccess(
            $result,
            'Daily attendance recorded successfully.',
            Response::HTTP_OK
        );
    }

    /**
     * Get section daily attendance roster and statistics for a specified date.
     */
    public function getSectionDailyAttendance(
        Request $request,
        Section $section,
        AttendanceService $attendanceService
    ): JsonResponse {
        Gate::authorize('view', $section);

        $date = $request->query('date', now()->format('Y-m-d'));
        $data = $attendanceService->getSectionDailyAttendance($section, $date);

        return $this->respondWithSuccess($data, 'Section daily attendance retrieved successfully.');
    }

    /**
     * Get attendance summary and daily trends for a section.
     */
    public function getSectionAttendanceSummary(
        Request $request,
        Section $section,
        AttendanceService $attendanceService
    ): JsonResponse {
        Gate::authorize('view', $section);

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $data = $attendanceService->getSectionAttendanceSummary($section, $startDate, $endDate);

        return $this->respondWithSuccess($data, 'Section attendance summary retrieved successfully.');
    }

    /**
     * Get attendance summary for a specific student (per term or year).
     * Accessible by School Admin, Teachers, Parents of the student, or the Student themselves.
     */
    public function getStudentAttendanceSummary(
        Request $request,
        Student $student,
        AttendanceService $attendanceService
    ): JsonResponse {
        Gate::authorize('view', $student);

        $data = $attendanceService->getStudentAttendanceSummary(
            student: $student,
            academicYearId: $request->query('academic_year_id') ? (int) $request->query('academic_year_id') : null,
            termId: $request->query('term_id') ? (int) $request->query('term_id') : null,
            startDate: $request->query('start_date'),
            endDate: $request->query('end_date')
        );

        return $this->respondWithSuccess($data, 'Student attendance summary retrieved successfully.');
    }

    /**
     * Get attendance summary for the authenticated student.
     */
    public function getMyStudentAttendance(
        Request $request,
        AttendanceService $attendanceService
    ): JsonResponse {
        $user = $request->user();

        if (! $user->isStudent() || ! $user->student) {
            return ApiResponse::error(
                'User is not associated with a student record.',
                'NOT_A_STUDENT',
                Response::HTTP_FORBIDDEN
            );
        }

        $data = $attendanceService->getStudentAttendanceSummary(
            student: $user->student,
            academicYearId: $request->query('academic_year_id') ? (int) $request->query('academic_year_id') : null,
            termId: $request->query('term_id') ? (int) $request->query('term_id') : null,
            startDate: $request->query('start_date'),
            endDate: $request->query('end_date')
        );

        return $this->respondWithSuccess($data, 'Student attendance summary retrieved successfully.');
    }

    /**
     * Get attendance summary for a parent's linked child.
     */
    public function getParentChildAttendance(
        Request $request,
        Student $student,
        AttendanceService $attendanceService
    ): JsonResponse {
        Gate::authorize('view', $student);

        $data = $attendanceService->getStudentAttendanceSummary(
            student: $student,
            academicYearId: $request->query('academic_year_id') ? (int) $request->query('academic_year_id') : null,
            termId: $request->query('term_id') ? (int) $request->query('term_id') : null,
            startDate: $request->query('start_date'),
            endDate: $request->query('end_date')
        );

        return $this->respondWithSuccess($data, 'Child attendance summary retrieved successfully.');
    }

    /**
     * List calendar events / overrides for the school.
     */
    public function getCalendar(Request $request, TenantManager $tenantManager): JsonResponse
    {
        $schoolId = $tenantManager->getTenantId();

        $query = SchoolCalendar::withoutGlobalScopes()
            ->where('school_id', $schoolId);

        if ($request->query('academic_year_id')) {
            $query->where('academic_year_id', $request->query('academic_year_id'));
        }

        if ($request->query('start_date') && $request->query('end_date')) {
            $query->whereBetween('date', [$request->query('start_date'), $request->query('end_date')]);
        }

        $events = $query->orderBy('date', 'asc')->get();

        return $this->respondWithSuccess($events, 'School calendar retrieved successfully.');
    }

    /**
     * Add or update an explicit calendar day override (Admin only).
     */
    public function storeCalendarDay(Request $request, TenantManager $tenantManager): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSchoolAdmin()) {
            return ApiResponse::error('Only school admins can configure calendar days.', 'FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $school = $tenantManager->getTenant();

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'is_school_day' => ['required', 'boolean'],
            'day_type' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'academic_year_id' => ['nullable', 'integer'],
        ]);

        $entry = SchoolCalendar::updateOrCreate(
            [
                'school_id' => $school->id,
                'date' => Carbon::parse($validated['date'])->format('Y-m-d'),
            ],
            [
                'is_school_day' => (bool) $validated['is_school_day'],
                'day_type' => $validated['day_type'],
                'description' => $validated['description'] ?? null,
                'academic_year_id' => $validated['academic_year_id'] ?? null,
            ]
        );

        return $this->respondWithSuccess($entry, 'Calendar day saved successfully.', Response::HTTP_CREATED);
    }
}
