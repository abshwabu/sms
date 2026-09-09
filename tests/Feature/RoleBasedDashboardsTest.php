<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Enums\RoleEnum;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Tenancy\TenantManager;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\AttendanceSeeder;
use Database\Seeders\CommunicationSeeder;
use Database\Seeders\GradingSeeder;
use Database\Seeders\LibrarySeeder;
use Database\Seeders\ParentSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SchoolSeeder;
use Database\Seeders\StaffSeeder;
use Database\Seeders\StudentSeeder;
use Database\Seeders\TimetableSeeder;
use Database\Seeders\TransportSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBasedDashboardsTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected School $oakridge;
    protected User $superAdmin;
    protected User $schoolAdmin;
    protected User $teacher;
    protected User $student;
    protected User $parent;

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
            GradingSeeder::class,
            TimetableSeeder::class,
            LibrarySeeder::class,
            TransportSeeder::class,
            CommunicationSeeder::class,
        ]);

        $this->greenwood = School::where('subdomain', 'greenwood')->firstOrFail();
        $this->oakridge = School::where('subdomain', 'oakridge')->firstOrFail();

        app(TenantManager::class)->setTenant($this->greenwood);

        $this->superAdmin = User::where('email', 'superadmin@bina.test')->firstOrFail();
        $this->schoolAdmin = User::where('email', 'admin@greenwood.edu')->firstOrFail();
        $this->teacher = User::where('email', 'teacher@greenwood.edu')->firstOrFail();
        $this->student = User::where('email', 'bart.simpson@greenwood.edu')->firstOrFail();
        $this->parent = User::where('email', 'parent@greenwood.edu')->firstOrFail();
    }

    public function test_super_admin_cross_school_overview(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.role', 'super_admin')
            ->assertJsonStructure([
                'data' => [
                    'role',
                    'title',
                    'summary_metrics' => [
                        'total_schools',
                        'active_schools',
                        'total_students',
                        'total_staff',
                        'total_sections',
                        'total_courses',
                    ],
                    'schools',
                    'system_health' => [
                        'db_connected',
                        'multi_tenant_isolation',
                    ],
                ],
            ]);

        $this->assertGreaterThanOrEqual(2, $response->json('data.summary_metrics.total_schools'));
        $this->assertGreaterThan(0, $response->json('data.summary_metrics.total_students'));
    }

    public function test_non_admin_cannot_access_super_admin_role_dashboard(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        $response = $this->actingAs($this->student)
            ->getJson('/api/dashboard?role=super_admin', $headers);

        $response->assertForbidden();
    }

    public function test_school_admin_dashboard_metrics_and_trends(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        $response = $this->actingAs($this->schoolAdmin)
            ->getJson('/api/dashboard', $headers);

        $response->assertOk()
            ->assertJsonPath('data.role', 'school_admin')
            ->assertJsonPath('data.school.id', $this->greenwood->id)
            ->assertJsonStructure([
                'data' => [
                    'enrollment_stats' => [
                        'total_students',
                        'total_staff',
                        'total_sections',
                        'total_capacity',
                        'capacity_utilized_percent',
                        'grade_breakdown',
                    ],
                    'attendance_trends' => [
                        'today',
                        'today_rate',
                        'breakdown',
                        'daily_history',
                    ],
                    'pending_actions' => [
                        'overdue_loans_count',
                        'unassigned_students_count',
                    ],
                    'recent_announcements',
                ],
            ]);

        $this->assertGreaterThan(0, $response->json('data.enrollment_stats.total_students'));
        $this->assertGreaterThan(0, $response->json('data.enrollment_stats.total_sections'));
    }

    public function test_teacher_dashboard_attendance_and_personal_timetable(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];
        $testDay = DayOfWeek::MONDAY->value;

        $response = $this->actingAs($this->teacher)
            ->getJson("/api/dashboard?day={$testDay}", $headers);

        $response->assertOk()
            ->assertJsonPath('data.role', 'teacher')
            ->assertJsonStructure([
                'data' => [
                    'teacher_profile' => [
                        'name',
                        'staff_number',
                        'role_title',
                    ],
                    'attendance_sections',
                    'today_timetable' => [
                        'day_of_week',
                        'slots',
                    ],
                    'pending_grade_entry',
                    'recent_messages',
                ],
            ]);

        $this->assertEquals($testDay, $response->json('data.today_timetable.day_of_week'));
    }

    public function test_student_dashboard_timetable_grades_and_loans(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];
        $testDay = DayOfWeek::MONDAY->value;

        $response = $this->actingAs($this->student)
            ->getJson("/api/dashboard?day={$testDay}", $headers);

        $response->assertOk()
            ->assertJsonPath('data.role', 'student')
            ->assertJsonPath('data.student_profile.name', 'Bart Simpson')
            ->assertJsonStructure([
                'data' => [
                    'student_profile' => [
                        'admission_number',
                        'section_name',
                        'grade_level',
                    ],
                    'today_timetable' => [
                        'day_of_week',
                        'slots',
                    ],
                    'recent_grades',
                    'attendance_summary',
                    'library_loans',
                    'announcements',
                ],
            ]);
    }

    public function test_parent_dashboard_multi_child_switcher_and_transport(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // 1. Initial login: Homer sees children switcher and defaults to first child (Bart)
        $response = $this->actingAs($this->parent)
            ->getJson('/api/dashboard', $headers);

        $response->assertOk()
            ->assertJsonPath('data.role', 'parent')
            ->assertJsonStructure([
                'data' => [
                    'children',
                    'active_child_id',
                    'child_dashboard' => [
                        'student',
                        'attendance',
                        'recent_grades',
                        'today_timetable',
                        'transport',
                        'direct_messages',
                        'announcements',
                    ],
                ],
            ]);

        $children = $response->json('data.children');
        $this->assertGreaterThanOrEqual(2, count($children));

        $firstChildId = $children[0]['id'];
        $secondChildId = $children[1]['id'];

        $this->assertEquals($firstChildId, $response->json('data.active_child_id'));

        // 2. Switch child to Lisa without relogin
        $response2 = $this->actingAs($this->parent)
            ->getJson("/api/dashboard?child_id={$secondChildId}", $headers);

        $response2->assertOk()
            ->assertJsonPath('data.active_child_id', $secondChildId);

        $this->assertEquals($children[1]['name'], $response2->json('data.child_dashboard.student.name'));
    }

    public function test_cross_tenant_isolation_on_dashboard(): void
    {
        // Oakridge admin should only see Oakridge data
        $oakridgeAdmin = User::where('email', 'admin@oakridge.edu')->firstOrFail();
        $headers = ['X-School-Id' => $this->oakridge->id];

        $response = $this->actingAs($oakridgeAdmin)
            ->getJson('/api/dashboard', $headers);

        $response->assertOk()
            ->assertJsonPath('data.role', 'school_admin')
            ->assertJsonPath('data.school.id', $this->oakridge->id)
            ->assertJsonPath('data.school.name', 'Oakridge Academy');
    }

    public function test_user_cannot_access_other_school_dashboard(): void
    {
        // Oakridge admin attempts to pass Greenwood's ID in header
        $oakridgeAdmin = User::where('email', 'admin@oakridge.edu')->firstOrFail();
        $headers = ['X-School-Id' => $this->greenwood->id];

        $response = $this->actingAs($oakridgeAdmin)
            ->getJson('/api/dashboard', $headers);

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN_TENANT_ACCESS');
    }

    public function test_non_super_admin_cannot_switch_to_other_roles(): void
    {
        // School admin attempts to switch to teacher dashboard
        $headers = ['X-School-Id' => $this->greenwood->id];

        $response = $this->actingAs($this->schoolAdmin)
            ->getJson('/api/dashboard?role=teacher', $headers);

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN_DASHBOARD_ROLE');
    }
}
