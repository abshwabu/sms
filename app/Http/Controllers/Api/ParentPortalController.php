<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\HasApiResponse;
use App\Models\ParentProfile;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ParentPortalController extends Controller
{
    use HasApiResponse;

    /**
     * Get all linked children for the currently authenticated parent.
     * Acceptance criterion: A parent linked to multiple children sees a unified
     * switcher across all linked children, even across different sections/grades.
     */
    public function children(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isParent()) {
            return ApiResponse::error(
                'Only parent accounts can access the parent portal.',
                'FORBIDDEN_PORTAL_ACCESS',
                Response::HTTP_FORBIDDEN
            );
        }

        $parent = $user->parentProfile;

        if (! $parent) {
            return $this->respondWithSuccess([], 'No parent profile found.');
        }

        $children = $parent->students()
            ->with([
                'user:id,name,email,phone,status',
                'currentSection.gradeLevel:id,name,order',
                'currentSection.academicYear:id,name,is_active',
                'currentSection.homeroomTeacher:id,name,email',
            ])
            ->get();

        return $this->respondWithSuccess($children, 'Linked children retrieved successfully.');
    }

    /**
     * Get comprehensive dashboard data for a selected child.
     * Acceptance criteria:
     * 1. A parent linked to 2 children can switch between their dashboards without re-login.
     * 2. A parent cannot see any student they aren't linked to (403 Forbidden).
     */
    public function childDashboard(Request $request, Student $student): JsonResponse
    {
        Gate::authorize('view', $student);

        $student->load([
            'user:id,name,email,phone,status,created_at',
            'currentSection.gradeLevel:id,name,order',
            'currentSection.academicYear:id,name,start_date,end_date,is_active',
            'currentSection.homeroomTeacher:id,name,email',
            'currentSection.subjectTeachers.course:id,name,code',
            'currentSection.subjectTeachers.staff.user:id,name,email',
            'enrollments.academicYear:id,name,start_date,end_date,is_closed',
            'enrollments.section.gradeLevel:id,name',
            'parents.user:id,name,email,phone',
        ]);

        $parentPivot = $student->parents()
            ->where('user_id', $request->user()->id)
            ->first()
            ?->pivot;

        return $this->respondWithSuccess([
            'student' => $student,
            'relationship' => $parentPivot?->relationship ?? 'guardian',
            'is_primary_contact' => (bool) ($parentPivot?->is_primary_contact ?? false),
            'academic_summary' => [
                'current_section' => $student->currentSection?->name,
                'grade_level' => $student->currentSection?->gradeLevel?->name,
                'academic_year' => $student->currentSection?->academicYear?->name,
                'homeroom_teacher' => $student->currentSection?->homeroomTeacher?->name,
                'total_enrolled_years' => $student->enrollments->count(),
                'subjects_count' => $student->currentSection?->subjectTeachers?->count() ?? 0,
            ],
        ], 'Child dashboard data retrieved successfully.');
    }
}
