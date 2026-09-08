<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Models\User;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\AttendanceSeeder;
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

class TransportManagementTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected School $oakridge;
    protected User $greenwoodAdmin;
    protected User $ednaTeacher;
    protected User $homerParent;
    protected User $bartUser;
    protected Student $bartStudent;
    protected Student $lisaStudent;
    protected Student $milhouseStudent;
    protected Section $section5A;
    protected User $oakridgeAdmin;
    protected TransportRoute $route101;
    protected TransportStop $stop1;
    protected TransportStop $stop2;

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
        ]);

        $this->greenwood = School::where('subdomain', 'greenwood')->firstOrFail();
        $this->oakridge = School::where('subdomain', 'oakridge')->firstOrFail();

        app(\App\Tenancy\TenantManager::class)->setTenant($this->greenwood);

        $this->greenwoodAdmin = User::where('email', 'admin@greenwood.edu')->firstOrFail();
        $this->ednaTeacher = User::where('email', 'teacher@greenwood.edu')->firstOrFail();
        $this->homerParent = User::where('email', 'parent@greenwood.edu')->firstOrFail();

        $this->bartStudent = Student::where('admission_number', 'GRE-25-00101')->firstOrFail();
        $this->bartUser = $this->bartStudent->user;
        $this->lisaStudent = Student::where('admission_number', 'GRE-25-00102')->firstOrFail();
        $this->milhouseStudent = Student::where('admission_number', 'GRE-25-00103')->firstOrFail();

        $year2025 = AcademicYear::where('school_id', $this->greenwood->id)->where('name', '2025/2026')->firstOrFail();
        $this->section5A = Section::where('school_id', $this->greenwood->id)
            ->where('academic_year_id', $year2025->id)
            ->where('name', 'Section A')
            ->firstOrFail();

        $this->oakridgeAdmin = User::where('email', 'admin@oakridge.edu')->firstOrFail();

        $this->route101 = TransportRoute::where('school_id', $this->greenwood->id)
            ->where('name', 'Route 101 - North Springfield Express')
            ->firstOrFail();

        $this->stop1 = TransportStop::where('transport_route_id', $this->route101->id)
            ->where('sequence', 1)
            ->firstOrFail();

        $this->stop2 = TransportStop::where('transport_route_id', $this->route101->id)
            ->where('sequence', 2)
            ->firstOrFail();
    }

    /**
     * Acceptance Criterion 1 (Part A):
     * Admin can build a route with ordered stops.
     */
    public function test_admin_can_build_route_with_ordered_stops(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        $payload = [
            'name' => 'Route 103 - West Springfield Line',
            'vehicle_info' => 'Bus #99 (License: WST-9901)',
            'driver_name' => 'Hans Moleman',
            'driver_contact' => '+1 (555) 888-9999',
            'capacity' => 28,
            'status' => 'active',
            'description' => 'Serves western districts and transit center.',
            'stops' => [
                [
                    'stop_name' => 'First Street Crossing',
                    'pickup_time' => '07:10',
                    'dropoff_time' => '15:55',
                    'sequence' => 1,
                    'landmark' => 'Post Office Corner',
                ],
                [
                    'stop_name' => 'Central Library Plaza',
                    'pickup_time' => '07:25',
                    'dropoff_time' => '15:40',
                    'sequence' => 2,
                    'landmark' => 'Fountain entrance',
                ],
                [
                    'stop_name' => 'Maple Avenue Gate',
                    'pickup_time' => '07:40',
                    'dropoff_time' => '15:25',
                    'sequence' => 3,
                    'landmark' => 'North park gate',
                ],
            ],
        ];

        $response = $this->actingAs($this->greenwoodAdmin)
            ->postJson('/api/transport/routes', $payload, $headers);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Route 103 - West Springfield Line')
            ->assertJsonPath('data.driver_name', 'Hans Moleman');

        $routeId = $response->json('data.id');

        $this->assertDatabaseHas('transport_routes', [
            'id' => $routeId,
            'name' => 'Route 103 - West Springfield Line',
            'school_id' => $this->greenwood->id,
        ]);

        $this->assertDatabaseCount('transport_stops', 3 + 3 + 2 + 2); // Initial stops + 3 new

        $this->assertDatabaseHas('transport_stops', [
            'transport_route_id' => $routeId,
            'stop_name' => 'First Street Crossing',
            'sequence' => 1,
        ]);

        $this->assertDatabaseHas('transport_stops', [
            'transport_route_id' => $routeId,
            'stop_name' => 'Central Library Plaza',
            'sequence' => 2,
        ]);

        $this->assertDatabaseHas('transport_stops', [
            'transport_route_id' => $routeId,
            'stop_name' => 'Maple Avenue Gate',
            'sequence' => 3,
        ]);
    }

    /**
     * Acceptance Criterion 1 (Part B):
     * Admin can assign a section's students in bulk.
     */
    public function test_admin_can_bulk_assign_section_students_to_route_and_stop(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // Ensure Section 5A has students
        $sectionStudentsCount = Student::where('current_section_id', $this->section5A->id)->count();
        $this->assertGreaterThan(0, $sectionStudentsCount);

        // Bulk assign all students in Grade 5-A to Route 101, Stop 2
        $response = $this->actingAs($this->greenwoodAdmin)
            ->postJson("/api/transport/sections/{$this->section5A->id}/routes/{$this->route101->id}/assign", [
                'transport_stop_id' => $this->stop2->id,
            ], $headers);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.assigned_count', $sectionStudentsCount)
            ->assertJsonPath('data.route_id', $this->route101->id)
            ->assertJsonPath('data.stop_id', $this->stop2->id);

        // Every student in section 5-A now has transport_stop_id = $this->stop2->id
        $sectionStudents = Student::where('current_section_id', $this->section5A->id)->get();
        foreach ($sectionStudents as $student) {
            $this->assertDatabaseHas('student_transport', [
                'student_id' => $student->id,
                'transport_route_id' => $this->route101->id,
                'transport_stop_id' => $this->stop2->id,
                'status' => 'active',
            ]);
        }
    }

    /**
     * Acceptance Criterion 1 (Part C):
     * Admin can assign students individually and unassign them.
     */
    public function test_admin_can_assign_and_unassign_individual_student(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // Assign Milhouse to Route 101, Stop 1
        $response = $this->actingAs($this->greenwoodAdmin)
            ->postJson('/api/transport/assignments', [
                'student_id' => $this->milhouseStudent->id,
                'transport_route_id' => $this->route101->id,
                'transport_stop_id' => $this->stop1->id,
                'notes' => 'Milhouse gets on at Evergreen Terrace',
            ], $headers);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student_id', $this->milhouseStudent->id)
            ->assertJsonPath('data.transport_route_id', $this->route101->id)
            ->assertJsonPath('data.transport_stop_id', $this->stop1->id);

        $this->assertDatabaseHas('student_transport', [
            'student_id' => $this->milhouseStudent->id,
            'transport_route_id' => $this->route101->id,
            'transport_stop_id' => $this->stop1->id,
            'status' => 'active',
        ]);

        // Unassign Milhouse
        $unassignResponse = $this->actingAs($this->greenwoodAdmin)
            ->deleteJson("/api/transport/students/{$this->milhouseStudent->id}/assignment", [], $headers);

        $unassignResponse->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('student_transport', [
            'student_id' => $this->milhouseStudent->id,
        ]);
    }

    /**
     * Acceptance Criterion 2 (Part A):
     * Parent sees pickup/dropoff time and stop name for their child.
     */
    public function test_parent_sees_pickup_dropoff_time_and_stop_name_for_child(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // Homer is linked to Bart Simpson.
        // In TransportSeeder, Bart is assigned to Route 101, Stop 1 ("Evergreen Terrace & Maple St", 07:15, 15:45).
        $response = $this->actingAs($this->homerParent)
            ->getJson("/api/parent/children/{$this->bartStudent->id}/transport", $headers);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_transport', true)
            ->assertJsonPath('data.stop_name', 'Evergreen Terrace & Maple St')
            ->assertJsonPath('data.pickup_time', '07:15:00')
            ->assertJsonPath('data.dropoff_time', '15:45:00')
            ->assertJsonPath('data.route_name', 'Route 101 - North Springfield Express')
            ->assertJsonPath('data.driver_name', 'Otto Mann')
            ->assertJsonPath('data.driver_contact', '+1-555-019-8765');

        // Check full stops are provided in ordered sequence
        $stops = $response->json('data.all_route_stops');
        $this->assertIsArray($stops);
        $this->assertCount(3, $stops);
        $this->assertEquals(1, $stops[0]['sequence']);
        $this->assertTrue($stops[0]['is_child_stop']);
        $this->assertFalse($stops[1]['is_child_stop']);
    }

    /**
     * Acceptance Criterion 2 (Part B):
     * Parent cannot view transport information of an unlinked student.
     */
    public function test_parent_cannot_view_transport_of_unlinked_student(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // Homer is NOT linked to Milhouse
        $response = $this->actingAs($this->homerParent)
            ->getJson("/api/parent/children/{$this->milhouseStudent->id}/transport", $headers);

        $response->assertForbidden();
    }

    /**
     * Student can see their own transport details.
     */
    public function test_student_can_view_own_transport(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        $response = $this->actingAs($this->bartUser)
            ->getJson('/api/student/transport', $headers);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_transport', true)
            ->assertJsonPath('data.stop_name', 'Evergreen Terrace & Maple St')
            ->assertJsonPath('data.pickup_time', '07:15:00')
            ->assertJsonPath('data.dropoff_time', '15:45:00');
    }

    /**
     * Non-admin roles (Teacher, Parent, Student) cannot create routes or manage stops.
     */
    public function test_non_admin_cannot_create_or_modify_transport_routes(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        $payload = [
            'name' => 'Unauthorized Route',
            'vehicle_info' => 'Van #1',
            'driver_name' => 'John Doe',
            'driver_contact' => '1234567890',
            'capacity' => 10,
        ];

        // Teacher
        $this->actingAs($this->ednaTeacher)
            ->postJson('/api/transport/routes', $payload, $headers)
            ->assertForbidden();

        // Parent
        $this->actingAs($this->homerParent)
            ->postJson('/api/transport/routes', $payload, $headers)
            ->assertForbidden();

        // Student
        $this->actingAs($this->bartUser)
            ->postJson('/api/transport/routes', $payload, $headers)
            ->assertForbidden();
    }

    /**
     * Stop must belong to the assigned route.
     */
    public function test_assigning_student_to_stop_of_different_route_fails_validation(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        $route102 = TransportRoute::where('school_id', $this->greenwood->id)
            ->where('name', 'Route 102 - South Hills & Valley Shuttle')
            ->firstOrFail();

        // Try assigning Milhouse with Route 101 but Stop from Route 102
        $stopFromRoute102 = TransportStop::where('transport_route_id', $route102->id)->firstOrFail();

        $response = $this->actingAs($this->greenwoodAdmin)
            ->postJson('/api/transport/assignments', [
                'student_id' => $this->milhouseStudent->id,
                'transport_route_id' => $this->route101->id,
                'transport_stop_id' => $stopFromRoute102->id,
            ], $headers);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    /**
     * Cross-tenant isolation: Admin from Oakridge cannot view Greenwood routes or edit them.
     */
    public function test_cross_tenant_isolation_on_transport(): void
    {
        // Oakridge admin trying to update Greenwood route
        $response = $this->actingAs($this->oakridgeAdmin)
            ->putJson("/api/transport/routes/{$this->route101->id}", [
                'name' => 'Hacked Route Name',
                'vehicle_info' => 'Hacked',
                'driver_name' => 'Hacker',
                'driver_contact' => '0000000000',
                'capacity' => 10,
            ], ['X-School-Id' => $this->oakridge->id]);

        $this->assertTrue(in_array($response->status(), [403, 404]));

        // Route name should remain unchanged
        $this->route101->refresh();
        $this->assertEquals('Route 101 - North Springfield Express', $this->route101->name);
    }
}
