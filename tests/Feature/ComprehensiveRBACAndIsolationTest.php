<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\CommunicationThread;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\GradeLevel;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\TransportRoute;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComprehensiveRBACAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected School $oakridge;
    protected School $maplewood;

    protected User $superAdmin;
    protected User $greenwoodAdmin;
    protected User $ednaTeacher;      // Greenwood Homeroom Teacher (Section A)
    protected User $hooverTeacher;    // Greenwood Math Teacher
    protected User $claraTeacher;     // Maplewood Teacher
    protected User $bartStudent;      // Greenwood Student
    protected User $lisaStudent;      // Greenwood Student
    protected User $tommyStudent;     // Maplewood Student
    protected User $homerParent;      // Greenwood Parent (linked to Bart & Lisa)
    protected User $sarahParent;      // Maplewood Parent (linked to Tommy)

    protected Student $bartStudentModel;
    protected Student $lisaStudentModel;
    protected Student $milhouseStudentModel;
    protected Student $tommyStudentModel;

    protected Section $sectionA;      // Greenwood Grade 9 - Section A (Edna homeroom)
    protected Section $sectionB;      // Greenwood Grade 9 - Section B
    protected Section $mapleSection;  // Maplewood Grade 3 - Room 103 (Clara homeroom)

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->greenwood = School::where('subdomain', 'greenwood')->firstOrFail();
        $this->oakridge = School::where('subdomain', 'oakridge')->firstOrFail();
        $this->maplewood = School::where('subdomain', 'maplewood')->firstOrFail();

        $this->superAdmin = User::where('email', 'superadmin@bina.test')->firstOrFail();
        $this->greenwoodAdmin = User::where('email', 'admin@greenwood.edu')->firstOrFail();
        $this->ednaTeacher = User::where('email', 'teacher@greenwood.edu')->firstOrFail();
        $this->hooverTeacher = User::where('email', 'hoover@greenwood.edu')->firstOrFail();
        $this->claraTeacher = User::where('email', 'teacher@maplewood.edu')->firstOrFail();

        $this->bartStudent = User::where('email', 'student@greenwood.edu')->firstOrFail();
        $this->lisaStudent = User::where('email', 'lisa.simpson@greenwood.edu')->firstOrFail();
        $this->tommyStudent = User::where('email', 'student@maplewood.edu')->firstOrFail();

        $this->homerParent = User::where('email', 'parent@greenwood.edu')->firstOrFail();
        $this->sarahParent = User::where('email', 'parent@maplewood.edu')->firstOrFail();

        app(\App\Tenancy\TenantManager::class)->bypass(function () {
            $this->bartStudentModel = Student::where('school_id', $this->greenwood->id)->where('admission_number', 'GRE-25-00101')->firstOrFail();
            $this->lisaStudentModel = Student::where('school_id', $this->greenwood->id)->where('admission_number', 'GRE-25-00102')->firstOrFail();
            $this->milhouseStudentModel = Student::where('school_id', $this->greenwood->id)->where('admission_number', 'GRE-25-00103')->firstOrFail();
            $this->tommyStudentModel = Student::where('school_id', $this->maplewood->id)->where('admission_number', 'MAP-25-00101')->firstOrFail();

            $activeYear = AcademicYear::where('school_id', $this->greenwood->id)->where('is_active', true)->firstOrFail();

            $this->sectionA = Section::where('school_id', $this->greenwood->id)
                ->where('academic_year_id', $activeYear->id)
                ->where('name', 'Section A')
                ->firstOrFail();

            $this->sectionB = Section::where('school_id', $this->greenwood->id)
                ->where('academic_year_id', $activeYear->id)
                ->where('name', 'Section B')
                ->firstOrFail();

            $this->mapleSection = Section::where('school_id', $this->maplewood->id)->where('name', 'Room 103')->firstOrFail();
        });
    }

    /**
     * Helper to authenticate as user with tenant header.
     */
    protected function actingAsTenant(User $user, School $school): static
    {
        $this->app['auth']->forgetGuards();
        app(\App\Tenancy\TenantManager::class)->setTenant($school);
        $token = $user->createToken('rbac-test-token')->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $school->id);
    }

    // =========================================================================
    // 1. CROSS-TENANT ISOLATION BOUNDARIES
    // =========================================================================

    public function test_cross_tenant_isolation_on_students_and_sections(): void
    {
        // Greenwood admin cannot access Maplewood student
        $response = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->getJson("/api/students/{$this->tommyStudentModel->id}");
        $this->assertContains($response->status(), [403, 404]);

        // Greenwood admin cannot access Maplewood section
        $secResponse = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->getJson("/api/sections/{$this->mapleSection->id}");
        $this->assertContains($secResponse->status(), [403, 404]);

        // Attempting to spoof header to Maplewood with Greenwood user credentials -> 403 Forbidden
        $token = $this->greenwoodAdmin->createToken('spoof-token')->plainTextToken;
        $spoofResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->maplewood->id)
            ->getJson('/api/students');
        $this->assertEquals(403, $spoofResponse->status());
    }

    public function test_cross_tenant_isolation_on_library(): void
    {
        $response = $this->actingAsTenant($this->ednaTeacher, $this->greenwood)
            ->getJson('/api/library/books');

        $response->assertStatus(200);
        $bookIds = collect($response->json('data.data') ?: $response->json('data'))->pluck('id');

        $maplewoodBook = app(\App\Tenancy\TenantManager::class)->bypass(fn () => Book::where('school_id', $this->maplewood->id)->firstOrFail());
        $this->assertFalse($bookIds->contains($maplewoodBook->id));
    }

    public function test_cross_tenant_isolation_on_transport(): void
    {
        $response = $this->actingAsTenant($this->homerParent, $this->greenwood)
            ->getJson('/api/transport/routes');

        $response->assertStatus(200);
        $routeNames = collect($response->json('data'))->pluck('name');

        $this->assertFalse($routeNames->contains('Route 10 - Maplewood Yellow Bus'));
    }

    public function test_cross_tenant_isolation_on_announcements(): void
    {
        $response = $this->actingAsTenant($this->bartStudent, $this->greenwood)
            ->getJson('/api/announcements');

        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title');

        $this->assertFalse($titles->contains('Welcome to Maplewood Elementary - 2025/2026 School Year!'));
    }

    // =========================================================================
    // 2. CROSS-SECTION TEACHER RBAC ISOLATION BOUNDARIES
    // =========================================================================

    public function test_teacher_cannot_mark_attendance_for_unassigned_section(): void
    {
        $payload = [
            'date' => '2025-10-15',
            'records' => [
                [
                    'student_id' => $this->milhouseStudentModel->id,
                    'status' => 'present',
                ],
            ],
        ];

        $response = $this->actingAsTenant($this->ednaTeacher, $this->greenwood)
            ->postJson("/api/sections/{$this->sectionB->id}/attendance", $payload);

        $this->assertEquals(403, $response->status());
    }

    public function test_teacher_cannot_enter_grades_for_unauthorized_subject_or_section(): void
    {
        [$mathSubject, $exam] = app(\App\Tenancy\TenantManager::class)->bypass(fn () => [
            Subject::where('school_id', $this->greenwood->id)->where('code', 'MTH-901')->firstOrFail(),
            Exam::where('school_id', $this->greenwood->id)->firstOrFail(),
        ]);

        $response = $this->actingAsTenant($this->ednaTeacher, $this->greenwood)
            ->postJson('/api/grades', [
                'exam_id' => $exam->id,
                'subject_id' => $mathSubject->id,
                'section_id' => $this->sectionB->id,
                'grades' => [
                    [
                        'student_id' => $this->milhouseStudentModel->id,
                        'marks_obtained' => 95.0,
                    ],
                ],
            ]);

        $this->assertEquals(403, $response->status());
    }

    public function test_teacher_cannot_create_or_delete_timetable_slots(): void
    {
        $mathSubject = app(\App\Tenancy\TenantManager::class)->bypass(fn () => Subject::where('school_id', $this->greenwood->id)->where('code', 'MTH-901')->firstOrFail());

        $response = $this->actingAsTenant($this->ednaTeacher, $this->greenwood)
            ->postJson("/api/sections/{$this->sectionB->id}/timetable", [
                'day_of_week' => 'monday',
                'period_number' => 6,
                'subject_id' => $mathSubject->id,
                'teacher_id' => $this->ednaTeacher->id,
                'start_time' => '14:00',
                'end_time' => '14:45',
            ]);

        $this->assertEquals(403, $response->status());
    }

    // =========================================================================
    // 3. STUDENT BOUNDARY ISOLATION
    // =========================================================================

    public function test_student_cannot_view_or_download_another_students_report_card(): void
    {
        $lisaCard = app(\App\Tenancy\TenantManager::class)->bypass(fn () => ReportCard::where('student_id', $this->lisaStudentModel->id)->firstOrFail());

        $response = $this->actingAsTenant($this->bartStudent, $this->greenwood)
            ->getJson("/api/report-cards/{$lisaCard->id}");

        $this->assertContains($response->status(), [403, 404]);

        $pdfResponse = $this->actingAsTenant($this->bartStudent, $this->greenwood)
            ->getJson("/api/report-cards/{$lisaCard->id}/pdf");

        $this->assertContains($pdfResponse->status(), [403, 404]);
    }

    public function test_student_cannot_view_another_students_attendance(): void
    {
        $response = $this->actingAsTenant($this->bartStudent, $this->greenwood)
            ->getJson("/api/students/{$this->lisaStudentModel->id}/attendance");

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_student_cannot_perform_staff_or_admin_operations(): void
    {
        $response1 = $this->actingAsTenant($this->bartStudent, $this->greenwood)
            ->postJson("/api/sections/{$this->sectionA->id}/attendance", [
                'date' => '2025-10-15',
                'records' => [],
            ]);
        $this->assertEquals(403, $response1->status());

        $response2 = $this->actingAsTenant($this->bartStudent, $this->greenwood)
            ->getJson('/api/admin/users');
        $this->assertEquals(403, $response2->status());
    }

    // =========================================================================
    // 4. PARENT BOUNDARY ISOLATION
    // =========================================================================

    public function test_parent_cannot_view_unlinked_child_dashboard_or_report_card(): void
    {
        $response = $this->actingAsTenant($this->homerParent, $this->greenwood)
            ->getJson("/api/parent/children/{$this->milhouseStudentModel->id}/dashboard");

        $this->assertEquals(403, $response->status());

        $attResponse = $this->actingAsTenant($this->homerParent, $this->greenwood)
            ->getJson("/api/parent/children/{$this->milhouseStudentModel->id}/attendance");

        $this->assertEquals(403, $attResponse->status());
    }

    public function test_parent_cannot_view_another_parents_communication_threads(): void
    {
        $tommyThread = app(\App\Tenancy\TenantManager::class)->bypass(fn () => CommunicationThread::where('student_id', $this->tommyStudentModel->id)->firstOrFail());

        $response = $this->actingAsTenant($this->homerParent, $this->greenwood)
            ->getJson("/api/communications/threads/{$tommyThread->id}");

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_parent_cannot_access_administrative_endpoints(): void
    {
        $response = $this->actingAsTenant($this->homerParent, $this->greenwood)
            ->getJson('/api/admin/stats');

        $this->assertEquals(403, $response->status());
    }

    // =========================================================================
    // 5. SCHOOL ADMIN BOUNDARY ISOLATION
    // =========================================================================

    public function test_school_admin_cannot_access_super_admin_endpoints(): void
    {
        $response = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->getJson('/api/dashboard?role=super_admin');

        $this->assertEquals(403, $response->status());
    }

    public function test_school_admin_cannot_modify_another_schools_academic_structure(): void
    {
        [$mapleYear, $mapleGrade] = app(\App\Tenancy\TenantManager::class)->bypass(fn () => [
            AcademicYear::where('school_id', $this->maplewood->id)->firstOrFail(),
            GradeLevel::where('school_id', $this->maplewood->id)->firstOrFail(),
        ]);

        $response = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/sections', [
                'academic_year_id' => $mapleYear->id,
                'grade_level_id' => $mapleGrade->id,
                'name' => 'Intruder Section',
                'capacity' => 30,
            ]);

        $this->assertContains($response->status(), [403, 404, 422]);
    }
}
