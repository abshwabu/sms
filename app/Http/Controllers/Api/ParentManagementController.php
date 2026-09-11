<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\InviteParentRequest;
use App\Http\Requests\LinkStudentRequest;
use App\Http\Requests\StoreParentRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\HasApiResponse;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class ParentManagementController extends Controller
{
    use HasApiResponse;

    /**
     * Display a listing of parents with linked students.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ParentProfile::class);

        $query = ParentProfile::with([
            'user:id,name,email,phone,status',
            'students.user:id,name,email',
            'students.currentSection.gradeLevel:id,name',
        ])
        ->search($request->query('search'))
        ->orderBy('created_at', 'desc');

        if ($request->boolean('all')) {
            return $this->respondWithSuccess($query->get(), 'Parents retrieved successfully.');
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $paginator = $query->paginate($perPage);

        return $this->respondWithPagination($paginator, 'Parents retrieved successfully.');
    }

    /**
     * Store a newly created parent and optionally link to a student.
     */
    public function store(StoreParentRequest $request, TenantManager $tenantManager): JsonResponse
    {
        Gate::authorize('create', ParentProfile::class);

        $school = $tenantManager->getTenant();
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
            $parentRole = Role::firstOrCreate([
                'name' => RoleEnum::PARENT->value,
                'guard_name' => 'web',
                'school_id' => $school->id,
            ]);

            $plainPassword = $validated['password'] ?? Str::random(10);

            $email = ! empty($validated['email'])
                ? $validated['email']
                : $this->generateParentEmail($school, $validated['name'], $validated['student_id'] ?? null);

            $user = User::create([
                'school_id' => $school->id,
                'name' => $validated['name'],
                'email' => $email,
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($plainPassword),
                'role' => RoleEnum::PARENT->value,
                'status' => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ]);
            $user->assignRole($parentRole);

            $parent = ParentProfile::create([
                'school_id' => $school->id,
                'user_id' => $user->id,
                'occupation' => $validated['occupation'] ?? null,
                'address' => $validated['address'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'emergency_contact' => $validated['emergency_contact'] ?? null,
            ]);

            if (! empty($validated['student_id'])) {
                $parent->linkStudent(
                    $validated['student_id'],
                    $validated['relationship'] ?? 'guardian',
                    (bool) ($validated['is_primary_contact'] ?? false)
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $parent->load(['user:id,name,email,phone,status', 'students.user:id,name,email']);

        return $this->respondWithSuccess($parent, 'Parent account created successfully.', Response::HTTP_CREATED);
    }

    /**
     * Display the specified parent profile with linked students.
     */
    public function show(ParentProfile $parent): JsonResponse
    {
        Gate::authorize('view', $parent);

        $parent->load([
            'user:id,name,email,phone,status,created_at',
            'students.user:id,name,email,phone,status',
            'students.currentSection.gradeLevel:id,name',
            'students.currentSection.academicYear:id,name',
        ]);

        return $this->respondWithSuccess($parent, 'Parent profile retrieved successfully.');
    }

    /**
     * Link an existing parent to an enrolled student.
     * Admin flow to link an existing parent to a newly enrolled student.
     */
    public function linkStudent(LinkStudentRequest $request, ParentProfile $parent): JsonResponse
    {
        Gate::authorize('linkStudent', $parent);

        $student = Student::findOrFail($request->integer('student_id'));

        $parent->linkStudent(
            $student,
            $request->input('relationship') ?: 'guardian',
            $request->boolean('is_primary_contact')
        );

        $parent->load(['students.user:id,name,email']);

        return $this->respondWithSuccess($parent, 'Student linked to parent successfully.');
    }

    /**
     * Unlink a student from a parent.
     */
    public function unlinkStudent(ParentProfile $parent, Student $student): JsonResponse
    {
        Gate::authorize('linkStudent', $parent);

        $parent->unlinkStudent($student);

        return $this->respondWithSuccess(null, 'Student unlinked from parent successfully.');
    }

    /**
     * Invite a new parent during or after enrollment.
     */
    public function invite(InviteParentRequest $request, TenantManager $tenantManager): JsonResponse
    {
        Gate::authorize('create', ParentProfile::class);

        $school = $tenantManager->getTenant();
        $validated = $request->validated();

        $existingUser = User::where('email', $validated['email'])
            ->where('school_id', $school->id)
            ->first();

        if ($existingUser) {
            // If already a parent user, link student directly
            if ($existingUser->isParent() && $existingUser->parentProfile && ! empty($validated['student_id'])) {
                $existingUser->parentProfile->linkStudent(
                    $validated['student_id'],
                    $validated['relationship'] ?? 'guardian',
                    (bool) ($validated['is_primary_contact'] ?? false)
                );

                return $this->respondWithSuccess($existingUser->parentProfile, 'Existing parent linked to student.');
            }

            return ApiResponse::error(
                'A user with this email already exists in this school.',
                'USER_ALREADY_EXISTS',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Create user and parent profile with invited status
        DB::beginTransaction();
        try {
            app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
            $parentRole = Role::firstOrCreate([
                'name' => RoleEnum::PARENT->value,
                'guard_name' => 'web',
                'school_id' => $school->id,
            ]);

            $temporaryPassword = 'Par!' . Str::random(8);

            $user = User::create([
                'school_id' => $school->id,
                'name' => $validated['name'] ?? 'Parent of Student',
                'email' => $validated['email'],
                'password' => Hash::make($temporaryPassword),
                'role' => RoleEnum::PARENT->value,
                'status' => UserStatus::INVITED,
            ]);
            $user->assignRole($parentRole);

            $parent = ParentProfile::create([
                'school_id' => $school->id,
                'user_id' => $user->id,
            ]);

            if (! empty($validated['student_id'])) {
                $parent->linkStudent(
                    $validated['student_id'],
                    $validated['relationship'] ?? 'guardian',
                    (bool) ($validated['is_primary_contact'] ?? false)
                );
            }

            $invitation = Invitation::create([
                'school_id' => $school->id,
                'email' => $validated['email'],
                'role' => RoleEnum::PARENT->value,
                'token' => Invitation::generateToken(),
                'invited_by' => $request->user()->id,
                'expires_at' => now()->addDays(7),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        try {
            Mail::to($invitation->email)->send(new InvitationMail(
                invitation: $invitation,
                school: $school,
                roleLabel: 'Parent / Guardian',
                temporaryPassword: $temporaryPassword,
                inviterName: $request->user()?->name,
                recipientName: $validated['name'] ?? null
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to send parent invitation email: ' . $e->getMessage());
        }

        return $this->respondWithSuccess([
            'parent' => $parent->load('students.user'),
            'invitation_token' => $invitation->token,
            'temporary_password' => $temporaryPassword,
        ], 'Parent invited and linked to student successfully.', Response::HTTP_CREATED);
    }

    /**
     * Generate unique parent email address for school domain if omitted.
     */
    protected function generateParentEmail(School $school, string $name, ?int $studentId = null): string
    {
        $cleanName = Str::slug($name, '.');
        $suffix = $studentId ? "p{$studentId}" : 'parent.' . Str::lower(Str::random(4));
        $subdomain = $school->subdomain ?: 'school';
        $candidate = "{$cleanName}.{$suffix}@{$subdomain}.edu";
        $i = 1;
        while (User::where('email', $candidate)->exists()) {
            $candidate = "{$cleanName}.{$suffix}.{$i}@{$subdomain}.edu";
            $i++;
        }
        return $candidate;
    }
}
