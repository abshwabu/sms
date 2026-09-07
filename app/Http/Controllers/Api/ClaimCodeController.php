<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClaimCodeRequest;
use App\Http\Requests\CreateClaimCodeRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\HasApiResponse;
use App\Models\ClaimCode;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class ClaimCodeController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected TenantManager $tenantManager
    ) {}

    /**
     * Admin/Staff generates a claim code for student/parent onboarding.
     */
    public function generate(CreateClaimCodeRequest $request): JsonResponse
    {
        $schoolId = $this->tenantManager->getTenantId() ?? $request->user()->school_id;

        if (! $schoolId) {
            return ApiResponse::error(
                'Tenant context required to generate claim codes.',
                'TENANT_REQUIRED',
                Response::HTTP_BAD_REQUEST
            );
        }

        $validated = $request->validated();

        $claimCode = ClaimCode::create([
            'school_id' => $schoolId,
            'code' => ClaimCode::generateCode(),
            'role' => $validated['role'],
            'student_id' => $validated['student_id'] ?? null,
            'expires_at' => now()->addDays(30),
        ]);

        return $this->respondWithSuccess([
            'id' => $claimCode->id,
            'code' => $claimCode->code,
            'role' => $claimCode->role,
            'student_id' => $claimCode->student_id,
            'expires_at' => $claimCode->expires_at->toIso8601String(),
        ], 'Claim code generated successfully.', Response::HTTP_CREATED);
    }

    /**
     * Student or parent registers and claims their account using a claim code.
     */
    public function claim(ClaimCodeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $claimCode = $this->tenantManager->bypassTenantScoping(function () use ($validated) {
            return ClaimCode::with('school')->where('code', $validated['code'])->first();
        });

        if (! $claimCode) {
            return ApiResponse::error('Invalid claim code.', 'INVALID_CLAIM_CODE', Response::HTTP_NOT_FOUND);
        }

        if ($claimCode->isExpired()) {
            return ApiResponse::error('This claim code has expired.', 'CLAIM_CODE_EXPIRED', Response::HTTP_GONE);
        }

        if ($claimCode->isClaimed()) {
            return ApiResponse::error('This claim code has already been claimed.', 'CLAIM_CODE_CLAIMED', Response::HTTP_CONFLICT);
        }

        $school = $claimCode->school;
        $this->tenantManager->setTenant($school);

        $user = User::create([
            'school_id' => $school->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $claimCode->role,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        // Assign Spatie role within school team context
        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
        $role = Role::where('name', $claimCode->role)->where('school_id', $school->id)->first();
        if ($role) {
            $user->assignRole($role);
        }

        $claimCode->markAsClaimed($user);

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
        ], 'Account created and code claimed successfully.', Response::HTTP_CREATED);
    }
}
