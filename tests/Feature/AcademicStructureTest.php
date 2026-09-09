<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\Section;
use App\Models\StudentSectionAssignment;
use App\Models\User;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicStructureTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected User $greenwoodAdmin;
    protected User $greenwoodTeacher;
    protected User $greenwoodStudent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            SchoolSeeder::class,
            RolesAndPermissionsSeeder::class,
            AcademicStructureSeeder::class,
        ]);

        $this->greenwood = School::where('subdomain', 'greenwood')->firstOrFail();
        app(\App\Tenancy\TenantManager::class)->setTenant($this->greenwood);

        $this->greenwoodAdmin = User::where('email', 'admin@greenwood.edu')->firstOrFail();
        $this->greenwoodTeacher = User::where('email', 'teacher@greenwood.edu')->firstOrFail();
        $this->greenwoodStudent = User::where('email', 'student@greenwood.edu')->firstOrFail();
    }

    /**
     * Acceptance Criterion 1:
     * Admin can create a year, add terms, add grade levels, and create sections with a homeroom teacher assigned.
     */
    public function test_admin_can_create_year_terms_grades_and_sections(): void
    {
        $token = $this->greenwoodAdmin->createToken('admin-token')->plainTextToken;

        // 1. Create Academic Year
        $yearRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->postJson('/api/academic-years', [
                'name' => '2026/2027',
                'start_date' => '2026-09-01',
                'end_date' => '2027-06-30',
                'is_active' => false,
            ]);

        $yearRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', '2026/2027');

        $yearId = $yearRes->json('data.id');

        // 2. Add Terms under the year
        $termRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->postJson("/api/academic-years/{$yearId}/terms", [
                'name' => 'Semester 1',
                'start_date' => '2026-09-01',
                'end_date' => '2027-01-20',
                'is_active' => true,
            ]);

        $termRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Semester 1');

        // 3. Add Grade Level
        $gradeRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->postJson('/api/grade-levels', [
                'name' => 'Kindergarten 1',
                'code' => 'KG1',
                'sequence' => 1,
                'description' => 'Early childhood foundation class.',
            ]);

        $gradeRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'KG1');

        $gradeId = $gradeRes->json('data.id');

        // 4. Create Section under Grade Level and Year with Homeroom Teacher assigned
        $sectionRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->postJson('/api/sections', [
                'academic_year_id' => $yearId,
                'grade_level_id' => $gradeId,
                'name' => 'KG1 - Bluebirds',
                'capacity' => 20,
                'homeroom_teacher_id' => $this->greenwoodTeacher->id,
            ]);

        $sectionRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'KG1 - Bluebirds')
            ->assertJsonPath('data.homeroom_teacher.id', $this->greenwoodTeacher->id)
            ->assertJsonPath('data.homeroom_teacher.name', $this->greenwoodTeacher->name);
    }

    /**
     * Acceptance Criterion 2:
     * Historical years/sections remain queryable read-only once a year is closed.
     */
    public function test_historical_years_and_sections_remain_queryable_read_only(): void
    {
        $adminToken = $this->greenwoodAdmin->createToken('admin-token')->plainTextToken;
        $closedYear = AcademicYear::where('name', '2024/2025')->firstOrFail();

        $this->assertTrue($closedYear->isClosed());

        // 1. Querying closed year details returns 200 OK
        $queryRes = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->getJson("/api/academic-years/{$closedYear->id}");

        $queryRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_closed', true)
            ->assertJsonPath('data.name', '2024/2025');

        $this->assertNotEmpty($queryRes->json('data.terms'));
        $this->assertNotEmpty($queryRes->json('data.sections'));

        // 2. Querying sections belonging to closed year returns 200 OK
        $sectionsRes = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->getJson("/api/sections?academic_year_id={$closedYear->id}");

        $sectionsRes->assertOk()
            ->assertJsonPath('success', true);
        $this->assertGreaterThan(0, count($sectionsRes->json('data')));

        // 3. Attempting to add a term to closed year returns 422 CLOSED_ACADEMIC_YEAR
        $addTermRes = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->postJson("/api/academic-years/{$closedYear->id}/terms", [
                'name' => 'Summer Term',
                'start_date' => '2025-07-01',
                'end_date' => '2025-08-31',
            ]);

        $addTermRes->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'CLOSED_ACADEMIC_YEAR');

        // 4. Attempting to add a section to closed year returns 422 CLOSED_ACADEMIC_YEAR
        $gradeLevel = GradeLevel::where('school_id', $this->greenwood->id)->firstOrFail();

        $addSectionRes = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->postJson('/api/sections', [
                'academic_year_id' => $closedYear->id,
                'grade_level_id' => $gradeLevel->id,
                'name' => 'Section Red',
            ]);

        $addSectionRes->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'CLOSED_ACADEMIC_YEAR');

        // 5. Attempting to update closed year returns 422 CLOSED_ACADEMIC_YEAR
        $updateYearRes = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->putJson("/api/academic-years/{$closedYear->id}", [
                'name' => '2024/2025 Modified',
                'start_date' => '2024-09-01',
                'end_date' => '2025-06-30',
            ]);

        $updateYearRes->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'CLOSED_ACADEMIC_YEAR');
    }

    /**
     * Promotion Concept:
     * A section belongs to exactly one academic year; promoting a student creates next year's section assignment,
     * without mutating history.
     */
    public function test_student_promotion_creates_new_assignment_preserving_history(): void
    {
        $adminToken = $this->greenwoodAdmin->createToken('admin-token')->plainTextToken;

        $histYear = AcademicYear::where('name', '2024/2025')->firstOrFail();
        $activeYear = AcademicYear::where('name', '2025/2026')->firstOrFail();
        $activeSection = Section::where('academic_year_id', $activeYear->id)->firstOrFail();

        // Check Bart's historical assignment in 2024/2025
        $histAssignment = StudentSectionAssignment::where('academic_year_id', $histYear->id)
            ->where('student_id', $this->greenwoodStudent->id)
            ->firstOrFail();

        $this->assertEquals('promoted', $histAssignment->status);
        $this->assertEquals('G9-001', $histAssignment->roll_number);

        // Promote student via promotion endpoint
        $promoteRes = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->postJson('/api/sections/promote', [
                'target_academic_year_id' => $activeYear->id,
                'target_section_id' => $activeSection->id,
                'student_ids' => [$this->greenwoodStudent->id],
            ]);

        $promoteRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.promoted_count', 1);

        // Verify historical assignment is completely untouched
        $histAssignmentReloaded = StudentSectionAssignment::where('academic_year_id', $histYear->id)
            ->where('student_id', $this->greenwoodStudent->id)
            ->firstOrFail();

        $this->assertEquals($histAssignment->id, $histAssignmentReloaded->id);
        $this->assertEquals('promoted', $histAssignmentReloaded->status);
        $this->assertEquals('G9-001', $histAssignmentReloaded->roll_number);

        // Verify active assignment exists for 2025/2026
        $activeAssignment = StudentSectionAssignment::where('academic_year_id', $activeYear->id)
            ->where('student_id', $this->greenwoodStudent->id)
            ->firstOrFail();

        $this->assertEquals($activeSection->id, $activeAssignment->section_id);
        $this->assertEquals('enrolled', $activeAssignment->status);
    }

    /**
     * Non-admin roles (Teacher, Student) cannot create or mutate academic hierarchy (403).
     */
    public function test_teacher_cannot_create_academic_structures(): void
    {
        $teacherToken = $this->greenwoodTeacher->createToken('teacher-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $teacherToken)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->postJson('/api/academic-years', [
                'name' => '2030/2031',
                'start_date' => '2030-09-01',
                'end_date' => '2031-06-30',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * Admin can directly manage academic terms (create, list, activate, and delete).
     */
    public function test_admin_can_manage_terms_direct_creation_and_activation(): void
    {
        $token = $this->greenwoodAdmin->createToken('admin-token')->plainTextToken;
        $activeYear = AcademicYear::where('is_active', true)->firstOrFail();

        // 1. Create term directly
        $createRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->postJson('/api/terms', [
                'academic_year_id' => $activeYear->id,
                'name' => 'Spring Trimester',
                'start_date' => '2026-01-15',
                'end_date' => '2026-04-15',
                'is_active' => false,
            ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Spring Trimester');

        $termId = $createRes->json('data.id');

        // 2. Activate term
        $activateRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->postJson("/api/terms/{$termId}/activate");

        $activateRes->assertStatus(200)
            ->assertJsonPath('data.is_active', true);

        // 3. List terms
        $listRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->getJson('/api/terms');

        $listRes->assertStatus(200);
        $termNames = collect($listRes->json('data'))->pluck('name')->all();
        $this->assertContains('Spring Trimester', $termNames);

        // 4. Delete term
        $deleteRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->deleteJson("/api/terms/{$termId}");

        $deleteRes->assertStatus(200);
        $this->assertNull(\App\Models\Term::find($termId));
    }
}
