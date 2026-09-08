<?php

namespace Tests\Feature;

use App\Mail\ReportCardPublishedMail;
use App\Mail\StudentAbsenceMail;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\InAppNotification;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\NotificationPipelineService;
use App\Services\PushNotificationHook;
use App\Services\SmsNotificationHook;
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
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UnifiedNotificationsPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected User $greenwoodAdmin;
    protected User $ednaTeacher;
    protected User $homerParent;
    protected User $margeParent;
    protected Student $bartStudent;
    protected Section $section9A;
    protected AcademicYear $year2025;

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
        app(\App\Tenancy\TenantManager::class)->setTenant($this->greenwood);

        $this->greenwoodAdmin = User::where('email', 'admin@greenwood.edu')->firstOrFail();
        $this->ednaTeacher = User::where('email', 'teacher@greenwood.edu')->firstOrFail();
        $this->homerParent = User::where('email', 'parent@greenwood.edu')->firstOrFail();
        $this->margeParent = User::where('email', 'marge@greenwood.edu')->firstOrFail();

        $this->bartStudent = Student::where('admission_number', 'GRE-25-00101')->firstOrFail();
        $this->section9A = $this->bartStudent->currentSection;
        $this->section9A->update(['homeroom_teacher_id' => $this->ednaTeacher->id]);
    }

    /**
     * Helper to authenticate as tenant user with Sanctum token and X-School-Id header.
     */
    protected function actingAsTenant(User $user): static
    {
        $this->app['auth']->forgetGuards();
        app(\App\Tenancy\TenantManager::class)->setTenant($this->greenwood);
        $token = $user->createToken('test-token')->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->greenwood->id);
    }

    /**
     * Acceptance Criterion 1: Marking a student absent triggers parent notification
     * (in-app + email if enabled) within the same request/queue job.
     */
    public function test_marking_student_absent_triggers_parent_notifications_in_app_and_email(): void
    {
        Mail::fake();

        $date = '2025-10-20'; // Monday

        $payload = [
            'date' => $date,
            'records' => [
                [
                    'student_id' => $this->bartStudent->id,
                    'status' => 'absent',
                    'remarks' => 'Flu symptoms',
                ],
            ],
        ];

        $response = $this->actingAsTenant($this->ednaTeacher)
            ->postJson("/api/sections/{$this->section9A->id}/attendance", $payload);

        $response->assertStatus(200);

        // Verify In-App notifications were generated for both linked parents (Homer & Marge)
        $this->assertDatabaseHas('notifications', [
            'school_id' => $this->greenwood->id,
            'user_id' => $this->homerParent->id,
            'type' => 'attendance_absence',
            'title' => 'Absence Notice: Bart Simpson',
        ]);

        $this->assertDatabaseHas('notifications', [
            'school_id' => $this->greenwood->id,
            'user_id' => $this->margeParent->id,
            'type' => 'attendance_absence',
            'title' => 'Absence Notice: Bart Simpson',
        ]);

        // Verify InAppNotification table for header bell center
        $this->assertDatabaseHas('in_app_notifications', [
            'school_id' => $this->greenwood->id,
            'user_id' => $this->homerParent->id,
            'type' => 'attendance_absence',
        ]);

        // Verify Queued Emails for both parents
        Mail::assertQueued(StudentAbsenceMail::class, function (StudentAbsenceMail $mail) {
            return $mail->hasTo($this->homerParent->email);
        });

        Mail::assertQueued(StudentAbsenceMail::class, function (StudentAbsenceMail $mail) {
            return $mail->hasTo($this->margeParent->email);
        });
    }

    /**
     * Acceptance Criterion: If parent disables email or attendance alerts in preferences,
     * in-app notification is still generated, but email is NOT queued.
     */
    public function test_marking_student_absent_respects_user_preferences(): void
    {
        Mail::fake();

        // Homer disables email_enabled
        $homerPref = NotificationPreference::forUser($this->homerParent);
        $homerPref->update(['email_enabled' => false]);

        // Marge disables attendance_alerts category
        $margePref = NotificationPreference::forUser($this->margeParent);
        $margePref->update(['attendance_alerts' => false]);

        $date = '2025-10-21';

        $payload = [
            'date' => $date,
            'records' => [
                [
                    'student_id' => $this->bartStudent->id,
                    'status' => 'absent',
                    'remarks' => 'Doctor appointment',
                ],
            ],
        ];

        $response = $this->actingAsTenant($this->ednaTeacher)
            ->postJson("/api/sections/{$this->section9A->id}/attendance", $payload);

        $response->assertStatus(200);

        // In-app notifications are ALWAYS delivered
        $this->assertDatabaseHas('notifications', [
            'school_id' => $this->greenwood->id,
            'user_id' => $this->homerParent->id,
            'type' => 'attendance_absence',
        ]);
        $this->assertDatabaseHas('notifications', [
            'school_id' => $this->greenwood->id,
            'user_id' => $this->margeParent->id,
            'type' => 'attendance_absence',
        ]);

        // Emails should NOT be queued because preferences forbid it
        Mail::assertNothingQueued();
    }

    /**
     * Acceptance Criterion 2: Publishing a report card notifies the linked parent(s).
     */
    public function test_publishing_report_card_notifies_linked_parents(): void
    {
        Mail::fake();

        $bartCard = ReportCard::where('student_id', $this->bartStudent->id)->firstOrFail();
        $bartCard->update(['status' => 'draft']);

        $response = $this->actingAsTenant($this->greenwoodAdmin)
            ->postJson("/api/report-cards/{$bartCard->id}/publish", [
                'principal_remarks' => 'Great performance this term!',
            ]);

        $response->assertStatus(200);

        // Verify In-app notification created for parents
        $this->assertDatabaseHas('notifications', [
            'school_id' => $this->greenwood->id,
            'user_id' => $this->homerParent->id,
            'type' => 'report_card_published',
            'title' => 'Report Card Published: Bart Simpson',
        ]);

        $this->assertDatabaseHas('notifications', [
            'school_id' => $this->greenwood->id,
            'user_id' => $this->margeParent->id,
            'type' => 'report_card_published',
        ]);

        // Verify email queued for Homer & Marge
        Mail::assertQueued(ReportCardPublishedMail::class, function (ReportCardPublishedMail $mail) {
            return $mail->hasTo($this->homerParent->email);
        });
        Mail::assertQueued(ReportCardPublishedMail::class, function (ReportCardPublishedMail $mail) {
            return $mail->hasTo($this->margeParent->email);
        });
    }

    /**
     * Test Report Card publishing respects disabled grade alerts.
     */
    public function test_publishing_report_card_respects_disabled_grade_alerts(): void
    {
        Mail::fake();

        $homerPref = NotificationPreference::forUser($this->homerParent);
        $homerPref->update(['grade_alerts' => false]);

        $bartCard = ReportCard::where('student_id', $this->bartStudent->id)->firstOrFail();
        $bartCard->update(['status' => 'draft']);

        $response = $this->actingAsTenant($this->greenwoodAdmin)
            ->postJson("/api/report-cards/{$bartCard->id}/publish");

        $response->assertStatus(200);

        // In-app is still delivered
        $this->assertDatabaseHas('notifications', [
            'school_id' => $this->greenwood->id,
            'user_id' => $this->homerParent->id,
            'type' => 'report_card_published',
        ]);

        // Email was queued for Marge (alerts enabled), but NOT for Homer (grade_alerts = false)
        Mail::assertQueued(ReportCardPublishedMail::class, function (ReportCardPublishedMail $mail) {
            return $mail->hasTo($this->margeParent->email);
        });

        Mail::assertNotQueued(ReportCardPublishedMail::class, function (ReportCardPublishedMail $mail) {
            return $mail->hasTo($this->homerParent->email);
        });
    }

    /**
     * Test notification preferences API: GET and PUT endpoints.
     */
    public function test_user_can_view_and_update_notification_preferences_via_api(): void
    {
        // 1. GET preferences returns defaults
        $response = $this->actingAsTenant($this->homerParent)
            ->getJson('/api/notifications/preferences');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email_enabled', true)
            ->assertJsonPath('data.telegram_enabled', true)
            ->assertJsonPath('data.attendance_alerts', true)
            ->assertJsonPath('data.grade_alerts', true);

        // 2. PUT preferences updates selected fields
        $updateResponse = $this->actingAsTenant($this->homerParent)
            ->putJson('/api/notifications/preferences', [
                'email_enabled' => false,
                'library_alerts' => false,
                'message_alerts' => false,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email_enabled', false)
            ->assertJsonPath('data.library_alerts', false)
            ->assertJsonPath('data.message_alerts', false)
            ->assertJsonPath('data.attendance_alerts', true); // Unmodified remains true

        // Verify in database
        $this->assertDatabaseHas('notification_preferences', [
            'school_id' => $this->greenwood->id,
            'user_id' => $this->homerParent->id,
            'email_enabled' => 0,
            'library_alerts' => 0,
            'message_alerts' => 0,
            'attendance_alerts' => 1,
        ]);
    }

    /**
     * Test deduplication ledger prevents repeated duplicate email dispatch.
     */
    public function test_deduplication_ledger_prevents_duplicate_dispatches(): void
    {
        Mail::fake();

        $pipeline = app(NotificationPipelineService::class);
        $record = AttendanceRecord::firstOrCreate(
            [
                'school_id' => $this->greenwood->id,
                'student_id' => $this->bartStudent->id,
                'date' => '2025-10-22',
            ],
            [
                'section_id' => $this->section9A->id,
                'status' => 'absent',
                'marked_by' => $this->ednaTeacher->id,
                'remarks' => 'Unexcused',
            ]
        );

        // First dispatch
        $pipeline->notifyAbsence($this->bartStudent, $record);

        // Second dispatch for the exact same record
        $pipeline->notifyAbsence($this->bartStudent, $record);

        // Homer and Marge should each only have been sent 1 email, not 2
        Mail::assertQueued(StudentAbsenceMail::class, 2);
    }

    /**
     * Test pluggable SMS and Push notification hooks.
     */
    public function test_sms_and_push_hooks_execute_when_enabled(): void
    {
        $smsReceived = [];
        $pushReceived = [];

        SmsNotificationHook::registerHandler(function ($recipient, $message, $payload) use (&$smsReceived) {
            $smsReceived[] = ['recipient' => $recipient->email, 'message' => $message];
            return true;
        });

        PushNotificationHook::registerHandler(function ($recipient, $title, $body, $payload) use (&$pushReceived) {
            $pushReceived[] = ['recipient' => $recipient->email, 'title' => $title];
            return true;
        });

        // Enable SMS and Push for Homer, and give him a phone number
        $this->homerParent->update(['phone' => '+15551234567']);
        $homerPref = NotificationPreference::forUser($this->homerParent);
        $homerPref->update([
            'sms_enabled' => true,
            'push_enabled' => true,
        ]);

        $pipeline = app(NotificationPipelineService::class);
        $result = $pipeline->send(
            recipient: $this->homerParent,
            type: 'system_alert',
            title: 'Emergency Drill',
            body: 'A routine drill will happen tomorrow at 10am.',
            payload: ['type' => 'drill'],
            schoolId: $this->greenwood->id
        );

        $this->assertTrue($result['channels']['sms']);
        $this->assertTrue($result['channels']['push']);
        $this->assertCount(1, $smsReceived);
        $this->assertCount(1, $pushReceived);
        $this->assertEquals($this->homerParent->email, $smsReceived[0]['recipient']);
    }
}
