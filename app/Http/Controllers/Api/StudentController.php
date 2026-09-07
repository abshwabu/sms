<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportStudentsCsvRequest;
use App\Http\Requests\PromoteRosterRequest;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Traits\HasApiResponse;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentImportService;
use App\Services\StudentPromotionService;
use App\Tenancy\Exceptions\ClosedAcademicYearException;
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

class StudentController extends Controller
{
    use HasApiResponse;

    /**
     * Display a listing of students with search and filter support.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Student::class);

        $query = Student::with([
            'user:id,name,email,phone,status',
            'currentSection.gradeLevel:id,name,order',
            'currentSection.academicYear:id,name,is_active',
        ])
        ->search($request->query('search'))
        ->inSection($request->query('section_id'))
        ->inGradeLevel($request->query('grade_level_id'))
        ->withStatus($request->query('status'))
        ->orderBy('admission_number');

        if ($request->boolean('all')) {
            return $this->respondWithSuccess($query->get(), 'Students retrieved successfully.');
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $paginator = $query->paginate($perPage);

        return $this->respondWithPagination($paginator, 'Students retrieved successfully.');
    }

    /**
     * Store a newly created student (creates User, Student profile, and initial Enrollment if section specified).
     */
    public function store(StoreStudentRequest $request, TenantManager $tenantManager): JsonResponse
    {
        Gate::authorize('create', Student::class);

        $school = $tenantManager->getTenant();
        $validated = $request->validated();

        $sectionId = $validated['section_id'] ?? null;
        $academicYearId = $validated['academic_year_id'] ?? null;

        if ($sectionId) {
            $section = Section::with('academicYear')->findOrFail($sectionId);
            $academicYearId = $academicYearId ?: $section->academic_year_id;
            if ($section->academicYear?->isClosed()) {
                throw new ClosedAcademicYearException('Cannot enroll student into a closed academic year.');
            }
        } elseif ($academicYearId) {
            $year = AcademicYear::findOrFail($academicYearId);
            if ($year->isClosed()) {
                throw new ClosedAcademicYearException('Cannot enroll student into a closed academic year.');
            }
        }

        DB::beginTransaction();
        try {
            app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
            $studentRole = Role::firstOrCreate([
                'name' => RoleEnum::STUDENT->value,
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
                'role' => RoleEnum::STUDENT->value,
                'status' => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ]);
            $user->assignRole($studentRole);

            $student = Student::create([
                'school_id' => $school->id,
                'user_id' => $user->id,
                'admission_number' => $validated['admission_number'],
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'gender' => $validated['gender'] ?? 'other',
                'address' => $validated['address'] ?? null,
                'admission_date' => $validated['admission_date'] ?? now()->toDateString(),
                'current_section_id' => $sectionId,
                'status' => 'active',
                'medical_notes' => $validated['medical_notes'] ?? null,
                'photo' => $validated['photo'] ?? null,
                'guardian_info' => $validated['guardian_info'] ?? null,
            ]);

            if ($sectionId && $academicYearId) {
                Enrollment::create([
                    'school_id' => $school->id,
                    'student_id' => $student->id,
                    'academic_year_id' => $academicYearId,
                    'section_id' => $sectionId,
                    'enrolled_at' => now()->toDateString(),
                    'status' => 'enrolled',
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $student->load([
            'user:id,name,email,phone,status',
            'currentSection.gradeLevel:id,name',
            'currentSection.academicYear:id,name',
        ]);

        return $this->respondWithSuccess($student, 'Student created successfully.', Response::HTTP_CREATED);
    }

    /**
     * Display the specified student with complete year-by-year enrollment history.
     */
    public function show(Student $student): JsonResponse
    {
        Gate::authorize('view', $student);

        $student->load([
            'user:id,name,email,phone,status,created_at',
            'currentSection.gradeLevel:id,name,order',
            'currentSection.academicYear:id,name,start_date,end_date,is_active',
            'enrollments.academicYear:id,name,start_date,end_date,is_closed',
            'enrollments.section.gradeLevel:id,name',
        ]);

        return $this->respondWithSuccess($student, 'Student details retrieved successfully.');
    }

    /**
     * Update the specified student profile.
     */
    public function update(UpdateStudentRequest $request, Student $student): JsonResponse
    {
        Gate::authorize('update', $student);

        $validated = $request->validated();

        DB::beginTransaction();
        try {
            // User attributes
            $userUpdates = array_intersect_key($validated, array_flip(['name', 'email', 'phone']));
            if (! empty($userUpdates)) {
                $student->user->update($userUpdates);
            }

            // Student attributes
            $studentUpdates = array_intersect_key($validated, array_flip([
                'date_of_birth',
                'gender',
                'address',
                'status',
                'medical_notes',
                'photo',
                'guardian_info',
            ]));
            if (! empty($studentUpdates)) {
                $student->update($studentUpdates);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $student->load([
            'user:id,name,email,phone,status',
            'currentSection.gradeLevel:id,name',
            'currentSection.academicYear:id,name',
        ]);

        return $this->respondWithSuccess($student, 'Student updated successfully.');
    }

    /**
     * Remove the specified student.
     */
    public function destroy(Student $student): JsonResponse
    {
        Gate::authorize('delete', $student);

        DB::beginTransaction();
        try {
            $user = $student->user;
            $student->delete();
            if ($user) {
                $user->delete();
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->respondWithSuccess(null, 'Student deleted successfully.');
    }

    /**
     * Bulk import students via CSV file.
     */
    public function import(ImportStudentsCsvRequest $request, StudentImportService $service): JsonResponse
    {
        Gate::authorize('import', Student::class);

        $result = $service->importFromCsv(
            file: $request->file('file'),
            academicYearId: (int) $request->input('academic_year_id'),
            sectionId: (int) $request->input('section_id'),
            defaultPassword: $request->input('default_password')
        );

        return $this->respondWithSuccess($result, 'Students imported successfully.');
    }

    /**
     * Promote, retain, or graduate section rosters.
     */
    public function promoteRoster(PromoteRosterRequest $request, StudentPromotionService $service): JsonResponse
    {
        Gate::authorize('promote', Student::class);

        $result = $service->promoteRoster(
            sourceSectionId: (int) $request->input('source_section_id'),
            targetAcademicYearId: (int) $request->input('target_academic_year_id'),
            targetSectionId: $request->input('target_section_id') ? (int) $request->input('target_section_id') : null,
            action: (string) $request->input('action'),
            studentIds: (array) ($request->input('student_ids') ?? [])
        );

        return $this->respondWithSuccess($result, 'Roster promotion processed successfully.');
    }
}
