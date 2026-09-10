<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\InviteStaffRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\HasApiResponse;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\School;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class InvitationController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected TenantManager $tenantManager
    ) {}

    /**
     * Admin invites a teacher or staff member by email.
     */
    public function invite(InviteStaffRequest $request): JsonResponse
    {
        $schoolId = $this->tenantManager->getTenantId() ?? $request->user()->school_id;

        if (! $schoolId) {
            return ApiResponse::error(
                'Tenant context required to send invitations.',
                'TENANT_REQUIRED',
                Response::HTTP_BAD_REQUEST
            );
        }

        $validated = $request->validated();

        // Check if user already exists
        if (User::where('email', $validated['email'])->where('school_id', $schoolId)->exists()) {
            return ApiResponse::error(
                'A user with this email already exists in this school.',
                'USER_ALREADY_EXISTS',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $invitation = Invitation::create([
            'school_id' => $schoolId,
            'email' => $validated['email'],
            'role' => $validated['role'],
            'token' => Invitation::generateToken(),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);

        $school = School::find($schoolId);
        try {
            $roleLabel = match ($invitation->role) {
                'school_admin' => 'School Administrator',
                'teacher' => 'Teacher / Faculty Staff',
                default => ucfirst(str_replace('_', ' ', $invitation->role)),
            };
            Mail::to($invitation->email)->send(new InvitationMail(
                invitation: $invitation,
                school: $school,
                roleLabel: $roleLabel,
                inviterName: $request->user()?->name
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to send staff invitation email: ' . $e->getMessage());
        }

        return $this->respondWithSuccess([
            'id' => $invitation->id,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'token' => $invitation->token,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ], 'Invitation created successfully.', Response::HTTP_CREATED);
    }

    /**
     * Accept invitation and create active user account.
     */
    public function accept(AcceptInvitationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $invitation = $this->tenantManager->bypassTenantScoping(function () use ($validated) {
            return Invitation::with('school')->where('token', $validated['token'])->first();
        });

        if (! $invitation) {
            return ApiResponse::error('Invalid invitation token.', 'INVALID_INVITATION', Response::HTTP_NOT_FOUND);
        }

        if ($invitation->isExpired()) {
            return ApiResponse::error('This invitation has expired.', 'INVITATION_EXPIRED', Response::HTTP_GONE);
        }

        if ($invitation->isAccepted()) {
            return ApiResponse::error('This invitation has already been accepted.', 'INVITATION_ACCEPTED', Response::HTTP_CONFLICT);
        }

        // Set tenant context for user creation and role assignment
        $school = $invitation->school;
        $this->tenantManager->setTenant($school);

        $user = User::create([
            'school_id' => $school->id,
            'name' => $validated['name'],
            'email' => $invitation->email,
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $invitation->role,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        // Assign Spatie role within the school's team context
        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
        $role = Role::where('name', $invitation->role)->where('school_id', $school->id)->first();
        if ($role) {
            $user->assignRole($role);
        }

        $invitation->markAsAccepted();

        // Issue Sanctum token
        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->respondWithSuccess([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status->value,
                'school' => [
                    'id' => $school->id,
                    'name' => $school->name,
                    'subdomain' => $school->subdomain,
                ],
            ],
        ], 'Invitation accepted and account activated successfully.', Response::HTTP_CREATED);
    }
}
