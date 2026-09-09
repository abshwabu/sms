<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\GradeLevel;
use App\Models\GradingScale;
use App\Models\ParentProfile;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\AttendanceSeeder;
use Database\Seeders\GradingSeeder;
use Database\Seeders\ParentSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SchoolSeeder;
use Database\Seeders\StaffSeeder;
use Database\Seeders\StudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingAndReportCardsTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected School $oakridge;
    protected User $greenwoodAdmin;
    protected User $greenwoodTeacher;
    protected User $homerParentUser;
    protected Student $bartStudent;
    protected Student $lisaStudent;
    protected Student $milhouseStudent;
    protected Section $sectionA;
    protected Term $fallTerm;
    protected AcademicYear $year2025;
    protected GradingScale $gradingScale;
    protected User $oakridgeParentUser;

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
        ]);

        $this->greenwood = School::where('subdomain', 'greenwood')->firstOrFail();
        $this->oakridge = School::where('subdomain', 'oakridge')->firstOrFail();

        app(\App\Tenancy\TenantManager::class)->setTenant($this->greenwood);

        $this->greenwoodAdmin = User::where('email', 'admin@greenwood.edu')->firstOrFail();
        $this->greenwoodTeacher = User::where('email', 'teacher@greenwood.edu')->firstOrFail();
        $this->homerParentUser = User::where('email', 'parent@greenwood.edu')->firstOrFail();

        $this->bartStudent = Student::where('admission_number', 'GRE-25-00101')->firstOrFail();
        $this->lisaStudent = Student::where('admission_number', 'GRE-25-00102')->firstOrFail();
        $this->milhouseStudent = Student::where('admission_number', 'GRE-25-00103')->firstOrFail();

        $this->year2025 = AcademicYear::where('school_id', $this->greenwood->id)->where('name', '2025/2026')->firstOrFail();
        $this->fallTerm = Term::where('school_id', $this->greenwood->id)->where('name', 'Fall Semester')->firstOrFail();
        $this->sectionA = Section::where('school_id', $this->greenwood->id)->where('academic_year_id', $this->year2025->id)->firstOrFail();
        $this->gradingScale = GradingScale::where('school_id', $this->greenwood->id)->firstOrFail();

        $this->oakridgeParentUser = User::where('email', 'parent@oakridge.edu')->firstOrFail();
    }

    /**
     * Acceptance Criterion 1: Teacher enters marks for their assigned subject/section;
     * report card auto-aggregates across all subjects for that student once all teachers have submitted.
     */
    public function test_teacher_enters_marks_and_report_card_auto_aggregates_once_all_teachers_have_submitted(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // Wipe existing grades and report cards for Bart to test auto-aggregation flow from scratch
        Grade::where('student_id', $this->bartStudent->id)->delete();
        ReportCard::where('student_id', $this->bartStudent->id)->delete();

        $subjects = Subject::where('grade_level_id', $this->sectionA->grade_level_id)->get();
        $this->assertGreaterThanOrEqual(3, $subjects->count());

        $midterm = Exam::where('term_id', $this->fallTerm->id)->where('type', 'midterm')->firstOrFail();

        // 1. Submit grade for only the first subject (e.g. Math)
        $sub1 = $subjects[0];
        $response1 = $this->actingAs($this->greenwoodTeacher)
            ->postJson('/api/grades', [
                'exam_id' => $midterm->id,
                'subject_id' => $sub1->id,
                'section_id' => $this->sectionA->id,
                'grades' => [
                    [
                        'student_id' => $this->bartStudent->id,
                        'marks_obtained' => 85.0,
                        'max_marks' => 100.0,
                        'remarks' => 'Good foundation',
                    ],
                ],
            ], $headers);

        $response1->assertStatus(200);

        // Verify draft report card created, but all_teachers_submitted is FALSE
        $cardAfterSub1 = ReportCard::withoutGlobalScopes()
            ->where('student_id', $this->bartStudent->id)
            ->where('term_id', $this->fallTerm->id)
            ->first();

        $this->assertNotNull($cardAfterSub1);
        $this->assertFalse($cardAfterSub1->all_teachers_submitted);
        $this->assertEquals(85.0, $cardAfterSub1->average_percentage);

        // 2. Submit grades for the remaining subjects
        foreach ($subjects->slice(1) as $sub) {
            $this->actingAs($this->greenwoodTeacher)
                ->postJson('/api/grades', [
                    'exam_id' => $midterm->id,
                    'subject_id' => $sub->id,
                    'section_id' => $this->sectionA->id,
                    'grades' => [
                        [
                            'student_id' => $this->bartStudent->id,
                            'marks_obtained' => 75.0,
                            'max_marks' => 100.0,
                        ],
                    ],
                ], $headers)
                ->assertStatus(200);
        }

        // 3. Verify that once all subjects have grades submitted, all_teachers_submitted = TRUE
        $cardAfterAll = ReportCard::withoutGlobalScopes()
            ->where('student_id', $this->bartStudent->id)
            ->where('term_id', $this->fallTerm->id)
            ->first();

        $this->assertNotNull($cardAfterAll);
        $this->assertTrue($cardAfterAll->all_teachers_submitted);
        $this->assertGreaterThan(0, $cardAfterAll->average_percentage);
        $this->assertNotNull($cardAfterAll->overall_grade);
        $this->assertNotNull($cardAfterAll->attendance_summary);
    }

    /**
     * Acceptance Criterion 1 (Ranking): Students in section are ranked according to average percentage.
     */
    public function test_students_are_automatically_ranked_in_section(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        $response = $this->actingAs($this->greenwoodTeacher)
            ->getJson("/api/sections/{$this->sectionA->id}/report-cards?term_id={$this->fallTerm->id}", $headers);

        $response->assertStatus(200);
        $cards = $response->json('data.report_cards');

        $this->assertNotEmpty($cards);

        // Lisa has higher marks (96.8%) than Bart (71.4%)
        $lisaCard = collect($cards)->firstWhere('student_id', $this->lisaStudent->id);
        $bartCard = collect($cards)->firstWhere('student_id', $this->bartStudent->id);

        $this->assertNotNull($lisaCard);
        $this->assertNotNull($bartCard);
        $this->assertEquals(1, $lisaCard['rank_in_section']);
        $this->assertEquals(2, $bartCard['rank_in_section']);
    }

    /**
     * Acceptance Criterion 2: Report card PDF matches the school's configured grading scale.
     */
    public function test_report_card_pdf_matches_school_configured_grading_scale(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        $lisaCard = ReportCard::where('student_id', $this->lisaStudent->id)
            ->where('term_id', $this->fallTerm->id)
            ->firstOrFail();

        $response = $this->actingAs($this->greenwoodAdmin)
            ->get("/api/report-cards/{$lisaCard->id}/pdf", $headers);

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));

        // Inspect PDF content: starts with %PDF magic bytes
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF', $content);

        // Verify grading scale is linked and has rules
        $this->assertNotNull($lisaCard->gradingScale);
        $this->assertEquals('Standard Letter (A–F)', $lisaCard->gradingScale->name);
        $this->assertCount(5, $lisaCard->gradingScale->rules);
    }

    /**
     * Acceptance Criterion 3: Parent can view/download their child's report card once published
     * (not before — draft returns 403 Forbidden).
     */
    public function test_parent_cannot_view_or_download_draft_report_card(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // Bart's report card is in draft status
        $bartCard = ReportCard::where('student_id', $this->bartStudent->id)
            ->where('term_id', $this->fallTerm->id)
            ->firstOrFail();
        $bartCard->update(['status' => 'draft', 'published_at' => null]);

        // 1. Parent listing should omit draft report cards
        $listResponse = $this->actingAs($this->homerParentUser)
            ->getJson("/api/parent/children/{$this->bartStudent->id}/report-cards", $headers);

        $listResponse->assertStatus(200);
        $cards = $listResponse->json('data');
        $this->assertEmpty($cards, 'Draft report cards must not be listed in the parent portal');

        // 2. Direct PDF download of draft returns 403 Forbidden
        $pdfResponse = $this->actingAs($this->homerParentUser)
            ->get("/api/parent/children/{$this->bartStudent->id}/report-cards/{$bartCard->id}/pdf", $headers);

        $pdfResponse->assertStatus(403);
        $this->assertStringContainsString('not been published yet', $pdfResponse->getContent());

        // 3. Direct report card show endpoint returns 403 Forbidden
        $showResponse = $this->actingAs($this->homerParentUser)
            ->getJson("/api/report-cards/{$bartCard->id}", $headers);

        $showResponse->assertStatus(403);
    }

    /**
     * Acceptance Criterion 3: Once published, parent can view and download child's report card.
     */
    public function test_parent_can_view_and_download_published_report_card(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // Lisa's report card is published
        $lisaCard = ReportCard::where('student_id', $this->lisaStudent->id)
            ->where('term_id', $this->fallTerm->id)
            ->firstOrFail();
        $lisaCard->update(['status' => 'published', 'published_at' => now()]);

        // 1. Parent listing includes published report card
        $listResponse = $this->actingAs($this->homerParentUser)
            ->getJson("/api/parent/children/{$this->lisaStudent->id}/report-cards", $headers);

        $listResponse->assertStatus(200);
        $cards = $listResponse->json('data');
        $this->assertCount(1, $cards);
        $this->assertEquals($lisaCard->id, $cards[0]['id']);

        // 2. Parent can download the published report card PDF
        $pdfResponse = $this->actingAs($this->homerParentUser)
            ->get("/api/parent/children/{$this->lisaStudent->id}/report-cards/{$lisaCard->id}/pdf", $headers);

        $pdfResponse->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $pdfResponse->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdfResponse->getContent());
    }

    /**
     * Parent cannot access a student they are not linked to (even if published).
     */
    public function test_parent_cannot_view_unlinked_student_report_card(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        $milhouseCard = ReportCard::firstOrCreate(
            ['school_id' => $this->greenwood->id, 'student_id' => $this->milhouseStudent->id, 'term_id' => $this->fallTerm->id],
            ['section_id' => $this->sectionA->id, 'academic_year_id' => $this->year2025->id, 'status' => 'published', 'published_at' => now()]
        );

        // Homer is not linked to Milhouse
        $response = $this->actingAs($this->homerParentUser)
            ->getJson("/api/parent/children/{$this->milhouseStudent->id}/report-cards", $headers);

        $response->assertStatus(403);

        $pdfResponse = $this->actingAs($this->homerParentUser)
            ->get("/api/parent/children/{$this->milhouseStudent->id}/report-cards/{$milhouseCard->id}/pdf", $headers);

        $pdfResponse->assertStatus(403);
    }

    /**
     * Cross-tenant isolation: User from School A cannot access School B's report cards.
     */
    public function test_cross_tenant_isolation_on_report_cards(): void
    {
        $lisaCard = ReportCard::where('student_id', $this->lisaStudent->id)->firstOrFail();

        // Oakridge parent attempting to access Greenwood report card
        $response = $this->actingAs($this->oakridgeParentUser)
            ->getJson("/api/report-cards/{$lisaCard->id}", ['X-School-Id' => $this->oakridge->id]);

        // Global tenant scope prevents finding School A's report card in School B's context (returns 404 or 403)
        $this->assertContains($response->status(), [403, 404]);
    }

    /**
     * Publishing workflow: School Admin or Homeroom Teacher publishes a report card.
     */
    public function test_admin_and_homeroom_teacher_can_publish_report_cards(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        $bartCard = ReportCard::where('student_id', $this->bartStudent->id)->firstOrFail();
        $bartCard->update(['status' => 'draft']);

        $response = $this->actingAs($this->greenwoodAdmin)
            ->postJson("/api/report-cards/{$bartCard->id}/publish", [
                'principal_remarks' => 'Good work this term, keep striving for excellence.',
            ], $headers);

        $response->assertStatus(200);
        $this->assertEquals('published', $bartCard->fresh()->status);
        $this->assertEquals('Good work this term, keep striving for excellence.', $bartCard->fresh()->principal_remarks);
    }

    /**
     * Test terms listing, exam creation & deletion, and grading scale deletion.
     */
    public function test_admin_can_list_all_terms_create_and_delete_exams_and_grading_scales(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // 1. List terms
        $termsRes = $this->actingAs($this->greenwoodAdmin)
            ->getJson('/api/terms', $headers);
        $termsRes->assertStatus(200);
        $this->assertNotEmpty($termsRes->json('data'));

        // 2. Create exam
        $createExamRes = $this->actingAs($this->greenwoodAdmin)
            ->postJson('/api/exams', [
                'name' => 'Midterm Assessment 2',
                'type' => 'midterm',
                'weight' => 25.0,
                'max_marks' => 100,
                'academic_year_id' => $this->year2025->id,
                'term_id' => $this->fallTerm->id,
                'grade_level_id' => $this->sectionA->grade_level_id,
            ], $headers);
        $createExamRes->assertStatus(201);
        $examId = $createExamRes->json('data.id');

        // 3. Delete exam
        $deleteExamRes = $this->actingAs($this->greenwoodAdmin)
            ->deleteJson("/api/exams/{$examId}", [], $headers);
        $deleteExamRes->assertStatus(200);
        $this->assertNull(Exam::find($examId));

        // 4. Create non-default grading scale & delete it
        $createScaleRes = $this->actingAs($this->greenwoodAdmin)
            ->postJson('/api/grading-scales', [
                'name' => 'Pass-Fail Scale',
                'scale_type' => 'percentage',
                'is_default' => false,
                'rules' => [
                    ['min_score' => 60, 'max_score' => 100, 'grade' => 'P', 'gpa_point' => 3.0, 'description' => 'Passed'],
                    ['min_score' => 0, 'max_score' => 59.9, 'grade' => 'F', 'gpa_point' => 0.0, 'description' => 'Failed'],
                ],
            ], $headers);
        $createScaleRes->assertStatus(201);
        $scaleId = $createScaleRes->json('data.id');

        $deleteScaleRes = $this->actingAs($this->greenwoodAdmin)
            ->deleteJson("/api/grading-scales/{$scaleId}", [], $headers);
        $deleteScaleRes->assertStatus(200);
        $this->assertNull(GradingScale::find($scaleId));
    }

    /**
     * Test report cards fallback when no term is explicitly marked active.
     */
    public function test_section_report_cards_fallbacks_when_no_term_is_active(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // Deactivate all terms for 2025/2026
        Term::where('academic_year_id', $this->year2025->id)->update(['is_active' => false]);

        // Querying section report cards without term_id should still resolve via fallback
        $response = $this->actingAs($this->greenwoodAdmin)
            ->getJson("/api/sections/{$this->sectionA->id}/report-cards", $headers);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.term'));
    }
}
