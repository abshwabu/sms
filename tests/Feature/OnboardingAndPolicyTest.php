<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\UserStatus;
use App\Models\ClaimCode;
use App\Models\Course;
use App\Models\Invitation;
use App\Models\School;
use App\Models\User;
use App\Policies\AttendancePolicy;
use App\Policies\CoursePolicy;
use App\Policies\StudentPolicy;
use App\Tenancy\TenantManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingAndPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected School $oakridge;
    protected TenantManager $tenantManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            SchoolSeeder::class,
            RolesAndPermissionsSeeder::class,
        ]);

        $this->tenantManager = app(TenantManager::class);
        $this->greenwood = School::where('subdomain', 'greenwood')->firstOrFail();
        $this->oakridge = School::where('subdomain', 'oakridge')->firstOrFail();
    }

    /**
     * Test Invitation flow: Admin invites teacher -> Teacher accepts -> Account active.
     */
    public function test_invite_based_onboarding_flow(): void
    {
        $admin = User::where('email', 'admin@greenwood.edu')->firstOrFail();
        $token = $admin->createToken('admin-token')->plainTextToken;

        // 1. Admin sends invitation
        $inviteRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->postJson('/api/admin/invitations', [
                'email' => 'newteacher@greenwood.edu',
                'role' => 'teacher',
            ]);

        $inviteRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'newteacher@greenwood.edu');

        $inviteToken = $inviteRes->json('data.token');
        $this->assertNotEmpty($inviteToken);

        // 2. Teacher accepts invitation
        $acceptRes = $this->postJson('/api/invitations/accept', [
            'token' => $inviteToken,
            'name' => 'Alice Teacher',
            'password' => 'newpassword123',
            'phone' => '+15551239999',
        ]);

        $acceptRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'newteacher@greenwood.edu')
            ->assertJsonPath('data.user.role', 'teacher')
            ->assertJsonPath('data.user.status', 'active')
            ->assertJsonPath('data.user.school.id', $this->greenwood->id);

        $teacherToken = $acceptRes->json('data.token');
        $this->assertNotEmpty($teacherToken);

        // 3. Newly created teacher can access tenant courses
        $coursesRes = $this->withHeader('Authorization', 'Bearer ' . $teacherToken)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->getJson('/api/courses');

        $coursesRes->assertOk();
    }

    /**
     * Test Claim Code flow: Admin generates code -> Student claims code -> Account active.
     */
    public function test_claim_code_onboarding_flow(): void
    {
        $admin = User::where('email', 'admin@greenwood.edu')->firstOrFail();
        $token = $admin->createToken('admin-token')->plainTextToken;

        // 1. Admin generates claim code
        $codeRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->postJson('/api/admin/claim-codes', [
                'role' => 'student',
            ]);

        $codeRes->assertStatus(201)
            ->assertJsonPath('success', true);

        $claimCode = $codeRes->json('data.code');
        $this->assertNotEmpty($claimCode);

        // 2. Student registers and claims code
        $claimRes = $this->postJson('/api/claim-codes/claim', [
            'code' => $claimCode,
            'name' => 'Lisa Simpson',
            'email' => 'lisa@greenwood.edu',
            'password' => 'lisapassword123',
        ]);

        $claimRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'lisa@greenwood.edu')
            ->assertJsonPath('data.user.role', 'student')
            ->assertJsonPath('data.user.school.id', $this->greenwood->id);

        // 3. Trying to claim the same code again should fail (409 Conflict)
        $secondClaimRes = $this->postJson('/api/claim-codes/claim', [
            'code' => $claimCode,
            'name' => 'Duplicate Claimer',
            'email' => 'duplicate@greenwood.edu',
            'password' => 'somepassword123',
        ]);

        $secondClaimRes->assertStatus(409)
            ->assertJsonPath('error.code', 'CLAIM_CODE_CLAIMED');
    }

    /**
     * Test Policies: StudentPolicy, AttendancePolicy, CoursePolicy.
     */
    public function test_model_policies_pattern(): void
    {
        $admin = User::where('email', 'admin@greenwood.edu')->firstOrFail();
        $teacher = User::where('email', 'teacher@greenwood.edu')->firstOrFail();
        $student = User::where('email', 'student@greenwood.edu')->firstOrFail();
        $oakridgeStudent = User::where('email', 'student@oakridge.edu')->firstOrFail();
        $superAdmin = User::where('email', 'superadmin@bina.test')->firstOrFail();

        $studentPolicy = new StudentPolicy();
        $attendancePolicy = new AttendancePolicy();
        $coursePolicy = new CoursePolicy();

        // Student Policy checks
        $this->assertTrue($studentPolicy->viewAny($admin));
        $this->assertTrue($studentPolicy->viewAny($teacher));
        $this->assertFalse($studentPolicy->viewAny($student));

        $this->assertTrue($studentPolicy->create($admin));
        $this->assertFalse($studentPolicy->create($teacher));
        $this->assertFalse($studentPolicy->create($student));

        // Cross-school checks
        $this->assertTrue($studentPolicy->view($teacher, $student));
        $this->assertFalse($studentPolicy->view($teacher, $oakridgeStudent)); // Cross-school blocked!

        // Super admin bypass check
        $this->assertTrue($studentPolicy->before($superAdmin, 'view'));

        // Attendance Policy checks
        $this->assertTrue($attendancePolicy->create($teacher));
        $this->assertFalse($attendancePolicy->create($student));

        // Course Policy checks with tenant context
        $course = $this->tenantManager->bypassTenantScoping(function () use ($admin) {
            return Course::where('school_id', $admin->school_id)->firstOrFail();
        });

        $this->assertTrue($coursePolicy->create($teacher));
        $this->assertFalse($coursePolicy->create($student));
        $this->assertTrue($coursePolicy->delete($admin, $course));
        $this->assertFalse($coursePolicy->delete($teacher, $course));
    }
}
