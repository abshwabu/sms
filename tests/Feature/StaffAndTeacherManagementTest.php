<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAndTeacherManagementTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected School $oakridge;
    protected User $greenwoodAdmin;
    protected User $teacher1;
    protected User $teacher2;
    protected Staff $staff1;
    protected Staff $staff2;
    protected AcademicYear $academicYear;
    protected GradeLevel $grade5;
    protected Section $section5A;
    protected Section $section5B;
    protected Section $section5C;
    protected Course $scienceCourse;
    protected Course $mathCourse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            SchoolSeeder::class,
            RolesAndPermissionsSeeder::class,
            AcademicStructureSeeder::class,
        ]);

        $this->greenwood = School::where('subdomain', 'greenwood')->firstOrFail();
        $this->oakridge = School::where('subdomain', 'oakridge')->firstOrFail();

        app(\App\Tenancy\TenantManager::class)->setTenant($this->greenwood);

        $this->greenwoodAdmin = User::where('email', 'admin@greenwood.edu')->firstOrFail();

        $this->academicYear = AcademicYear::withoutGlobalScopes()
            ->where('school_id', $this->greenwood->id)
            ->where('name', '2025/2026')
            ->firstOrFail();

        $this->grade5 = GradeLevel::withoutGlobalScopes()->create([
            'school_id' => $this->greenwood->id,
            'name' => 'Grade 5',
            'code' => 'G5',
            'sequence' => 5,
        ]);

        // Courses
        $this->scienceCourse = Course::withoutGlobalScopes()->create([
            'school_id' => $this->greenwood->id,
            'name' => 'General Science',
            'code' => 'SCI-101',
        ]);

        $this->mathCourse = Course::withoutGlobalScopes()->create([
            'school_id' => $this->greenwood->id,
            'name' => 'Elementary Math',
            'code' => 'MTH-101',
        ]);

        // Teacher 1
        $this->teacher1 = User::withoutGlobalScopes()->create([
            'school_id' => $this->greenwood->id,
            'name' => 'Alice Teacher',
            'email' => 'alice.teacher@greenwood.edu',
            'password' => 'password123',
            'role' => RoleEnum::TEACHER->value,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->staff1 = Staff::withoutGlobalScopes()->create([
            'school_id' => $this->greenwood->id,
            'user_id' => $this->teacher1->id,
            'staff_number' => 'STF-26-00001',
            'role_title' => 'Grade 5 Homeroom & Science Teacher',
            'department' => 'Sciences',
            'status' => 'active',
            'subjects_taught' => ['General Science'],
        ]);

        // Teacher 2
        $this->teacher2 = User::withoutGlobalScopes()->create([
            'school_id' => $this->greenwood->id,
            'name' => 'Bob Teacher',
            'email' => 'bob.teacher@greenwood.edu',
            'password' => 'password123',
            'role' => RoleEnum::TEACHER->value,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->staff2 = Staff::withoutGlobalScopes()->create([
            'school_id' => $this->greenwood->id,
            'user_id' => $this->teacher2->id,
            'staff_number' => 'STF-26-00002',
            'role_title' => 'Grade 5 Homeroom & Math Teacher',
            'department' => 'Mathematics',
            'status' => 'active',
            'subjects_taught' => ['Elementary Math'],
        ]);

        // Sections
        // 5-A has Teacher 1 as homeroom
        $this->section5A = Section::withoutGlobalScopes()->create([
            'school_id' => $this->greenwood->id,
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->grade5->id,
            'name' => 'Grade 5 - A',
            'capacity' => 30,
            'homeroom_teacher_id' => $this->teacher1->id,
        ]);

        // 5-B has Teacher 2 as homeroom
        $this->section5B = Section::withoutGlobalScopes()->create([
            'school_id' => $this->greenwood->id,
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->grade5->id,
            'name' => 'Grade 5 - B',
            'capacity' => 30,
            'homeroom_teacher_id' => $this->teacher2->id,
        ]);

        // 5-C has no homeroom teacher assigned yet
        $this->section5C = Section::withoutGlobalScopes()->create([
            'school_id' => $this->greenwood->id,
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->grade5->id,
            'name' => 'Grade 5 - C',
            'capacity' => 30,
            'homeroom_teacher_id' => null,
        ]);

        // Add students into Section 5-A and 5-B
        $s1User = User::withoutGlobalScopes()->create([
            'school_id' => $this->greenwood->id,
            'name' => 'Tommy Kid',
            'email' => 'tommy@greenwood.edu',
            'password' => 'password123',
            'role' => RoleEnum::STUDENT->value,
            'status' => 'active',
        ]);
        Student::withoutGlobalScopes()->create([
            'school_id' => $this->greenwood->id,
            'user_id' => $s1User->id,
            'admission_number' => 'GRE-5A-01',
            'current_section_id' => $this->section5A->id,
            'status' => 'active',
        ]);

        $s2User = User::withoutGlobalScopes()->create([
            'school_id' => $this->greenwood->id,
            'name' => 'Sally Kid',
            'email' => 'sally@greenwood.edu',
            'password' => 'password123',
            'role' => RoleEnum::STUDENT->value,
            'status' => 'active',
        ]);
        Student::withoutGlobalScopes()->create([
            'school_id' => $this->greenwood->id,
            'user_id' => $s2User->id,
            'admission_number' => 'GRE-5B-01',
            'current_section_id' => $this->section5B->id,
            'status' => 'active',
        ]);
    }

    protected function actingAsTenant(User $user, School $school): static
    {
        $this->app['auth']->forgetGuards();
        app(\App\Tenancy\TenantManager::class)->setTenant($school);
        $token = $user->createToken('test-token')->plainTextToken;
        return $this->withHeader('Authorization', 'Bearer ' . $token)
                    ->withHeader('X-School-Id', (string) $school->id);
    }

    /**
     * Test staff directory listing and filters.
     */
    public function test_staff_directory_listing_and_filtering(): void
    {
        $response = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->getJson('/api/staff');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.pagination.total', 2);

        // Filter by department
        $deptResponse = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->getJson('/api/staff?department=Sciences');

        $deptResponse->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.role_title', 'Grade 5 Homeroom & Science Teacher');

        // Filter by search
        $searchResponse = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->getJson('/api/staff?search=Bob');

        $searchResponse->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.user.name', 'Bob Teacher');
    }

    /**
     * Test admin can create and update staff profile.
     */
    public function test_admin_can_create_and_update_staff_member(): void
    {
        $payload = [
            'name' => 'Carol Danvers',
            'email' => 'carol.danvers@greenwood.edu',
            'role_title' => 'Physics Teacher',
            'department' => 'Sciences',
            'hire_date' => '2025-08-15',
            'status' => 'active',
            'course_ids' => [$this->scienceCourse->id],
        ];

        $createRes = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/staff', $payload);

        $createRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role_title', 'Physics Teacher')
            ->assertJsonPath('data.courses.0.code', 'SCI-101');

        $staffId = $createRes->json('data.id');

        // Update staff
        $updateRes = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->putJson("/api/staff/{$staffId}", [
                'role_title' => 'Senior Physics Teacher',
                'department' => 'Physical Sciences',
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.role_title', 'Senior Physics Teacher')
            ->assertJsonPath('data.department', 'Physical Sciences');
    }

    /**
     * Acceptance Criteria Core Test:
     * A teacher assigned as homeroom for "Grade 5 - A" can see that section's roster
     * and enter attendance/grades for it.
     */
    public function test_homeroom_teacher_can_view_roster_and_enter_attendance_and_grades(): void
    {
        // 1. Teacher 1 can view Grade 5 - A details
        $this->actingAsTenant($this->teacher1, $this->greenwood)
            ->getJson("/api/sections/{$this->section5A->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Grade 5 - A');

        // 2. Teacher 1 can view Grade 5 - A roster
        $rosterRes = $this->actingAsTenant($this->teacher1, $this->greenwood)
            ->getJson("/api/sections/{$this->section5A->id}/roster");

        $rosterRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_students', 1)
            ->assertJsonPath('data.students.0.user.name', 'Tommy Kid');

        // 3. Teacher 1 can enter attendance for Grade 5 - A
        $attRes = $this->actingAsTenant($this->teacher1, $this->greenwood)
            ->postJson("/api/sections/{$this->section5A->id}/attendance", [
                'date' => '2026-09-08',
                'records' => [
                    ['student_id' => 1, 'status' => 'present'],
                ],
            ]);

        $attRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 4. Teacher 1 can enter grades for Grade 5 - A
        $gradeRes = $this->actingAsTenant($this->teacher1, $this->greenwood)
            ->postJson("/api/sections/{$this->section5A->id}/grades", [
                'course_id' => $this->scienceCourse->id,
                'grades' => [
                    ['student_id' => 1, 'score' => 95],
                ],
            ]);

        $gradeRes->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Acceptance Criteria Core Test:
     * A teacher cannot see other sections (like Grade 5 - B) unless separately assigned a subject there.
     */
    public function test_teacher_cannot_see_other_sections_or_enter_attendance_or_grades(): void
    {
        // 1. Teacher 1 cannot view Grade 5 - B details (403)
        $this->actingAsTenant($this->teacher1, $this->greenwood)
            ->getJson("/api/sections/{$this->section5B->id}")
            ->assertStatus(403);

        // 2. Teacher 1 cannot view Grade 5 - B roster (403)
        $this->actingAsTenant($this->teacher1, $this->greenwood)
            ->getJson("/api/sections/{$this->section5B->id}/roster")
            ->assertStatus(403);

        // 3. Teacher 1 cannot enter attendance for Grade 5 - B (403)
        $this->actingAsTenant($this->teacher1, $this->greenwood)
            ->postJson("/api/sections/{$this->section5B->id}/attendance", [
                'date' => '2026-09-08',
            ])
            ->assertStatus(403);

        // 4. Teacher 1 cannot enter grades for Grade 5 - B (403)
        $this->actingAsTenant($this->teacher1, $this->greenwood)
            ->postJson("/api/sections/{$this->section5B->id}/grades", [
                'course_id' => $this->scienceCourse->id,
            ])
            ->assertStatus(403);
    }

    /**
     * Acceptance Criteria Core Test:
     * When separately assigned a subject in Grade 5 - B, the teacher CAN see that section's roster
     * and grade that specific subject, but CANNOT grade other subjects in that section.
     */
    public function test_teacher_assigned_subject_in_other_section_gains_access_to_that_section(): void
    {
        // Admin assigns Teacher 1 to teach Science in Grade 5 - B
        $assignRes = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson("/api/sections/{$this->section5B->id}/assign-subject-teacher", [
                'course_id' => $this->scienceCourse->id,
                'staff_id' => $this->staff1->id,
            ]);

        $assignRes->assertStatus(201)
            ->assertJsonPath('success', true);

        // 1. Now Teacher 1 CAN see Grade 5 - B details
        $this->actingAsTenant($this->teacher1, $this->greenwood)
            ->getJson("/api/sections/{$this->section5B->id}")
            ->assertStatus(200);

        // 2. Teacher 1 CAN see Grade 5 - B roster
        $rosterRes = $this->actingAsTenant($this->teacher1, $this->greenwood)
            ->getJson("/api/sections/{$this->section5B->id}/roster");

        $rosterRes->assertStatus(200)
            ->assertJsonPath('data.total_students', 1)
            ->assertJsonPath('data.students.0.user.name', 'Sally Kid');

        // 3. Teacher 1 CAN enter attendance for Grade 5 - B
        $this->actingAsTenant($this->teacher1, $this->greenwood)
            ->postJson("/api/sections/{$this->section5B->id}/attendance", [
                'date' => '2026-09-08',
            ])
            ->assertStatus(200);

        // 4. Teacher 1 CAN enter grades for Science in Grade 5 - B
        $this->actingAsTenant($this->teacher1, $this->greenwood)
            ->postJson("/api/sections/{$this->section5B->id}/grades", [
                'course_id' => $this->scienceCourse->id,
                'grades' => [
                    ['student_id' => 2, 'score' => 88],
                ],
            ])
            ->assertStatus(200);

        // 5. But Teacher 1 CANNOT enter grades for Math in Grade 5 - B (since they only teach Science!)
        $this->actingAsTenant($this->teacher1, $this->greenwood)
            ->postJson("/api/sections/{$this->section5B->id}/grades", [
                'course_id' => $this->mathCourse->id,
                'grades' => [
                    ['student_id' => 2, 'score' => 70],
                ],
            ])
            ->assertStatus(403);

        // 6. Teacher 1 STILL CANNOT see Grade 5 - C (where they have no assignments)
        $this->actingAsTenant($this->teacher1, $this->greenwood)
            ->getJson("/api/sections/{$this->section5C->id}/roster")
            ->assertStatus(403);
    }

    /**
     * Test teachers cannot perform admin-only actions (creating staff, assigning homeroom/subject teachers).
     */
    public function test_teacher_cannot_perform_admin_only_staff_actions(): void
    {
        // Teacher cannot create staff (403)
        $this->actingAsTenant($this->teacher1, $this->greenwood)
            ->postJson('/api/staff', [
                'name' => 'Hacker Teacher',
                'email' => 'hack@example.com',
                'role_title' => 'Teacher',
            ])
            ->assertStatus(403);

        // Teacher cannot assign homeroom teacher (403)
        $this->actingAsTenant($this->teacher1, $this->greenwood)
            ->postJson("/api/sections/{$this->section5C->id}/assign-homeroom", [
                'teacher_id' => $this->teacher1->id,
            ])
            ->assertStatus(403);

        // Teacher cannot assign subject teacher (403)
        $this->actingAsTenant($this->teacher1, $this->greenwood)
            ->postJson("/api/sections/{$this->section5C->id}/assign-subject-teacher", [
                'course_id' => $this->scienceCourse->id,
                'staff_id' => $this->staff1->id,
            ])
            ->assertStatus(403);
    }

    /**
     * Test multi-tenant isolation: Oakridge cannot access Greenwood staff.
     */
    public function test_tenant_isolation_for_staff(): void
    {
        $oakridgeAdmin = User::where('email', 'admin@oakridge.edu')->firstOrFail();

        // Oakridge admin viewing /api/staff should not see Greenwood staff
        $res = $this->actingAsTenant($oakridgeAdmin, $this->oakridge)
            ->getJson('/api/staff');

        $res->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 0);

        // Oakridge admin cannot fetch Greenwood staff details (404)
        $this->actingAsTenant($oakridgeAdmin, $this->oakridge)
            ->getJson("/api/staff/{$this->staff1->id}")
            ->assertStatus(404);
    }

    /**
     * Test admin can create a teacher with credentials and temporary password is returned.
     */
    public function test_admin_can_add_teacher_with_credentials_and_login(): void
    {
        // 1. Create with custom password
        $resCustom = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/staff', [
                'name' => 'Prof. Severus Snape',
                'email' => 'snape@greenwood.edu',
                'role_title' => 'Potions & Chemistry Teacher',
                'department' => 'Sciences',
                'password' => 'SecurePass123!',
                'course_ids' => [$this->scienceCourse->id],
            ]);

        $resCustom->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.temporary_password', 'SecurePass123!')
            ->assertJsonPath('data.user.email', 'snape@greenwood.edu')
            ->assertJsonPath('data.user.role', RoleEnum::TEACHER->value);

        // Verify login works with custom password
        $loginRes = $this->postJson('/api/auth/login', [
            'email' => 'snape@greenwood.edu',
            'password' => 'SecurePass123!',
        ]);
        $loginRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', 'teacher');

        // 2. Create with auto-generated password
        $resAuto = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/staff', [
                'name' => 'Prof. Minerva McGonagall',
                'email' => 'minerva@greenwood.edu',
                'role_title' => 'Transfiguration & Math Teacher',
                'department' => 'Mathematics',
            ]);

        $resAuto->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['temporary_password']]);

        $autoPass = $resAuto->json('data.temporary_password');
        $this->assertNotEmpty($autoPass);
        $this->assertGreaterThanOrEqual(10, strlen($autoPass));

        // Verify login works with auto-generated password
        $loginAutoRes = $this->postJson('/api/auth/login', [
            'email' => 'minerva@greenwood.edu',
            'password' => $autoPass,
        ]);
        $loginAutoRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', 'teacher');
    }
}
