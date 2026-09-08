<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\AttendanceSeeder;
use Database\Seeders\GradingSeeder;
use Database\Seeders\ParentSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SchoolSeeder;
use Database\Seeders\StaffSeeder;
use Database\Seeders\StudentSeeder;
use Database\Seeders\TimetableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableAndSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected School $oakridge;
    protected User $greenwoodAdmin;
    protected User $ednaTeacher;
    protected User $hooverTeacher;
    protected User $homerParent;
    protected Student $bartStudent;
    protected Student $lisaStudent;
    protected Student $milhouseStudent;
    protected Section $sectionA;
    protected Section $sectionB;
    protected AcademicYear $year2025;
    protected Subject $mathSubject;
    protected Subject $engSubject;
    protected Subject $sciSubject;
    protected User $oakridgeTeacher;

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
        ]);

        $this->greenwood = School::where('subdomain', 'greenwood')->firstOrFail();
        $this->oakridge = School::where('subdomain', 'oakridge')->firstOrFail();

        app(\App\Tenancy\TenantManager::class)->setTenant($this->greenwood);

        $this->greenwoodAdmin = User::where('email', 'admin@greenwood.edu')->firstOrFail();
        $this->ednaTeacher = User::where('email', 'teacher@greenwood.edu')->firstOrFail();
        $this->hooverTeacher = User::where('email', 'hoover@greenwood.edu')->firstOrFail();
        $this->homerParent = User::where('email', 'parent@greenwood.edu')->firstOrFail();

        $this->bartStudent = Student::where('admission_number', 'GRE-25-00101')->firstOrFail();
        $this->lisaStudent = Student::where('admission_number', 'GRE-25-00102')->firstOrFail();
        $this->milhouseStudent = Student::where('admission_number', 'GRE-25-00103')->firstOrFail();

        $this->year2025 = AcademicYear::where('school_id', $this->greenwood->id)->where('name', '2025/2026')->firstOrFail();
        $this->sectionA = Section::where('school_id', $this->greenwood->id)->where('academic_year_id', $this->year2025->id)->where('name', 'Section A')->firstOrFail();
        $this->sectionB = Section::where('school_id', $this->greenwood->id)->where('academic_year_id', $this->year2025->id)->where('name', 'Section B')->firstOrFail();

        $this->mathSubject = Subject::where('school_id', $this->greenwood->id)->where('code', 'MTH-901')->firstOrFail();
        $this->engSubject = Subject::where('school_id', $this->greenwood->id)->where('code', 'ENG-901')->firstOrFail();
        $this->sciSubject = Subject::where('school_id', $this->greenwood->id)->where('code', 'SCI-901')->firstOrFail();

        $this->oakridgeTeacher = User::where('email', 'teacher@oakridge.edu')->firstOrFail();
    }

    /**
     * Acceptance Criterion 1: Admin builds timetable slot;
     * conflicting teacher assignments are blocked with a clear error (HTTP 422).
     */
    public function test_conflicting_teacher_assignment_is_blocked_with_clear_error(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // In seeded data, Edna teaches Section A on Monday during Period 1 (08:00 - 08:50)
        $existingSlot = TimetableSlot::where('section_id', $this->sectionA->id)
            ->where('day_of_week', 'monday')
            ->where('period_number', 1)
            ->where('teacher_id', $this->ednaTeacher->id)
            ->firstOrFail();

        $this->assertNotNull($existingSlot);

        // Clear any existing slot in Section B at Monday Period 1 so Section Conflict does not fire
        TimetableSlot::where('section_id', $this->sectionB->id)
            ->where('day_of_week', 'monday')
            ->where('period_number', 1)
            ->delete();

        // Attempt to double-book Edna in Section B at the exact same Monday Period 1
        $response = $this->actingAs($this->greenwoodAdmin)
            ->postJson("/api/sections/{$this->sectionB->id}/timetable", [
                'subject_id' => $this->engSubject->id,
                'teacher_id' => $this->ednaTeacher->id,
                'day_of_week' => 'monday',
                'period_number' => 1,
                'room' => 'Room 102',
            ], $headers);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'TIMETABLE_CONFLICT');
        
        // Assert clear and descriptive conflict message
        $message = $response->json('error.message');
        $this->assertStringContainsString('Edna Krabappel', $message);
        $this->assertStringContainsString('Section A', $message);
        $this->assertStringContainsString('Monday', $message);
        $this->assertStringContainsString('Period 1', $message);

        // Assert conflict details are provided in response payload
        $this->assertEquals('teacher', $response->json('error.details.conflict_type'));
        $this->assertEquals($this->ednaTeacher->id, $response->json('error.details.teacher_id'));
        $this->assertEquals($this->sectionA->id, $response->json('error.details.conflicting_section_id'));
    }

    /**
     * Conflict detection: Section cannot have two subjects scheduled at the same slot.
     */
    public function test_section_double_booking_conflict_is_blocked(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // In Section A, Monday Period 1 is already English
        $response = $this->actingAs($this->greenwoodAdmin)
            ->postJson("/api/sections/{$this->sectionA->id}/timetable", [
                'subject_id' => $this->mathSubject->id,
                'teacher_id' => $this->hooverTeacher->id,
                'day_of_week' => 'monday',
                'period_number' => 1,
                'room' => 'Room 101',
            ], $headers);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'TIMETABLE_CONFLICT');
        $this->assertStringContainsString('already has English Literature 9 scheduled on Monday during Period 1', $response->json('error.message'));
    }

    /**
     * Conflict detection: Room cannot be double-booked by two different sections.
     */
    public function test_room_double_booking_conflict_is_blocked(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // Ensure Section B has no slot at Monday Period 1 so Section Conflict does not fire
        TimetableSlot::where('section_id', $this->sectionB->id)
            ->where('day_of_week', 'monday')
            ->where('period_number', 1)
            ->delete();

        // In Section A, Monday Period 1 uses Room 101
        // Attempt to book Room 101 for Section B at the same Monday Period 1 (with Hoover, who is free)
        $response = $this->actingAs($this->greenwoodAdmin)
            ->postJson("/api/sections/{$this->sectionB->id}/timetable", [
                'subject_id' => $this->mathSubject->id,
                'teacher_id' => $this->hooverTeacher->id,
                'day_of_week' => 'monday',
                'period_number' => 1,
                'room' => 'Room 101',
            ], $headers);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'TIMETABLE_CONFLICT');
        $this->assertStringContainsString("Room 'Room 101' is already booked", $response->json('error.message'));
    }

    /**
     * Acceptance Criterion 2: A teacher's personal timetable correctly aggregates
     * slots across every section they teach.
     */
    public function test_teacher_personal_timetable_aggregates_across_every_section_they_teach(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // Fetch personal timetable for Edna Krabappel
        $response = $this->actingAs($this->ednaTeacher)
            ->getJson('/api/teacher/timetable', $headers);

        $response->assertStatus(200);
        $data = $response->json('data');

        // Edna teaches in both Section A and Section B
        $this->assertCount(2, $data['sections_taught']);
        $sectionNames = collect($data['sections_taught'])->pluck('name')->all();
        $this->assertContains('Section A', $sectionNames);
        $this->assertContains('Section B', $sectionNames);

        // Verify total periods aggregated
        $this->assertGreaterThanOrEqual(10, $data['stats']['total_weekly_periods']);

        // Check slots include both Section A and Section B slots
        $slotSectionIds = collect($data['slots'])->pluck('section_id')->unique()->all();
        $this->assertContains($this->sectionA->id, $slotSectionIds);
        $this->assertContains($this->sectionB->id, $slotSectionIds);

        // Also test admin querying teacher's timetable via route
        $adminResponse = $this->actingAs($this->greenwoodAdmin)
            ->getJson("/api/teachers/{$this->ednaTeacher->id}/timetable", $headers);

        $adminResponse->assertStatus(200);
        $this->assertEquals($data['stats']['total_weekly_periods'], $adminResponse->json('data.stats.total_weekly_periods'));
    }

    /**
     * Admin can build full week's timetable via single slot creation and batch builder.
     */
    public function test_admin_can_create_and_batch_build_section_timetable(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // 1. Single Slot Creation
        $response = $this->actingAs($this->greenwoodAdmin)
            ->postJson("/api/sections/{$this->sectionA->id}/timetable", [
                'subject_id' => $this->mathSubject->id,
                'teacher_id' => $this->hooverTeacher->id,
                'day_of_week' => 'friday',
                'period_number' => 3,
                'room' => 'Room 101',
            ], $headers);

        $response->assertStatus(201);
        $slotId = $response->json('data.id');
        $this->assertDatabaseHas('timetable_slots', [
            'id' => $slotId,
            'section_id' => $this->sectionA->id,
            'day_of_week' => 'friday',
            'period_number' => 3,
        ]);

        // 2. Batch Slot Creation for a new Section
        $newSec = Section::create([
            'school_id' => $this->greenwood->id,
            'academic_year_id' => $this->year2025->id,
            'grade_level_id' => $this->sectionA->grade_level_id,
            'name' => 'Section C',
            'capacity' => 25,
        ]);

        $batchResponse = $this->actingAs($this->greenwoodAdmin)
            ->postJson("/api/sections/{$newSec->id}/timetable/batch", [
                'replace_existing' => true,
                'slots' => [
                    [
                        'subject_id' => $this->mathSubject->id,
                        'teacher_id' => $this->hooverTeacher->id,
                        'day_of_week' => 'monday',
                        'period_number' => 5, // Free period for Hoover
                        'room' => 'Room 103',
                    ],
                    [
                        'subject_id' => $this->engSubject->id,
                        'teacher_id' => $this->ednaTeacher->id,
                        'day_of_week' => 'monday',
                        'period_number' => 6, // Free period for Edna
                        'room' => 'Room 103',
                    ],
                ],
            ], $headers);

        $batchResponse->assertStatus(201);
        $this->assertEquals(2, $batchResponse->json('data.count'));
    }

    /**
     * Section timetable returns structured 2D weekly matrix grid.
     */
    public function test_section_timetable_returns_structured_weekly_grid(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        $response = $this->actingAs($this->greenwoodAdmin)
            ->getJson("/api/sections/{$this->sectionA->id}/timetable", $headers);

        $response->assertStatus(200);
        $grid = $response->json('data.grid');

        $this->assertArrayHasKey('monday', $grid);
        $this->assertArrayHasKey('tuesday', $grid);
        $this->assertArrayHasKey('wednesday', $grid);
        $this->assertArrayHasKey('thursday', $grid);
        $this->assertArrayHasKey('friday', $grid);

        // Monday Period 1 is English
        $this->assertNotNull($grid['monday'][1]);
        $this->assertEquals('English Literature 9', $grid['monday'][1]['subject']['name']);
        $this->assertEquals('Edna Krabappel', $grid['monday'][1]['teacher']['name']);
    }

    /**
     * Student can view their own section's weekly timetable.
     */
    public function test_student_can_view_own_section_timetable(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];
        $bartUser = $this->bartStudent->user;

        $response = $this->actingAs($bartUser)
            ->getJson('/api/student/timetable', $headers);

        $response->assertStatus(200);
        $this->assertEquals($this->sectionA->id, $response->json('data.section.id'));
        $this->assertNotEmpty($response->json('data.slots'));
    }

    /**
     * Parent can view linked child's section timetable, but cannot view unlinked child's timetable.
     */
    public function test_parent_can_view_linked_child_timetable_but_not_unlinked(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // Homer is linked to Bart
        $response = $this->actingAs($this->homerParent)
            ->getJson("/api/parent/children/{$this->bartStudent->id}/timetable", $headers);

        $response->assertStatus(200);
        $this->assertEquals($this->sectionA->id, $response->json('data.section.id'));

        // Homer is NOT linked to Milhouse -> 403 Forbidden
        $unlinkedResponse = $this->actingAs($this->homerParent)
            ->getJson("/api/parent/children/{$this->milhouseStudent->id}/timetable", $headers);

        $unlinkedResponse->assertStatus(403);
    }

    /**
     * Closed Academic Year Guard: Cannot create or update timetable in closed academic year.
     */
    public function test_cannot_modify_timetable_in_closed_academic_year(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        $year2024 = AcademicYear::where('school_id', $this->greenwood->id)->where('name', '2024/2025')->firstOrFail();
        $year2024->update(['is_closed' => true]);

        $histSection = Section::where('school_id', $this->greenwood->id)->where('academic_year_id', $year2024->id)->firstOrFail();

        $response = $this->actingAs($this->greenwoodAdmin)
            ->postJson("/api/sections/{$histSection->id}/timetable", [
                'subject_id' => $this->mathSubject->id,
                'teacher_id' => $this->hooverTeacher->id,
                'day_of_week' => 'monday',
                'period_number' => 1,
            ], $headers);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'CLOSED_ACADEMIC_YEAR');
    }

    /**
     * Cross-tenant isolation: User from School A cannot access School B's timetable.
     */
    public function test_cross_tenant_isolation_on_timetable(): void
    {
        $slot = TimetableSlot::where('section_id', $this->sectionA->id)->firstOrFail();

        // Oakridge teacher attempting to access Greenwood slot
        $response = $this->actingAs($this->oakridgeTeacher)
            ->deleteJson("/api/timetable-slots/{$slot->id}", [], ['X-School-Id' => $this->oakridge->id]);

        $this->assertContains($response->status(), [403, 404]);
    }
}
