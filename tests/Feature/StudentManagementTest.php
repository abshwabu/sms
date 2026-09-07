<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected School $oakridge;
    protected User $greenwoodAdmin;
    protected User $greenwoodTeacher;
    protected User $oakridgeAdmin;
    protected AcademicYear $greenwoodActiveYear;
    protected AcademicYear $greenwoodClosedYear;
    protected Section $sourceSection;

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
        $this->greenwoodTeacher = User::where('email', 'teacher@greenwood.edu')->firstOrFail();
        $this->oakridgeAdmin = User::where('email', 'admin@oakridge.edu')->firstOrFail();

        $this->greenwoodActiveYear = AcademicYear::withoutGlobalScopes()
            ->where('school_id', $this->greenwood->id)
            ->where('name', '2025/2026')
            ->firstOrFail();

        $this->greenwoodClosedYear = AcademicYear::withoutGlobalScopes()
            ->where('school_id', $this->greenwood->id)
            ->where('name', '2024/2025')
            ->firstOrFail();

        $this->sourceSection = Section::withoutGlobalScopes()
            ->where('school_id', $this->greenwood->id)
            ->where('academic_year_id', $this->greenwoodActiveYear->id)
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
     * Importing a CSV of 50 students creates valid student + enrollment + user records
     * with generated login credentials, and the students can authenticate.
     */
    public function test_admin_can_bulk_import_50_students_via_csv_with_credentials(): void
    {
        // Generate CSV content with 50 students
        $csvRows = ["name,dob,gender,address,guardian_name,guardian_phone,guardian_email,guardian_relationship"];
        for ($i = 1; $i <= 50; $i++) {
            $csvRows[] = sprintf(
                "Student Number %02d,2010-05-%02d,male,123 School Way,Guardian %02d,+1555000%02d,guardian%02d@example.com,Parent",
                $i,
                ($i % 28) + 1,
                $i,
                $i,
                $i
            );
        }
        $csvContent = implode("\n", $csvRows);

        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $response = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/students/import', [
                'file' => $file,
                'academic_year_id' => $this->greenwoodActiveYear->id,
                'section_id' => $this->sourceSection->id,
                'default_password' => 'BinaStudent2026!',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.imported_count', 50);

        // Verify database records
        $this->assertDatabaseCount('students', 50);
        $this->assertDatabaseCount('enrollments', 50);

        $credentials = $response->json('data.credentials');
        $this->assertCount(50, $credentials);
        $this->assertEquals('BinaStudent2026!', $credentials[0]['plain_password']);
        $this->assertEquals($this->sourceSection->name, $credentials[0]['section']);

        // Verify user records exist with student role and belong to Greenwood
        $firstStudentCred = $credentials[0];
        $studentUser = User::where('email', $firstStudentCred['email'])->first();
        $this->assertNotNull($studentUser);
        $this->assertEquals(RoleEnum::STUDENT->value, $studentUser->role);
        $this->assertEquals($this->greenwood->id, $studentUser->school_id);

        // Verify the student can log in with generated credentials
        $loginResponse = $this->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->postJson('/api/auth/login', [
                'email' => $firstStudentCred['email'],
                'password' => 'BinaStudent2026!',
            ]);

        $loginResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', RoleEnum::STUDENT->value)
            ->assertJsonStructure(['data' => ['token']]);
    }

    /**
     * Acceptance Criterion 2:
     * End-of-year promotion moves an entire section to the next grade level in one action,
     * preserving prior-year enrollment history.
     */
    public function test_end_of_year_promotion_moves_entire_section_to_next_grade_preserving_history(): void
    {
        // 1. Create a target academic year (e.g. 2026/2027) and a target section in Greenwood
        $targetYear = AcademicYear::withoutGlobalScopes()->create([
            'school_id' => $this->greenwood->id,
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_active' => false,
            'is_closed' => false,
        ]);

        $targetGrade = GradeLevel::withoutGlobalScopes()
            ->where('school_id', $this->greenwood->id)
            ->where('code', 'G10')
            ->firstOrFail();

        $targetSection = Section::withoutGlobalScopes()->create([
            'school_id' => $this->greenwood->id,
            'academic_year_id' => $targetYear->id,
            'grade_level_id' => $targetGrade->id,
            'name' => 'Grade 10 - Section A (2026)',
            'capacity' => 35,
        ]);

        // 2. Populate source section with 10 students
        $csvRows = ["name,dob,gender,address"];
        for ($i = 1; $i <= 10; $i++) {
            $csvRows[] = "Student {$i},2010-01-01,female,Springfield";
        }
        $file = UploadedFile::fake()->createWithContent('students.csv', implode("\n", $csvRows));

        $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/students/import', [
                'file' => $file,
                'academic_year_id' => $this->greenwoodActiveYear->id,
                'section_id' => $this->sourceSection->id,
            ])
            ->assertStatus(200);

        $students = Student::withoutGlobalScopes()
            ->where('school_id', $this->greenwood->id)
            ->where('current_section_id', $this->sourceSection->id)
            ->get();
        $this->assertCount(10, $students);

        // 3. Promote the entire section roster to the target section
        $promoteResponse = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/students/promote-roster', [
                'source_section_id' => $this->sourceSection->id,
                'target_academic_year_id' => $targetYear->id,
                'target_section_id' => $targetSection->id,
                'action' => 'promote',
            ]);

        $promoteResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.processed_count', 10)
            ->assertJsonPath('data.action', 'promote');

        // 4. Verify all 10 students are now updated to target section
        foreach ($students as $student) {
            $student->refresh();
            $this->assertEquals($targetSection->id, $student->current_section_id);
            $this->assertEquals('active', $student->status);

            // Verify full year-by-year history is preserved: 2 enrollments per student
            $enrollments = Enrollment::withoutGlobalScopes()
                ->where('student_id', $student->id)
                ->orderBy('academic_year_id')
                ->get();

            $this->assertCount(2, $enrollments);

            // Prior year enrollment is preserved with 'promoted' status
            $priorEnrollment = $enrollments->firstWhere('academic_year_id', $this->greenwoodActiveYear->id);
            $this->assertNotNull($priorEnrollment);
            $this->assertEquals($this->sourceSection->id, $priorEnrollment->section_id);
            $this->assertEquals('promoted', $priorEnrollment->status);

            // Next year enrollment is active
            $nextEnrollment = $enrollments->firstWhere('academic_year_id', $targetYear->id);
            $this->assertNotNull($nextEnrollment);
            $this->assertEquals($targetSection->id, $nextEnrollment->section_id);
            $this->assertEquals('enrolled', $nextEnrollment->status);
        }
    }

    /**
     * Test roster graduation marks students as graduated and preserves history.
     */
    public function test_roster_graduation_updates_status_and_clears_current_section(): void
    {
        // Import 3 senior students
        $csv = "name\nSenior A\nSenior B\nSenior C";
        $file = UploadedFile::fake()->createWithContent('seniors.csv', $csv);

        $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/students/import', [
                'file' => $file,
                'academic_year_id' => $this->greenwoodActiveYear->id,
                'section_id' => $this->sourceSection->id,
            ])
            ->assertStatus(200);

        // Graduate roster
        $response = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/students/promote-roster', [
                'source_section_id' => $this->sourceSection->id,
                'target_academic_year_id' => $this->greenwoodActiveYear->id,
                'action' => 'graduate',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.action', 'graduate')
            ->assertJsonPath('data.processed_count', 3);

        $graduatedStudents = Student::withoutGlobalScopes()
            ->where('school_id', $this->greenwood->id)
            ->where('status', 'graduated')
            ->get();

        $this->assertCount(3, $graduatedStudents);
        foreach ($graduatedStudents as $grad) {
            $this->assertNull($grad->current_section_id);
            $enrollment = Enrollment::withoutGlobalScopes()
                ->where('student_id', $grad->id)
                ->first();
            $this->assertEquals('graduated', $enrollment->status);
        }
    }

    /**
     * Test student search and filtering by section, grade level, status, and query term.
     */
    public function test_student_search_and_filters(): void
    {
        // Import students
        $csv = "name,dob,gender\nAlice Wonderland,2011-01-01,female\nBob Builder,2011-02-02,male";
        $file = UploadedFile::fake()->createWithContent('roster.csv', $csv);

        $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/students/import', [
                'file' => $file,
                'academic_year_id' => $this->greenwoodActiveYear->id,
                'section_id' => $this->sourceSection->id,
            ]);

        // Search by name
        $searchRes = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->getJson('/api/students?search=Alice');

        $searchRes->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.user.name', 'Alice Wonderland');

        // Filter by section
        $secRes = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->getJson('/api/students?section_id=' . $this->sourceSection->id);

        $secRes->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 2);

        // Filter by grade level
        $gradeRes = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->getJson('/api/students?grade_level_id=' . $this->sourceSection->grade_level_id);

        $gradeRes->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 2);

        // Filter by status
        $statusRes = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->getJson('/api/students?status=active');

        $statusRes->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 2);
    }

    /**
     * Test importing or promoting into a closed academic year fails with 422 CLOSED_ACADEMIC_YEAR.
     */
    public function test_cannot_import_or_promote_into_closed_academic_year(): void
    {
        $closedSection = Section::withoutGlobalScopes()
            ->where('school_id', $this->greenwood->id)
            ->where('academic_year_id', $this->greenwoodClosedYear->id)
            ->first();

        // If no closed section seeded, create one directly
        if (! $closedSection) {
            $grade = GradeLevel::withoutGlobalScopes()->where('school_id', $this->greenwood->id)->first();
            $closedSection = Section::withoutGlobalScopes()->create([
                'school_id' => $this->greenwood->id,
                'academic_year_id' => $this->greenwoodClosedYear->id,
                'grade_level_id' => $grade->id,
                'name' => 'Historical Section',
                'capacity' => 30,
            ]);
        }

        $file = UploadedFile::fake()->createWithContent('test.csv', "name\nJohn Doe");

        $importRes = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/students/import', [
                'file' => $file,
                'academic_year_id' => $this->greenwoodClosedYear->id,
                'section_id' => $closedSection->id,
            ]);

        $importRes->assertStatus(422)
            ->assertJsonPath('error.code', 'CLOSED_ACADEMIC_YEAR');

        // Test promoting into closed academic year
        $promoteRes = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/students/promote-roster', [
                'source_section_id' => $this->sourceSection->id,
                'target_academic_year_id' => $this->greenwoodClosedYear->id,
                'target_section_id' => $closedSection->id,
                'action' => 'promote',
            ]);

        $promoteRes->assertStatus(422)
            ->assertJsonPath('error.code', 'CLOSED_ACADEMIC_YEAR');
    }

    /**
     * Test tenant isolation: School A cannot see or modify School B students.
     */
    public function test_tenant_isolation_enforced(): void
    {
        // 1. Create a student in Greenwood
        $file = UploadedFile::fake()->createWithContent('test.csv', "name\nGreenwood Kid");
        $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/students/import', [
                'file' => $file,
                'academic_year_id' => $this->greenwoodActiveYear->id,
                'section_id' => $this->sourceSection->id,
            ]);

        $student = Student::withoutGlobalScopes()->where('school_id', $this->greenwood->id)->first();

        // 2. Oakridge admin querying /api/students should see 0 students
        $oakridgeRes = $this->actingAsTenant($this->oakridgeAdmin, $this->oakridge)
            ->getJson('/api/students');

        $oakridgeRes->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 0);

        // 3. Oakridge admin cannot fetch Greenwood student details
        $showRes = $this->actingAsTenant($this->oakridgeAdmin, $this->oakridge)
            ->getJson("/api/students/{$student->id}");

        $showRes->assertStatus(404);
    }

    /**
     * Test teacher can view student lists but cannot bulk import or promote rosters (RBAC).
     */
    public function test_teacher_can_view_but_cannot_modify_roster(): void
    {
        // View student list is allowed
        $this->actingAsTenant($this->greenwoodTeacher, $this->greenwood)
            ->getJson('/api/students')
            ->assertStatus(200);

        // Bulk import is forbidden (403)
        $file = UploadedFile::fake()->createWithContent('test.csv', "name\nDisallowed Kid");
        $this->actingAsTenant($this->greenwoodTeacher, $this->greenwood)
            ->postJson('/api/students/import', [
                'file' => $file,
                'academic_year_id' => $this->greenwoodActiveYear->id,
                'section_id' => $this->sourceSection->id,
            ])
            ->assertStatus(403);

        // Promotion is forbidden (403)
        $this->actingAsTenant($this->greenwoodTeacher, $this->greenwood)
            ->postJson('/api/students/promote-roster', [
                'source_section_id' => $this->sourceSection->id,
                'target_academic_year_id' => $this->greenwoodActiveYear->id,
                'action' => 'promote',
            ])
            ->assertStatus(403);
    }
}
