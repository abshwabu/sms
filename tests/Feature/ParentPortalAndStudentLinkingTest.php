<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\AcademicYear;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\ParentSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SchoolSeeder;
use Database\Seeders\StaffSeeder;
use Database\Seeders\StudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentPortalAndStudentLinkingTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected School $oakridge;
    protected User $greenwoodAdmin;
    protected User $greenwoodTeacher;
    protected User $homerUser;
    protected ParentProfile $homerProfile;
    protected Student $bartStudent;
    protected Student $lisaStudent;
    protected Student $milhouseStudent;
    protected User $oakridgeParentUser;
    protected Student $oakridgeStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SchoolSeeder::class,
            RolesAndPermissionsSeeder::class,
            AcademicStructureSeeder::class,
            StudentSeeder::class,
            StaffSeeder::class,
            ParentSeeder::class,
        ]);

        $this->greenwood = School::where('subdomain', 'greenwood')->firstOrFail();
        $this->oakridge = School::where('subdomain', 'oakridge')->firstOrFail();

        app(\App\Tenancy\TenantManager::class)->setTenant($this->greenwood);

        $this->greenwoodAdmin = User::where('email', 'admin@greenwood.edu')->firstOrFail();
        $this->greenwoodTeacher = User::where('email', 'teacher@greenwood.edu')->firstOrFail();
        $this->homerUser = User::where('email', 'parent@greenwood.edu')->firstOrFail();
        $this->homerProfile = ParentProfile::where('user_id', $this->homerUser->id)->firstOrFail();

        $this->bartStudent = Student::where('admission_number', 'GRE-25-00101')->firstOrFail();
        $this->lisaStudent = Student::where('admission_number', 'GRE-25-00102')->firstOrFail();
        $this->milhouseStudent = Student::where('admission_number', 'GRE-25-00103')->firstOrFail();

        $this->oakridgeParentUser = User::where('email', 'parent@oakridge.edu')->firstOrFail();
        $this->oakridgeStudent = Student::withoutGlobalScopes()
            ->where('school_id', $this->oakridge->id)
            ->firstOrFail();
    }

    /**
     * Helper to authenticate requests with tenant headers.
     */
    protected function actingAsTenant(User $user, School $school): static
    {
        $this->app['auth']->forgetGuards();
        app(\App\Tenancy\TenantManager::class)->setTenant($school);
        $token = $user->createToken('test-token')->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $school->id);
    }

    /**
     * Acceptance Criterion 1:
     * A parent linked to 2 children can switch between their dashboards without re-login.
     */
    public function test_parent_linked_to_two_children_can_switch_dashboards_without_relogin(): void
    {
        // 1. Parent retrieves their linked children list
        $response = $this->actingAsTenant($this->homerUser, $this->greenwood)
            ->getJson('/api/parent/children');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $childrenIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->bartStudent->id, $childrenIds);
        $this->assertContains($this->lisaStudent->id, $childrenIds);

        // 2. Parent views Child 1 (Bart Simpson) dashboard
        $bartDashboard = $this->actingAsTenant($this->homerUser, $this->greenwood)
            ->getJson("/api/parent/children/{$this->bartStudent->id}/dashboard");

        $bartDashboard->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student.id', $this->bartStudent->id)
            ->assertJsonPath('data.student.user.name', 'Bart Simpson')
            ->assertJsonPath('data.relationship', 'father')
            ->assertJsonStructure([
                'data' => [
                    'student' => ['id', 'user_id', 'admission_number', 'status'],
                    'relationship',
                    'is_primary_contact',
                    'academic_summary' => [
                        'current_section',
                        'grade_level',
                        'academic_year',
                        'homeroom_teacher',
                        'total_enrolled_years',
                        'subjects_count',
                    ],
                ],
            ]);

        // 3. Parent switches to Child 2 (Lisa Simpson) dashboard using the SAME token / context
        $lisaDashboard = $this->actingAsTenant($this->homerUser, $this->greenwood)
            ->getJson("/api/parent/children/{$this->lisaStudent->id}/dashboard");

        $lisaDashboard->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student.id', $this->lisaStudent->id)
            ->assertJsonPath('data.student.user.name', 'Lisa Simpson');
    }

    /**
     * Acceptance Criterion 2:
     * A parent cannot see any student they aren't linked to (HTTP 403 Forbidden).
     */
    public function test_parent_cannot_view_unlinked_student_dashboard(): void
    {
        // Homer attempts to view Milhouse's portal dashboard
        $response = $this->actingAsTenant($this->homerUser, $this->greenwood)
            ->getJson("/api/parent/children/{$this->milhouseStudent->id}/dashboard");

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * Acceptance Criterion 2 (continued):
     * A parent cannot access unlinked student details via the standard /api/students/{id} endpoint,
     * but can access their linked children.
     */
    public function test_parent_cannot_view_unlinked_student_via_students_resource(): void
    {
        // Homer attempts to view Milhouse via standard student API
        $response = $this->actingAsTenant($this->homerUser, $this->greenwood)
            ->getJson("/api/students/{$this->milhouseStudent->id}");

        $response->assertStatus(403)
            ->assertJsonPath('success', false);

        // Homer CAN view his linked child Bart via standard student API
        $bartResponse = $this->actingAsTenant($this->homerUser, $this->greenwood)
            ->getJson("/api/students/{$this->bartStudent->id}");

        $bartResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->bartStudent->id);
    }

    /**
     * Cross-Tenant Isolation:
     * A parent from School A cannot access children from School B.
     */
    public function test_parent_cannot_view_children_from_another_school(): void
    {
        // Homer (Greenwood) tries to access Oakridge student
        $response = $this->actingAsTenant($this->homerUser, $this->greenwood)
            ->getJson("/api/parent/children/{$this->oakridgeStudent->id}/dashboard");

        $this->assertContains($response->status(), [403, 404]);

        // Oakridge parent tries to access Greenwood children
        $response2 = $this->actingAsTenant($this->oakridgeParentUser, $this->oakridge)
            ->getJson("/api/parent/children/{$this->bartStudent->id}/dashboard");

        $this->assertContains($response2->status(), [403, 404]);
    }

    /**
     * Admin Link Flow:
     * Admin links an existing parent to a student.
     */
    public function test_admin_can_link_existing_parent_to_enrolled_student(): void
    {
        // Homer is not linked to Milhouse yet. Admin links Homer to Milhouse as guardian
        $response = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson("/api/parents/{$this->homerProfile->id}/link-student", [
                'student_id' => $this->milhouseStudent->id,
                'relationship' => 'guardian',
                'is_primary_contact' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Student linked to parent successfully.');

        $this->assertTrue($this->homerProfile->fresh()->isLinkedTo($this->milhouseStudent->id));

        // Now Homer CAN view Milhouse's dashboard
        $milhouseDashboard = $this->actingAsTenant($this->homerUser, $this->greenwood)
            ->getJson("/api/parent/children/{$this->milhouseStudent->id}/dashboard");

        $milhouseDashboard->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student.id', $this->milhouseStudent->id);
    }

    /**
     * Duplicate Link Prevention:
     * Linking a student who is already linked fails with 422.
     */
    public function test_admin_cannot_link_already_linked_student_duplicate(): void
    {
        $response = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson("/api/parents/{$this->homerProfile->id}/link-student", [
                'student_id' => $this->bartStudent->id,
                'relationship' => 'father',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    /**
     * Admin Unlink Flow:
     * Admin unlinks a student from a parent.
     */
    public function test_admin_can_unlink_student_from_parent(): void
    {
        $response = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->deleteJson("/api/parents/{$this->homerProfile->id}/students/{$this->bartStudent->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Student unlinked from parent successfully.');

        $this->assertFalse($this->homerProfile->fresh()->isLinkedTo($this->bartStudent->id));

        // Homer now gets 403 trying to view Bart
        $bartDashboard = $this->actingAsTenant($this->homerUser, $this->greenwood)
            ->getJson("/api/parent/children/{$this->bartStudent->id}/dashboard");

        $bartDashboard->assertStatus(403);
    }

    /**
     * Admin Create Parent Flow:
     * Admin creates a new parent account and optionally links a student immediately.
     */
    public function test_admin_can_create_parent_and_link_student_on_creation(): void
    {
        $response = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/parents', [
                'name' => 'Ned Flanders',
                'email' => 'ned.flanders@springfield.org',
                'phone' => '+1 (555) 733-5555',
                'occupation' => 'Business Owner',
                'address' => '744 Evergreen Terrace',
                'student_id' => $this->milhouseStudent->id,
                'relationship' => 'guardian',
                'is_primary_contact' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'Ned Flanders')
            ->assertJsonPath('data.occupation', 'Business Owner');

        $newParent = ParentProfile::whereHas('user', function ($q) {
            $q->where('email', 'ned.flanders@springfield.org');
        })->firstOrFail();

        $this->assertTrue($newParent->isLinkedTo($this->milhouseStudent->id));
        $this->assertEquals(RoleEnum::PARENT->value, $newParent->user->role);
    }

    /**
     * Admin Invite Flow:
     * Admin invites a parent via email with claim code and student link.
     */
    public function test_admin_can_invite_parent_with_student_link(): void
    {
        $response = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/parents/invite', [
                'name' => 'Maude Flanders',
                'email' => 'maude.flanders@springfield.org',
                'student_id' => $this->milhouseStudent->id,
                'relationship' => 'guardian',
                'phone' => '+1 (555) 733-5556',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'parent',
                    'invitation_token',
                    'temporary_password',
                ],
            ]);
    }

    /**
     * Parent Management Directory Listing:
     * Admin can list parents with search and student count.
     */
    public function test_admin_can_list_and_search_parents(): void
    {
        $response = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->getJson('/api/parents?search=Homer');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.name', 'Homer Simpson');
    }

    /**
     * Non-Admin Authorization Check:
     * Regular teacher or parent cannot invoke parent management write actions.
     */
    public function test_non_admin_cannot_manage_parents(): void
    {
        $response = $this->actingAsTenant($this->greenwoodTeacher, $this->greenwood)
            ->postJson('/api/parents', [
                'name' => 'Unauthorized Parent',
                'email' => 'unauth@greenwood.edu',
            ]);

        $response->assertStatus(403);
    }
}
