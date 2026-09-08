<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\RoleEnum;
use App\Events\StudentMarkedAbsent;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\SchoolCalendar;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\AbsenceNotificationHook;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\AttendanceSeeder;
use Database\Seeders\ParentSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SchoolSeeder;
use Database\Seeders\StaffSeeder;
use Database\Seeders\StudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected School $oakridge;
    protected User $greenwoodAdmin;
    protected User $ednaTeacher;      // Homeroom teacher of Grade 9 - Section A
    protected User $hooverTeacher;    // Other teacher (Mathematics)
    protected User $homerParent;       // Parent of Bart & Lisa
    protected User $bartUser;          // Student
    protected Student $bartStudent;
    protected Student $lisaStudent;
    protected Student $milhouseStudent;
    protected Section $section9A;
    protected AcademicYear $year2025;
    protected AcademicYear $closedYear;

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
            AttendanceSeeder::class,
        ]);

        $this->greenwood = School::where('subdomain', 'greenwood')->firstOrFail();
        $this->oakridge = School::where('subdomain', 'oakridge')->firstOrFail();

        app(\App\Tenancy\TenantManager::class)->setTenant($this->greenwood);

        $this->greenwoodAdmin = User::where('email', 'admin@greenwood.edu')->firstOrFail();
        $this->ednaTeacher = User::where('email', 'teacher@greenwood.edu')->firstOrFail();
        $this->hooverTeacher = User::where('email', 'hoover@greenwood.edu')->firstOrFail();
        $this->homerParent = User::where('email', 'parent@greenwood.edu')->firstOrFail();

        $this->year2025 = AcademicYear::withoutGlobalScopes()
            ->where('school_id', $this->greenwood->id)
            ->where('name', '2025/2026')
            ->firstOrFail();

        $this->closedYear = AcademicYear::withoutGlobalScopes()
            ->where('school_id', $this->greenwood->id)
            ->where('name', '2024/2025')
            ->firstOrFail();

        $this->section9A = Section::withoutGlobalScopes()
            ->where('school_id', $this->greenwood->id)
            ->where('academic_year_id', $this->year2025->id)
            ->firstOrFail();

        // Ensure Edna is homeroom teacher of Grade 9 - Section A
        $this->section9A->update(['homeroom_teacher_id' => $this->ednaTeacher->id]);

        $this->bartStudent = Student::where('admission_number', 'GRE-25-00101')->firstOrFail();
        $this->lisaStudent = Student::where('admission_number', 'GRE-25-00102')->firstOrFail();
        $this->milhouseStudent = Student::where('admission_number', 'GRE-25-00103')->firstOrFail();
        $this->bartUser = $this->bartStudent->user;
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
     * Acceptance Criterion 1:
     * Teacher marks a full section's attendance in under 5 clicks for a "mostly present" day.
     * Evaluates bulk mark all present with single exception payload.
     */
    public function test_teacher_can_bulk_mark_full_section_with_mostly_present_in_one_action(): void
    {
        $date = '2025-09-15'; // Monday

        $payload = [
            'date' => $date,
            'default_status' => AttendanceStatus::PRESENT->value,
            'records' => [
                [
                    'student_id' => $this->bartStudent->id,
                    'status' => AttendanceStatus::ABSENT->value,
                    'remarks' => 'Dentist appointment',
                ],
            ],
        ];

        $response = $this->actingAsTenant($this->ednaTeacher, $this->greenwood)
            ->postJson("/api/sections/{$this->section9A->id}/attendance", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 5)
            ->assertJsonPath('data.present', 4)
            ->assertJsonPath('data.absent', 1)
            ->assertJsonPath('data.late', 0);

        // Verify records in database
        $this->assertDatabaseHas('attendance_records', [
            'school_id' => $this->greenwood->id,
            'section_id' => $this->section9A->id,
            'student_id' => $this->bartStudent->id,
            'date' => $date,
            'status' => 'absent',
            'remarks' => 'Dentist appointment',
            'marked_by' => $this->ednaTeacher->id,
        ]);

        $this->assertDatabaseHas('attendance_records', [
            'school_id' => $this->greenwood->id,
            'section_id' => $this->section9A->id,
            'student_id' => $this->lisaStudent->id,
            'date' => $date,
            'status' => 'present',
        ]);

        $this->assertDatabaseHas('attendance_records', [
            'school_id' => $this->greenwood->id,
            'section_id' => $this->section9A->id,
            'student_id' => $this->milhouseStudent->id,
            'date' => $date,
            'status' => 'present',
        ]);
    }

    /**
     * Acceptance Criterion 2:
     * Attendance % is correctly computed excluding non-school days (weekends/holidays — reference school_calendar).
     */
    public function test_attendance_percentage_correctly_excludes_weekends_and_holidays(): void
    {
        // Date range: 2025-10-06 (Mon) to 2025-10-17 (Fri) = 12 calendar days (10 weekdays, 2 weekend days)
        $startDate = '2025-10-06';
        $endDate = '2025-10-17';

        // Add 2 explicit holidays on weekdays:
        // - 2025-10-08 (Wednesday) Holiday
        // - 2025-10-13 (Monday) Holiday
        SchoolCalendar::create([
            'school_id' => $this->greenwood->id,
            'academic_year_id' => $this->year2025->id,
            'date' => '2025-10-08',
            'day_type' => 'holiday',
            'is_school_day' => false,
            'description' => 'Fall Staff Development',
        ]);

        SchoolCalendar::create([
            'school_id' => $this->greenwood->id,
            'academic_year_id' => $this->year2025->id,
            'date' => '2025-10-13',
            'day_type' => 'holiday',
            'is_school_day' => false,
            'description' => 'Indigenous Peoples Day',
        ]);

        // Total school days: 10 weekdays - 2 holidays = 8 school days!
        $schoolDaysCount = SchoolCalendar::getSchoolDaysCount($startDate, $endDate, $this->greenwood->id);
        $this->assertEquals(8, $schoolDaysCount);

        // Record attendance for Lisa:
        // 7 days present, 1 day late = 8 attended out of 8 school days -> 100%
        $schoolDates = SchoolCalendar::getSchoolDatesBetween($startDate, $endDate, $this->greenwood->id);
        foreach ($schoolDates as $index => $date) {
            $status = ($index === 0) ? AttendanceStatus::LATE->value : AttendanceStatus::PRESENT->value;
            AttendanceRecord::updateOrCreate(
                [
                    'school_id' => $this->greenwood->id,
                    'student_id' => $this->lisaStudent->id,
                    'date' => $date,
                ],
                [
                    'section_id' => $this->section9A->id,
                    'academic_year_id' => $this->year2025->id,
                    'status' => $status,
                    'marked_by' => $this->ednaTeacher->id,
                ]
            );
        }

        // Record attendance for Bart:
        // 6 days present, 2 days absent = 6 attended out of 8 school days -> (6/8)*100 = 75.0%
        foreach ($schoolDates as $index => $date) {
            $status = ($index >= 6) ? AttendanceStatus::ABSENT->value : AttendanceStatus::PRESENT->value;
            AttendanceRecord::updateOrCreate(
                [
                    'school_id' => $this->greenwood->id,
                    'student_id' => $this->bartStudent->id,
                    'date' => $date,
                ],
                [
                    'section_id' => $this->section9A->id,
                    'academic_year_id' => $this->year2025->id,
                    'status' => $status,
                    'marked_by' => $this->ednaTeacher->id,
                ]
            );
        }

        // Query Lisa's attendance summary
        $lisaRes = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->getJson("/api/students/{$this->lisaStudent->id}/attendance-summary?start_date={$startDate}&end_date={$endDate}");

        $lisaRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_school_days', 8)
            ->assertJsonPath('data.summary.present_days', 7)
            ->assertJsonPath('data.summary.late_days', 1)
            ->assertJsonPath('data.summary.absent_days', 0)
            ->assertJsonPath('data.summary.attended_days', 8)
            ->assertJsonPath('data.summary.attendance_percentage', 100);

        // Query Bart's attendance summary
        $bartRes = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->getJson("/api/students/{$this->bartStudent->id}/attendance-summary?start_date={$startDate}&end_date={$endDate}");

        $bartRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_school_days', 8)
            ->assertJsonPath('data.summary.present_days', 6)
            ->assertJsonPath('data.summary.absent_days', 2)
            ->assertJsonPath('data.summary.attended_days', 6)
            ->assertJsonPath('data.summary.attendance_percentage', 75);
    }

    /**
     * Authorization Checks:
     * Homeroom teacher can mark attendance; unassigned teacher cannot (403 Forbidden).
     */
    public function test_unassigned_teacher_cannot_mark_attendance_for_section(): void
    {
        $response = $this->actingAsTenant($this->hooverTeacher, $this->greenwood)
            ->postJson("/api/sections/{$this->section9A->id}/attendance", [
                'date' => '2025-09-16',
                'default_status' => 'present',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Absence Notification Trigger Hook:
     * When a student is marked absent, the notification hook triggers and dispatches StudentMarkedAbsent event.
     */
    public function test_marking_student_absent_triggers_notification_hook(): void
    {
        Event::fake([StudentMarkedAbsent::class]);

        $response = $this->actingAsTenant($this->ednaTeacher, $this->greenwood)
            ->postJson("/api/sections/{$this->section9A->id}/attendance", [
                'date' => '2025-09-17',
                'records' => [
                    [
                        'student_id' => $this->bartStudent->id,
                        'status' => 'absent',
                        'remarks' => 'Unexcused absence',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.absent', 1);

        Event::assertDispatched(StudentMarkedAbsent::class, function ($e) {
            return (int) $e->student->id === (int) $this->bartStudent->id
                && $e->attendanceRecord->status === 'absent';
        });
    }

    /**
     * Section Daily Attendance Query:
     * Retrieves roster status with recorded counts and percentage.
     */
    public function test_teacher_can_view_section_daily_attendance_roster(): void
    {
        $response = $this->actingAsTenant($this->ednaTeacher, $this->greenwood)
            ->getJson("/api/sections/{$this->section9A->id}/attendance?date=2025-09-02");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stats.total', 5)
            ->assertJsonPath('data.stats.present', 5)
            ->assertJsonPath('data.stats.is_fully_marked', true)
            ->assertJsonPath('data.stats.attendance_percentage', 100)
            ->assertJsonCount(5, 'data.roster');
    }

    /**
     * Section Attendance Summary & Trends:
     * Returns period summary and daily trends over calendar school days.
     */
    public function test_user_can_view_section_attendance_summary(): void
    {
        $response = $this->actingAsTenant($this->ednaTeacher, $this->greenwood)
            ->getJson("/api/sections/{$this->section9A->id}/attendance-summary?start_date=2025-09-01&end_date=2025-09-10");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'section' => ['id', 'name', 'grade_level', 'students_count'],
                    'period' => ['start_date', 'end_date', 'school_days_in_period'],
                    'overall_summary' => ['average_attendance_percentage', 'total_present', 'total_late'],
                    'daily_trends',
                ],
            ]);
    }

    /**
     * Student Read-Only View:
     * Authenticated student can view their own attendance history.
     */
    public function test_student_can_view_own_attendance_history(): void
    {
        $response = $this->actingAsTenant($this->bartUser, $this->greenwood)
            ->getJson('/api/student/attendance');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'summary' => ['total_school_days', 'present_days', 'late_days', 'absent_days', 'attendance_percentage'],
                    'recent_records',
                ],
            ]);
    }

    /**
     * Parent Read-Only View:
     * Parent can view attendance history for their linked child, but cannot view unlinked student.
     */
    public function test_parent_can_view_linked_child_attendance_but_not_unlinked(): void
    {
        // Homer views Bart's attendance (linked) -> 200 OK
        $bartRes = $this->actingAsTenant($this->homerParent, $this->greenwood)
            ->getJson("/api/parent/children/{$this->bartStudent->id}/attendance");

        $bartRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.present_days', 2);

        // Homer attempts to view Milhouse's attendance (unlinked) -> 403 Forbidden
        $milhouseRes = $this->actingAsTenant($this->homerParent, $this->greenwood)
            ->getJson("/api/parent/children/{$this->milhouseStudent->id}/attendance");

        $milhouseRes->assertStatus(403);
    }

    /**
     * Closed Academic Year Guard:
     * Cannot record attendance in a closed academic year.
     */
    public function test_cannot_record_attendance_in_closed_academic_year(): void
    {
        // Fetch existing section in closed academic year
        $closedSection = Section::withoutGlobalScopes()
            ->where('school_id', $this->greenwood->id)
            ->where('academic_year_id', $this->closedYear->id)
            ->firstOrFail();

        $response = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson("/api/sections/{$closedSection->id}/attendance", [
                'date' => '2024-10-10',
                'default_status' => 'present',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'CLOSED_ACADEMIC_YEAR');
    }

    /**
     * Multi-Tenant Isolation:
     * Greenwood teacher cannot access Oakridge section attendance.
     */
    public function test_cross_tenant_isolation_on_attendance(): void
    {
        $oakSection = Section::withoutGlobalScopes()
            ->where('school_id', $this->oakridge->id)
            ->firstOrFail();

        $response = $this->actingAsTenant($this->ednaTeacher, $this->greenwood)
            ->getJson("/api/sections/{$oakSection->id}/attendance");

        $this->assertContains($response->status(), [403, 404]);
    }
}
