<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\UserStatus;
use App\Models\Course;
use App\Models\School;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndRBACTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected School $oakridge;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            SchoolSeeder::class,
            RolesAndPermissionsSeeder::class,
        ]);

        $this->greenwood = School::where('subdomain', 'greenwood')->firstOrFail();
        $this->oakridge = School::where('subdomain', 'oakridge')->firstOrFail();
    }

    /**
     * Acceptance Criterion 1:
     * Login returns a token and the user's role + school context.
     */
    public function test_login_returns_token_and_user_role_and_school_context(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@greenwood.edu',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'status',
                        'school' => [
                            'id',
                            'name',
                            'subdomain',
                            'timezone',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.user.role', 'school_admin')
            ->assertJsonPath('data.user.school.id', $this->greenwood->id)
            ->assertJsonPath('data.user.school.subdomain', 'greenwood');

        $this->assertNotEmpty($response->json('data.token'));
    }

    /**
     * Acceptance Criterion 2:
     * A teacher token cannot hit school-admin-only endpoints (403).
     */
    public function test_teacher_token_cannot_hit_school_admin_only_endpoints(): void
    {
        $teacher = User::where('email', 'teacher@greenwood.edu')->firstOrFail();
        $token = $teacher->createToken('test-token')->plainTextToken;

        // Attempt to hit admin users listing as a teacher
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->getJson('/api/admin/users');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // Attempt to send invitation as a teacher
        $inviteResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->postJson('/api/admin/invitations', [
                'email' => 'newstaff@greenwood.edu',
                'role' => 'teacher',
            ]);

        $inviteResponse->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * School Admin CAN hit school-admin endpoints.
     */
    public function test_school_admin_can_hit_school_admin_endpoints(): void
    {
        $admin = User::where('email', 'admin@greenwood.edu')->firstOrFail();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->getJson('/api/admin/users');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'school_id', 'name', 'email', 'role', 'status'],
                ],
            ]);
    }

    /**
     * Acceptance Criterion 3:
     * A user from School A cannot access School B's records even with a valid token.
     */
    public function test_user_from_school_a_cannot_access_school_b_records(): void
    {
        $greenwoodAdmin = User::where('email', 'admin@greenwood.edu')->firstOrFail();
        $token = $greenwoodAdmin->createToken('test-token')->plainTextToken;

        // Try to access School B (Oakridge) endpoints using Greenwood token
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->oakridge->id)
            ->getJson('/api/courses');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'FORBIDDEN_TENANT_ACCESS');

        // Try to access School B admin endpoints using Greenwood token
        $adminResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->oakridge->id)
            ->getJson('/api/admin/users');

        $adminResponse->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'FORBIDDEN_TENANT_ACCESS');
    }

    /**
     * Test platform Super Admin can access cross-tenant records.
     */
    public function test_super_admin_has_cross_tenant_access(): void
    {
        $superAdmin = User::where('email', 'superadmin@bina.test')->firstOrFail();
        $token = $superAdmin->createToken('test-token')->plainTextToken;

        // Super Admin accessing Greenwood
        $greenwoodRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->getJson('/api/admin/users');

        $greenwoodRes->assertOk()->assertJsonPath('success', true);

        // Super Admin accessing Oakridge
        $oakridgeRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->oakridge->id)
            ->getJson('/api/admin/users');

        $oakridgeRes->assertOk()->assertJsonPath('success', true);
    }
}
