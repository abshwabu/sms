<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BatchTimetableSlotRequest;
use App\Http\Requests\StoreTimetableSlotRequest;
use App\Http\Requests\UpdateTimetableSlotRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\HasApiResponse;
use App\Models\Section;
use App\Models\Student;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Services\TimetableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class TimetableController extends Controller
{
    use HasApiResponse;

    /**
     * Get weekly timetable for a given section.
     */
    public function getSectionTimetable(
        Request $request,
        Section $section,
        TimetableService $timetableService
    ): JsonResponse {
        Gate::authorize('view', $section);

        $dayOfWeek = $request->query('day_of_week');
        $timetable = $timetableService->getSectionTimetable($section, $dayOfWeek);

        return $this->respondWithSuccess($timetable, 'Section timetable retrieved successfully.');
    }

    /**
     * Create a single timetable slot for a section.
     * Acceptance criterion: Conflicting teacher assignments are blocked with a clear error.
     */
    public function storeSlot(
        StoreTimetableSlotRequest $request,
        Section $section,
        TimetableService $timetableService
    ): JsonResponse {
        $user = $request->user();

        if (! $user->isSchoolAdmin() && ! $section->isHomeroomTeacher($user)) {
            return ApiResponse::error(
                'Only administrators or the section homeroom teacher can create timetable slots.',
                'FORBIDDEN_TIMETABLE_CREATION',
                Response::HTTP_FORBIDDEN
            );
        }

        $slot = $timetableService->createSlot($section, $request->validated());

        return $this->respondWithSuccess($slot, 'Timetable slot created successfully.', Response::HTTP_CREATED);
    }

    /**
     * Update an existing timetable slot.
     */
    public function updateSlot(
        UpdateTimetableSlotRequest $request,
        TimetableSlot $timetableSlot,
        TimetableService $timetableService
    ): JsonResponse {
        Gate::authorize('update', $timetableSlot);

        $slot = $timetableService->updateSlot($timetableSlot, $request->validated());

        return $this->respondWithSuccess($slot, 'Timetable slot updated successfully.');
    }

    /**
     * Delete a timetable slot.
     */
    public function destroySlot(
        Request $request,
        TimetableSlot $timetableSlot,
        TimetableService $timetableService
    ): JsonResponse {
        Gate::authorize('delete', $timetableSlot);

        $timetableService->deleteSlot($timetableSlot);

        return $this->respondWithSuccess(null, 'Timetable slot deleted successfully.');
    }

    /**
     * Bulk build or replace a section's weekly timetable.
     * Admin builds full week's timetable for a section with form/drag-and-drop.
     */
    public function batchStoreSlots(
        BatchTimetableSlotRequest $request,
        Section $section,
        TimetableService $timetableService
    ): JsonResponse {
        $user = $request->user();

        if (! $user->isSchoolAdmin() && ! $section->isHomeroomTeacher($user)) {
            return ApiResponse::error(
                'Unauthorized to manage timetable for this section.',
                'FORBIDDEN',
                Response::HTTP_FORBIDDEN
            );
        }

        $slots = $timetableService->batchCreateSlots(
            section: $section,
            slotsData: $request->input('slots', []),
            replaceExisting: (bool) $request->input('replace_existing', false)
        );

        return $this->respondWithSuccess([
            'count' => $slots->count(),
            'slots' => $slots,
        ], 'Weekly timetable slots created successfully.', Response::HTTP_CREATED);
    }

    /**
     * Acceptance criterion: A teacher's personal timetable correctly aggregates
     * slots across every section they teach.
     */
    public function getTeacherTimetable(
        Request $request,
        User $teacher,
        TimetableService $timetableService
    ): JsonResponse {
        $user = $request->user();

        if (! $user->isSchoolAdmin() && (int) $user->id !== (int) $teacher->id) {
            return ApiResponse::error(
                'You can only view your own teaching schedule.',
                'FORBIDDEN',
                Response::HTTP_FORBIDDEN
            );
        }

        $academicYearId = $request->query('academic_year_id');
        $timetable = $timetableService->getTeacherTimetable($teacher, $academicYearId ? (int) $academicYearId : null);

        return $this->respondWithSuccess($timetable, 'Teacher personal timetable retrieved successfully.');
    }

    /**
     * Authenticated teacher's personal aggregated timetable.
     */
    public function getMyTeacherTimetable(
        Request $request,
        TimetableService $timetableService
    ): JsonResponse {
        $user = $request->user();

        if (! $user->isTeacher() && ! $user->isSchoolAdmin()) {
            return ApiResponse::error(
                'Only teaching staff have a teaching timetable.',
                'FORBIDDEN',
                Response::HTTP_FORBIDDEN
            );
        }

        $timetable = $timetableService->getTeacherTimetable($user);

        return $this->respondWithSuccess($timetable, 'Your teaching timetable retrieved successfully.');
    }

    /**
     * Authenticated student views their own section's weekly timetable.
     */
    public function getMyStudentTimetable(
        Request $request,
        TimetableService $timetableService
    ): JsonResponse {
        $user = $request->user();

        if (! $user->isStudent() || ! $user->student) {
            return ApiResponse::error('Only students can access this endpoint.', 'FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $section = $user->student->currentSection;

        if (! $section) {
            return ApiResponse::error('You are not currently enrolled in any section.', 'NO_SECTION', Response::HTTP_NOT_FOUND);
        }

        $timetable = $timetableService->getSectionTimetable($section);

        return $this->respondWithSuccess($timetable, 'Student section timetable retrieved successfully.');
    }

    /**
     * Authenticated parent views their linked child's section timetable.
     */
    public function getParentChildTimetable(
        Request $request,
        Student $student,
        TimetableService $timetableService
    ): JsonResponse {
        $user = $request->user();

        if (! $user->isParent() || ! $user->parentProfile?->isLinkedTo($student)) {
            return ApiResponse::error(
                'You are not authorized to view this student\'s timetable.',
                'FORBIDDEN_PARENT_ACCESS',
                Response::HTTP_FORBIDDEN
            );
        }

        $section = $student->currentSection;

        if (! $section) {
            return ApiResponse::error('Student is not currently enrolled in any section.', 'NO_SECTION', Response::HTTP_NOT_FOUND);
        }

        $timetable = $timetableService->getSectionTimetable($section);

        return $this->respondWithSuccess($timetable, 'Child section timetable retrieved successfully.');
    }
}
