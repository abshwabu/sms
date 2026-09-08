<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\GradeLevel;
use App\Models\GradingScale;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentSubjectSelection;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Services\ReportCardService;
use App\Services\TimetableService;
use App\Tenancy\TenantManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectiveSubjectManagementTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected School $maplewood;

    protected User $greenwoodAdmin;
    protected User $maplewoodAdmin;
    protected User $ednaTeacher;
    protected User $homerParent;
    protected User $bartUser;
    protected User $lisaUser;
    protected User $milhouseUser;

    protected Student $bartStudent;
    protected Student $lisaStudent;
    protected Student $milhouseStudent;
    protected Student $tommyStudent;

    protected AcademicYear $year2025;
    protected Term $fallTerm;
    protected GradeLevel $grade10;
    protected Section $section10A;
    protected Section $section10B;

    protected Subject $coreMath;
    protected Subject $coreEng;
    protected Subject $electiveCS;
    protected Subject $electiveArt;
    protected Subject $electiveMusic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->greenwood = School::where('subdomain', 'greenwood')->firstOrFail();
        $this->maplewood = School::where('subdomain', 'maplewood')->firstOrFail();

        $this->greenwoodAdmin = User::where('email', 'admin@greenwood.edu')->firstOrFail();
        $this->maplewoodAdmin = User::where('email', 'admin@maplewood.edu')->firstOrFail();
        $this->ednaTeacher = User::where('email', 'teacher@greenwood.edu')->firstOrFail();
        $this->homerParent = User::where('email', 'parent@greenwood.edu')->firstOrFail();

        $this->bartUser = User::where('email', 'bart.simpson@greenwood.edu')->firstOrFail();
        $this->lisaUser = User::where('email', 'lisa.simpson@greenwood.edu')->firstOrFail();
        $this->milhouseUser = User::where('email', 'milhouse@greenwood.edu')->firstOrFail();

        app(TenantManager::class)->bypass(function () {
            $this->bartStudent = Student::where('school_id', $this->greenwood->id)->where('admission_number', 'GRE-25-00101')->firstOrFail();
            $this->lisaStudent = Student::where('school_id', $this->greenwood->id)->where('admission_number', 'GRE-25-00102')->firstOrFail();
            $this->milhouseStudent = Student::where('school_id', $this->greenwood->id)->where('admission_number', 'GRE-25-00103')->firstOrFail();
            $this->tommyStudent = Student::where('school_id', $this->maplewood->id)->where('admission_number', 'MAP-25-00101')->firstOrFail();

            $this->year2025 = AcademicYear::where('school_id', $this->greenwood->id)->where('name', '2025/2026')->firstOrFail();
            $this->fallTerm = Term::where('school_id', $this->greenwood->id)->where('academic_year_id', $this->year2025->id)->firstOrFail();
            $this->grade10 = GradeLevel::where('school_id', $this->greenwood->id)->where('code', 'G10')->firstOrFail();

            // Sections
            $this->section10A = Section::firstOrCreate(
                ['school_id' => $this->greenwood->id, 'academic_year_id' => $this->year2025->id, 'grade_level_id' => $this->grade10->id, 'name' => 'Section 10-A'],
                ['capacity' => 30, 'homeroom_teacher_id' => $this->ednaTeacher->id]
            );

            $this->section10B = Section::firstOrCreate(
                ['school_id' => $this->greenwood->id, 'academic_year_id' => $this->year2025->id, 'grade_level_id' => $this->grade10->id, 'name' => 'Section 10-B'],
                ['capacity' => 30, 'homeroom_teacher_id' => $this->ednaTeacher->id]
            );

            // Assign students to Section 10-A
            $this->bartStudent->update(['current_section_id' => $this->section10A->id]);
            $this->lisaStudent->update(['current_section_id' => $this->section10A->id]);
            $this->milhouseStudent->update(['current_section_id' => $this->section10A->id]);

            // Core subjects (implicit for all students in grade)
            $this->coreMath = Subject::firstOrCreate(
                ['school_id' => $this->greenwood->id, 'grade_level_id' => $this->grade10->id, 'code' => 'G10-MTH'],
                ['name' => 'Grade 10 Algebra & Geometry', 'credit_hours' => 1.0, 'is_elective' => false]
            );

            $this->coreEng = Subject::firstOrCreate(
                ['school_id' => $this->greenwood->id, 'grade_level_id' => $this->grade10->id, 'code' => 'G10-ENG'],
                ['name' => 'Grade 10 World Literature', 'credit_hours' => 1.0, 'is_elective' => false]
            );

            // 3 Elective Subjects
            $this->electiveCS = Subject::firstOrCreate(
                ['school_id' => $this->greenwood->id, 'grade_level_id' => $this->grade10->id, 'code' => 'G10-CS'],
                ['name' => 'Computer Science & Software', 'credit_hours' => 1.0, 'is_elective' => true]
            );

            $this->electiveArt = Subject::firstOrCreate(
                ['school_id' => $this->greenwood->id, 'grade_level_id' => $this->grade10->id, 'code' => 'G10-ART'],
                ['name' => 'Studio Fine Arts', 'credit_hours' => 1.0, 'is_elective' => true]
            );

            $this->electiveMusic = Subject::firstOrCreate(
                ['school_id' => $this->greenwood->id, 'grade_level_id' => $this->grade10->id, 'code' => 'G10-MUS'],
                ['name' => 'Music Theory & Ensemble', 'credit_hours' => 1.0, 'is_elective' => true]
            );
        });
    }

    protected function actingAsTenant(User $user, School $school): static
    {
        $this->app['auth']->forgetGuards();
        app(TenantManager::class)->setTenant($school);
        $token = $user->createToken('test-elective-token')->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $school->id);
    }

    /**
     * Acceptance Criterion:
     * A Grade 10 section can offer 3 elective subjects with caps; students select one each;
     * selections lock once capacity is reached or window closes.
     */
    public function test_admin_configures_offerings_and_capacity_is_strictly_enforced(): void
    {
        // 1. Admin configures 3 elective offerings with caps
        // CS: cap 1, Art: cap 2, Music: cap 2
        $csResp = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/electives/offerings', [
                'academic_year_id' => $this->year2025->id,
                'grade_level_id' => $this->grade10->id,
                'subject_id' => $this->electiveCS->id,
                'type' => 'elective',
                'max_students' => 1,
                'enrollment_start' => now()->subDay()->toDateTimeString(),
                'enrollment_end' => now()->addDays(14)->toDateTimeString(),
                'is_open' => true,
            ]);
        $csResp->assertStatus(201);
        $csOfferingId = $csResp->json('data.id');

        $artResp = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/electives/offerings', [
                'academic_year_id' => $this->year2025->id,
                'grade_level_id' => $this->grade10->id,
                'subject_id' => $this->electiveArt->id,
                'type' => 'elective',
                'max_students' => 2,
                'enrollment_start' => now()->subDay()->toDateTimeString(),
                'enrollment_end' => now()->addDays(14)->toDateTimeString(),
                'is_open' => true,
            ]);
        $artResp->assertStatus(201);

        $musResp = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/electives/offerings', [
                'academic_year_id' => $this->year2025->id,
                'grade_level_id' => $this->grade10->id,
                'subject_id' => $this->electiveMusic->id,
                'type' => 'elective',
                'max_students' => 2,
                'enrollment_start' => now()->subDay()->toDateTimeString(),
                'enrollment_end' => now()->addDays(14)->toDateTimeString(),
                'is_open' => true,
            ]);
        $musResp->assertStatus(201);

        // Core subjects remain implicit: verify no rows in student_subject_selections for core subjects
        $this->assertDatabaseMissing('student_subject_selections', [
            'subject_id' => $this->coreMath->id,
        ]);
        $this->assertDatabaseMissing('student_subject_selections', [
            'subject_id' => $this->coreEng->id,
        ]);

        // 2. Student 1 (Bart) selects CS
        $bartSelect = $this->actingAsTenant($this->bartUser, $this->greenwood)
            ->postJson('/api/student/electives/select', [
                'academic_year_id' => $this->year2025->id,
                'subject_id' => $this->electiveCS->id,
            ]);
        $bartSelect->assertStatus(201);
        $this->assertEquals('enrolled', $bartSelect->json('data.status'));

        // 3. Student 2 (Milhouse) attempts to select CS, but capacity is capped at 1!
        $milhouseSelectFail = $this->actingAsTenant($this->milhouseUser, $this->greenwood)
            ->postJson('/api/student/electives/select', [
                'academic_year_id' => $this->year2025->id,
                'subject_id' => $this->electiveCS->id,
            ]);
        $milhouseSelectFail->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('capacity', (string) $milhouseSelectFail->json('error.details.subject_id.0'));

        // 4. Milhouse selects Art instead (capacity 2) -> succeeds
        $milhouseSelectArt = $this->actingAsTenant($this->milhouseUser, $this->greenwood)
            ->postJson('/api/student/electives/select', [
                'academic_year_id' => $this->year2025->id,
                'subject_id' => $this->electiveArt->id,
            ]);
        $milhouseSelectArt->assertStatus(201);

        // 5. Admin views offering students
        $studentsResp = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->getJson("/api/electives/offerings/{$csOfferingId}/students");
        $studentsResp->assertStatus(200);
        $this->assertEquals(1, $studentsResp->json('data.offering.enrolled_count'));
        $this->assertEquals($this->bartStudent->id, $studentsResp->json('data.students.0.student_id'));

        // 6. Bart drops CS -> capacity freed
        $bartDrop = $this->actingAsTenant($this->bartUser, $this->greenwood)
            ->postJson('/api/student/electives/drop', [
                'academic_year_id' => $this->year2025->id,
                'subject_id' => $this->electiveCS->id,
            ]);
        $bartDrop->assertStatus(200);

        // 7. Milhouse drops Art and now selects CS -> succeeds because CS seat is open
        $this->actingAsTenant($this->milhouseUser, $this->greenwood)
            ->postJson('/api/student/electives/drop', [
                'academic_year_id' => $this->year2025->id,
                'subject_id' => $this->electiveArt->id,
            ])
            ->assertStatus(200);

        $milhouseSelectCS = $this->actingAsTenant($this->milhouseUser, $this->greenwood)
            ->postJson('/api/student/electives/select', [
                'academic_year_id' => $this->year2025->id,
                'subject_id' => $this->electiveCS->id,
            ]);
        $milhouseSelectCS->assertStatus(201);
    }

    /**
     * Acceptance Criterion:
     * Selections lock when enrollment window closes.
     */
    public function test_selections_lock_when_enrollment_window_closes(): void
    {
        // 1. Offering with window in the past
        $offering = SubjectOffering::create([
            'school_id' => $this->greenwood->id,
            'academic_year_id' => $this->year2025->id,
            'grade_level_id' => $this->grade10->id,
            'subject_id' => $this->electiveMusic->id,
            'type' => 'elective',
            'max_students' => 10,
            'enrollment_start' => now()->subDays(10),
            'enrollment_end' => now()->subDays(1), // closed yesterday
            'is_open' => true,
        ]);

        // Student attempt should fail due to window closed
        $response = $this->actingAsTenant($this->lisaUser, $this->greenwood)
            ->postJson('/api/student/electives/select', [
                'academic_year_id' => $this->year2025->id,
                'subject_id' => $this->electiveMusic->id,
            ]);
        $response->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('window is closed', (string) $response->json('error.details.subject_id.0'));

        // 2. Admin extends window
        $updateResp = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->putJson("/api/electives/offerings/{$offering->id}", [
                'enrollment_end' => now()->addDays(7)->toDateTimeString(),
                'is_open' => true,
            ]);
        $updateResp->assertStatus(200);

        // Student attempts again -> succeeds
        $retryResp = $this->actingAsTenant($this->lisaUser, $this->greenwood)
            ->postJson('/api/student/electives/select', [
                'academic_year_id' => $this->year2025->id,
                'subject_id' => $this->electiveMusic->id,
            ]);
        $retryResp->assertStatus(201);

        // 3. Admin manually closes window via is_open toggle
        $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->putJson("/api/electives/offerings/{$offering->id}", [
                'is_open' => false,
            ])
            ->assertStatus(200);

        // Student attempt to drop or select blocked when closed
        $dropResp = $this->actingAsTenant($this->lisaUser, $this->greenwood)
            ->postJson('/api/student/electives/drop', [
                'academic_year_id' => $this->year2025->id,
                'subject_id' => $this->electiveMusic->id,
            ]);
        $dropResp->assertStatus(422);
    }

    /**
     * Acceptance Criterion:
     * Student report cards show core subjects + only their chosen electives
     * (not all electives offered to the grade).
     */
    public function test_student_report_card_shows_core_plus_only_chosen_electives(): void
    {
        // Setup offerings
        SubjectOffering::firstOrCreate(
            ['school_id' => $this->greenwood->id, 'academic_year_id' => $this->year2025->id, 'grade_level_id' => $this->grade10->id, 'subject_id' => $this->electiveCS->id],
            ['type' => 'elective', 'max_students' => 10, 'is_open' => true]
        );
        SubjectOffering::firstOrCreate(
            ['school_id' => $this->greenwood->id, 'academic_year_id' => $this->year2025->id, 'grade_level_id' => $this->grade10->id, 'subject_id' => $this->electiveArt->id],
            ['type' => 'elective', 'max_students' => 10, 'is_open' => true]
        );
        SubjectOffering::firstOrCreate(
            ['school_id' => $this->greenwood->id, 'academic_year_id' => $this->year2025->id, 'grade_level_id' => $this->grade10->id, 'subject_id' => $this->electiveMusic->id],
            ['type' => 'elective', 'max_students' => 10, 'is_open' => true]
        );

        // Bart selects CS
        StudentSubjectSelection::updateOrCreate(
            ['school_id' => $this->greenwood->id, 'student_id' => $this->bartStudent->id, 'academic_year_id' => $this->year2025->id, 'subject_id' => $this->electiveCS->id],
            ['status' => 'enrolled', 'selected_at' => now(), 'selected_by' => $this->bartUser->id]
        );

        // Lisa selects Music
        StudentSubjectSelection::updateOrCreate(
            ['school_id' => $this->greenwood->id, 'student_id' => $this->lisaStudent->id, 'academic_year_id' => $this->year2025->id, 'subject_id' => $this->electiveMusic->id],
            ['status' => 'enrolled', 'selected_at' => now(), 'selected_by' => $this->lisaUser->id]
        );

        // Create an Exam for Fall Term
        $exam = Exam::firstOrCreate([
            'school_id' => $this->greenwood->id,
            'academic_year_id' => $this->year2025->id,
            'term_id' => $this->fallTerm->id,
            'grade_level_id' => $this->grade10->id,
            'name' => 'Elective Term Assessment',
        ], [
            'type' => 'midterm',
            'weight' => 1.0,
            'max_marks' => 100.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        // Record grades for Core subjects
        Grade::create([
            'school_id' => $this->greenwood->id,
            'student_id' => $this->bartStudent->id,
            'subject_id' => $this->coreMath->id,
            'exam_id' => $exam->id,
            'section_id' => $this->section10A->id,
            'marks_obtained' => 88.0,
            'max_marks' => 100.0,
            'entered_by' => $this->ednaTeacher->id,
        ]);

        Grade::create([
            'school_id' => $this->greenwood->id,
            'student_id' => $this->bartStudent->id,
            'subject_id' => $this->coreEng->id,
            'exam_id' => $exam->id,
            'section_id' => $this->section10A->id,
            'marks_obtained' => 92.0,
            'max_marks' => 100.0,
            'entered_by' => $this->ednaTeacher->id,
        ]);

        // Record grades for Bart in CS
        Grade::create([
            'school_id' => $this->greenwood->id,
            'student_id' => $this->bartStudent->id,
            'subject_id' => $this->electiveCS->id,
            'exam_id' => $exam->id,
            'section_id' => $this->section10A->id,
            'marks_obtained' => 95.0,
            'max_marks' => 100.0,
            'entered_by' => $this->ednaTeacher->id,
        ]);

        // Also record a grade for Lisa in Music
        Grade::create([
            'school_id' => $this->greenwood->id,
            'student_id' => $this->lisaStudent->id,
            'subject_id' => $this->coreMath->id,
            'exam_id' => $exam->id,
            'section_id' => $this->section10A->id,
            'marks_obtained' => 98.0,
            'max_marks' => 100.0,
            'entered_by' => $this->ednaTeacher->id,
        ]);

        Grade::create([
            'school_id' => $this->greenwood->id,
            'student_id' => $this->lisaStudent->id,
            'subject_id' => $this->electiveMusic->id,
            'exam_id' => $exam->id,
            'section_id' => $this->section10A->id,
            'marks_obtained' => 100.0,
            'max_marks' => 100.0,
            'entered_by' => $this->ednaTeacher->id,
        ]);

        // Aggregate report cards
        $reportCardService = app(ReportCardService::class);
        $gradingScale = GradingScale::getDefaultScale($this->greenwood->id);

        $bartReportCard = $reportCardService->aggregateStudentReportCard($this->bartStudent, $this->fallTerm, $gradingScale);
        $lisaReportCard = $reportCardService->aggregateStudentReportCard($this->lisaStudent, $this->fallTerm, $gradingScale);

        // Verify Bart's report card
        $bartItems = $bartReportCard->items()->with('subject')->get();
        $bartSubjectCodes = $bartItems->pluck('subject.code')->all();

        // Must include Core subjects
        $this->assertContains('G10-MTH', $bartSubjectCodes);
        $this->assertContains('G10-ENG', $bartSubjectCodes);
        // Must include selected elective CS
        $this->assertContains('G10-CS', $bartSubjectCodes);
        // Must NOT include unselected electives (Art, Music)
        $this->assertNotContains('G10-ART', $bartSubjectCodes);
        $this->assertNotContains('G10-MUS', $bartSubjectCodes);

        // Verify Lisa's report card
        $lisaItems = $lisaReportCard->items()->with('subject')->get();
        $lisaSubjectCodes = $lisaItems->pluck('subject.code')->all();

        $this->assertContains('G10-MTH', $lisaSubjectCodes);
        $this->assertContains('G10-MUS', $lisaSubjectCodes);
        // Must NOT include CS or Art
        $this->assertNotContains('G10-CS', $lisaSubjectCodes);
        $this->assertNotContains('G10-ART', $lisaSubjectCodes);
    }

    /**
     * Acceptance Criterion:
     * Student personal timetables correctly merge section core slots with chosen elective slots,
     * even across sections.
     */
    public function test_student_personal_timetable_merges_core_and_cross_section_electives(): void
    {
        // 1. Clear existing slots for clean testing
        TimetableSlot::withoutGlobalScopes()
            ->where('school_id', $this->greenwood->id)
            ->whereIn('section_id', [$this->section10A->id, $this->section10B->id])
            ->delete();

        // 2. Schedule Core Math and English in Section 10-A
        TimetableSlot::create([
            'school_id' => $this->greenwood->id,
            'academic_year_id' => $this->year2025->id,
            'section_id' => $this->section10A->id,
            'subject_id' => $this->coreMath->id,
            'teacher_id' => $this->ednaTeacher->id,
            'day_of_week' => DayOfWeek::MONDAY->value,
            'period_number' => 1,
            'start_time' => '08:00',
            'end_time' => '08:50',
            'room' => 'Room 101',
        ]);

        TimetableSlot::create([
            'school_id' => $this->greenwood->id,
            'academic_year_id' => $this->year2025->id,
            'section_id' => $this->section10A->id,
            'subject_id' => $this->coreEng->id,
            'teacher_id' => $this->ednaTeacher->id,
            'day_of_week' => DayOfWeek::MONDAY->value,
            'period_number' => 2,
            'start_time' => '09:00',
            'end_time' => '09:50',
            'room' => 'Room 101',
        ]);

        // 3. Section 10-A has elective slot for Computer Science on Monday Period 3
        TimetableSlot::create([
            'school_id' => $this->greenwood->id,
            'academic_year_id' => $this->year2025->id,
            'section_id' => $this->section10A->id,
            'subject_id' => $this->electiveCS->id,
            'teacher_id' => $this->ednaTeacher->id,
            'day_of_week' => DayOfWeek::MONDAY->value,
            'period_number' => 3,
            'start_time' => '10:00',
            'end_time' => '10:50',
            'room' => 'Lab 1',
        ]);

        // 4. Section 10-B (different section!) has elective slot for Music Theory on Monday Period 3
        TimetableSlot::create([
            'school_id' => $this->greenwood->id,
            'academic_year_id' => $this->year2025->id,
            'section_id' => $this->section10B->id,
            'subject_id' => $this->electiveMusic->id,
            'teacher_id' => $this->ednaTeacher->id,
            'day_of_week' => DayOfWeek::MONDAY->value,
            'period_number' => 3,
            'start_time' => '10:00',
            'end_time' => '10:50',
            'room' => 'Music Hall',
        ]);

        // 5. Enrollments:
        // Bart is in Section 10-A and selects CS (held in Section 10-A)
        StudentSubjectSelection::updateOrCreate(
            ['school_id' => $this->greenwood->id, 'student_id' => $this->bartStudent->id, 'academic_year_id' => $this->year2025->id, 'subject_id' => $this->electiveCS->id],
            ['status' => 'enrolled', 'selected_at' => now(), 'selected_by' => $this->bartUser->id]
        );

        // Lisa is ALSO in Section 10-A, but selects Music (held in Section 10-B!)
        StudentSubjectSelection::updateOrCreate(
            ['school_id' => $this->greenwood->id, 'student_id' => $this->lisaStudent->id, 'academic_year_id' => $this->year2025->id, 'subject_id' => $this->electiveMusic->id],
            ['status' => 'enrolled', 'selected_at' => now(), 'selected_by' => $this->lisaUser->id]
        );

        // 6. Query Bart's timetable
        $timetableService = app(TimetableService::class);
        $bartTimetable = $timetableService->getStudentTimetable($this->bartStudent, $this->year2025->id);

        $bartSlotSubjects = collect($bartTimetable['slots'])->pluck('subject.code')->all();
        $this->assertContains('G10-MTH', $bartSlotSubjects);
        $this->assertContains('G10-ENG', $bartSlotSubjects);
        $this->assertContains('G10-CS', $bartSlotSubjects);
        $this->assertNotContains('G10-MUS', $bartSlotSubjects);

        // 7. Query Lisa's timetable:
        // Section 10-A's CS slot must be excluded, and Section 10-B's Music slot must be merged!
        $lisaTimetable = $timetableService->getStudentTimetable($this->lisaStudent, $this->year2025->id);

        $lisaSlotSubjects = collect($lisaTimetable['slots'])->pluck('subject.code')->all();
        $this->assertContains('G10-MTH', $lisaSlotSubjects);
        $this->assertContains('G10-ENG', $lisaSlotSubjects);
        $this->assertContains('G10-MUS', $lisaSlotSubjects, 'Cross-section elective slot (Music from Section 10-B) was merged');
        $this->assertNotContains('G10-CS', $lisaSlotSubjects, 'Unselected elective slot (CS from Section 10-A) was excluded');

        // 8. Test API endpoint for Student self-timetable
        $apiResp = $this->actingAsTenant($this->bartUser, $this->greenwood)
            ->getJson('/api/student/timetable');
        $apiResp->assertStatus(200);
        $apiSlotCodes = collect($apiResp->json('data.slots'))->pluck('subject.code')->all();
        $this->assertContains('G10-CS', $apiSlotCodes);

        // 9. Test API endpoint for Parent child timetable
        $parentResp = $this->actingAsTenant($this->homerParent, $this->greenwood)
            ->getJson("/api/parent/children/{$this->lisaStudent->id}/timetable");
        $parentResp->assertStatus(200);
        $parentSlotCodes = collect($parentResp->json('data.slots'))->pluck('subject.code')->all();
        $this->assertContains('G10-MUS', $parentSlotCodes);
        $this->assertNotContains('G10-CS', $parentSlotCodes);
    }

    /**
     * Test parent can manage electives for linked child but is blocked for unlinked student.
     */
    public function test_parent_access_controls_for_child_electives(): void
    {
        SubjectOffering::firstOrCreate(
            ['school_id' => $this->greenwood->id, 'academic_year_id' => $this->year2025->id, 'grade_level_id' => $this->grade10->id, 'subject_id' => $this->electiveArt->id],
            ['type' => 'elective', 'max_students' => 15, 'is_open' => true]
        );

        // Homer selects Art for Bart (linked child) -> succeeds
        $resp = $this->actingAsTenant($this->homerParent, $this->greenwood)
            ->postJson("/api/parent/children/{$this->bartStudent->id}/electives/select", [
                'academic_year_id' => $this->year2025->id,
                'subject_id' => $this->electiveArt->id,
            ]);
        $resp->assertStatus(201);

        // Homer attempts to select elective for Milhouse (unlinked student in same school) -> 403
        $unlinkedResp = $this->actingAsTenant($this->homerParent, $this->greenwood)
            ->postJson("/api/parent/children/{$this->milhouseStudent->id}/electives/select", [
                'academic_year_id' => $this->year2025->id,
                'subject_id' => $this->electiveArt->id,
            ]);
        $unlinkedResp->assertStatus(403);

        // Homer attempts to select elective for Tommy (unlinked student from Maplewood) -> 404 (tenant scoping)
        $crossTenantResp = $this->actingAsTenant($this->homerParent, $this->greenwood)
            ->postJson("/api/parent/children/{$this->tommyStudent->id}/electives/select", [
                'academic_year_id' => $this->year2025->id,
                'subject_id' => $this->electiveArt->id,
            ]);
        $crossTenantResp->assertStatus(404);
    }

    /**
     * Test cross-tenant and role-based access control.
     */
    public function test_cross_tenant_and_role_isolation(): void
    {
        // Student cannot configure subject offerings
        $studentBlocked = $this->actingAsTenant($this->bartUser, $this->greenwood)
            ->postJson('/api/electives/offerings', [
                'academic_year_id' => $this->year2025->id,
                'grade_level_id' => $this->grade10->id,
                'subject_id' => $this->electiveCS->id,
            ]);
        $studentBlocked->assertStatus(403);

        // Maplewood admin cannot configure offerings for Greenwood's subjects/grades
        $maplewoodBlocked = $this->actingAsTenant($this->maplewoodAdmin, $this->maplewood)
            ->postJson('/api/electives/offerings', [
                'academic_year_id' => $this->year2025->id,
                'grade_level_id' => $this->grade10->id,
                'subject_id' => $this->electiveCS->id,
            ]);
        $this->assertContains($maplewoodBlocked->status(), [403, 404]);
    }
}
