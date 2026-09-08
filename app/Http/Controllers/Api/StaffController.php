<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Http\Traits\HasApiResponse;
use App\Models\Course;
use App\Models\School;
use App\Models\Staff;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class StaffController extends Controller
{
    use HasApiResponse;

    /**
     * Display a listing of the staff directory with filters.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Staff::class);

        $query = Staff::with([
            'user:id,name,email,phone,status',
            'courses:id,name,code',
            'homeroomSections:id,name,academic_year_id',
            'homeroomSections.academicYear:id,name',
            'sectionSubjectAssignments.section:id,name',
            'sectionSubjectAssignments.course:id,name,code',
        ])
        ->search($request->query('search'))
        ->withStatus($request->query('status'))
        ->inDepartment($request->query('department'))
        ->withRole($request->query('role_title'))
        ->orderBy('staff_number');

        if ($request->filled('course_id')) {
            $query->whereHas('courses', function ($cq) use ($request) {
                $cq->where('courses.id', $request->query('course_id'));
            });
        }

        if ($request->boolean('all')) {
            return $this->respondWithSuccess($query->get(), 'Staff directory retrieved successfully.');
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $paginator = $query->paginate($perPage);

        return $this->respondWithPagination($paginator, 'Staff directory retrieved successfully.');
    }

    /**
     * Store a newly created staff member and create associated user account.
     */
    public function store(StoreStaffRequest $request, TenantManager $tenantManager): JsonResponse
    {
        Gate::authorize('create', Staff::class);

        $school = $tenantManager->getTenant();
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
            $teacherRole = Role::firstOrCreate([
                'name' => RoleEnum::TEACHER->value,
                'guard_name' => 'web',
                'school_id' => $school->id,
            ]);

            $plainPassword = $validated['password'] ?? Str::random(10);

            $user = User::create([
                'school_id' => $school->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($plainPassword),
                'role' => RoleEnum::TEACHER->value,
                'status' => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ]);
            $user->assignRole($teacherRole);

            $staffNumber = ! empty($validated['staff_number'])
                ? $validated['staff_number']
                : $this->generateStaffNumber($school);

            $staff = Staff::create([
                'school_id' => $school->id,
                'user_id' => $user->id,
                'staff_number' => $staffNumber,
                'role_title' => $validated['role_title'],
                'department' => $validated['department'] ?? null,
                'hire_date' => $validated['hire_date'] ?? now()->toDateString(),
                'status' => $validated['status'] ?? 'active',
                'phone' => $validated['phone'] ?? null,
                'qualification' => $validated['qualification'] ?? null,
                'subjects_taught' => $validated['subjects_taught'] ?? null,
            ]);

            if (! empty($validated['course_ids'])) {
                $syncData = [];
                foreach ($validated['course_ids'] as $cid) {
                    $syncData[$cid] = ['school_id' => $school->id];
                }
                $staff->courses()->sync($syncData);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $staff->load([
            'user:id,name,email,phone,status',
            'courses:id,name,code',
        ]);

        return $this->respondWithSuccess($staff, 'Staff member created successfully.', Response::HTTP_CREATED);
    }

    /**
     * Display the specified staff profile with section assignments.
     */
    public function show(Staff $staff): JsonResponse
    {
        Gate::authorize('view', $staff);

        $staff->load([
            'user:id,name,email,phone,status,created_at',
            'courses:id,name,code',
            'homeroomSections.academicYear:id,name',
            'homeroomSections.gradeLevel:id,name',
            'sectionSubjectAssignments.section.gradeLevel:id,name',
            'sectionSubjectAssignments.section.academicYear:id,name',
            'sectionSubjectAssignments.course:id,name,code',
        ]);

        return $this->respondWithSuccess($staff, 'Staff profile retrieved successfully.');
    }

    /**
     * Update the specified staff record.
     */
    public function update(UpdateStaffRequest $request, Staff $staff): JsonResponse
    {
        Gate::authorize('update', $staff);

        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $userUpdates = array_intersect_key($validated, array_flip(['name', 'email', 'phone']));
            if (! empty($userUpdates)) {
                $staff->user->update($userUpdates);
            }

            $staffUpdates = array_intersect_key($validated, array_flip([
                'role_title',
                'department',
                'hire_date',
                'status',
                'phone',
                'qualification',
                'subjects_taught',
            ]));
            if (! empty($staffUpdates)) {
                $staff->update($staffUpdates);
            }

            if (isset($validated['course_ids'])) {
                $schoolId = $staff->school_id;
                $syncData = [];
                foreach ($validated['course_ids'] as $cid) {
                    $syncData[$cid] = ['school_id' => $schoolId];
                }
                $staff->courses()->sync($syncData);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $staff->load([
            'user:id,name,email,phone,status',
            'courses:id,name,code',
            'homeroomSections',
            'sectionSubjectAssignments.section',
            'sectionSubjectAssignments.course',
        ]);

        return $this->respondWithSuccess($staff, 'Staff record updated successfully.');
    }

    /**
     * Remove the specified staff member.
     */
    public function destroy(Staff $staff): JsonResponse
    {
        Gate::authorize('delete', $staff);

        DB::beginTransaction();
        try {
            $user = $staff->user;
            $staff->delete();
            if ($user) {
                $user->delete();
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->respondWithSuccess(null, 'Staff member removed successfully.');
    }

    /**
     * Generate unique sequential staff number (e.g. STF-26-00001).
     */
    protected function generateStaffNumber(School $school): string
    {
        $prefix = 'STF';
        $year = date('y');
        $count = Staff::withoutGlobalScopes()->where('school_id', $school->id)->count() + 1;
        $candidate = sprintf('%s-%s-%05d', $prefix, $year, $count);

        while (Staff::withoutGlobalScopes()->where('school_id', $school->id)->where('staff_number', $candidate)->exists()) {
            $count++;
            $candidate = sprintf('%s-%s-%05d', $prefix, $year, $count);
        }

        return $candidate;
    }
}
