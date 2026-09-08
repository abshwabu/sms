<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Enums\UserStatus;
use App\Mail\AnnouncementPublishedMail;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\CommunicationMessage;
use App\Models\CommunicationThread;
use App\Models\GradeLevel;
use App\Models\InAppNotification;
use App\Models\NotificationDispatch;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Services\TelegramService;
use Carbon\Carbon;
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

class CommunicationsAndAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected School $oakridge;
    protected User $greenwoodAdmin;
    protected User $ednaTeacher;
    protected User $hooverTeacher;
    protected User $homerParent;
    protected User $margeParent;
    protected User $bartUser;
    protected Student $bartStudent;
    protected Student $milhouseStudent;
    protected User $kirkParent;
    protected GradeLevel $grade10;
    protected GradeLevel $grade11;
    protected Section $sectionA;
    protected User $oakridgeAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        TelegramService::clearSentMessages();

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

        app(\App\Tenancy\TenantManager::class)->setTenant($this->greenwood);

        $this->greenwoodAdmin = User::where('email', 'admin@greenwood.edu')->firstOrFail();
        $this->ednaTeacher = User::where('email', 'teacher@greenwood.edu')->firstOrFail();
        $this->hooverTeacher = User::where('email', 'hoover@greenwood.edu')->firstOrFail();
        $this->homerParent = User::where('email', 'parent@greenwood.edu')->firstOrFail();

        $this->bartStudent = Student::where('admission_number', 'GRE-25-00101')->firstOrFail();
        $this->bartUser = $this->bartStudent->user;
        $this->milhouseStudent = Student::where('admission_number', 'GRE-25-00103')->firstOrFail();

        // Create Marge Simpson as second linked parent for Bart
        $margeUser = User::create([
            'school_id' => $this->greenwood->id,
            'name' => 'Marge Simpson',
            'email' => 'marge@simpson.family',
            'password' => bcrypt('password123'),
            'role' => RoleEnum::PARENT->value,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $margeProfile = ParentProfile::create([
            'school_id' => $this->greenwood->id,
            'user_id' => $margeUser->id,
            'occupation' => 'Homemaker',
        ]);
        $margeProfile->linkStudent($this->bartStudent, 'mother', false);
        $this->margeParent = $margeUser;

        // Create Kirk Van Houten (Parent of Milhouse)
        $kirkUser = User::create([
            'school_id' => $this->greenwood->id,
            'name' => 'Kirk Van Houten',
            'email' => 'kirk@crackers.com',
            'password' => bcrypt('password123'),
            'role' => RoleEnum::PARENT->value,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $kirkProfile = ParentProfile::create([
            'school_id' => $this->greenwood->id,
            'user_id' => $kirkUser->id,
        ]);
        $kirkProfile->linkStudent($this->milhouseStudent, 'father', true);
        $this->kirkParent = $kirkUser;

        $this->grade10 = GradeLevel::where('school_id', $this->greenwood->id)->where('code', 'G10')->firstOrFail();
        $this->grade11 = GradeLevel::where('school_id', $this->greenwood->id)->where('code', 'G11')->firstOrFail();

        $year2025 = AcademicYear::where('school_id', $this->greenwood->id)->where('name', '2025/2026')->firstOrFail();
        $this->sectionA = Section::where('school_id', $this->greenwood->id)
            ->where('academic_year_id', $year2025->id)
            ->where('name', 'Section A')
            ->firstOrFail();

        $this->oakridgeAdmin = User::where('email', 'admin@oakridge.edu')->firstOrFail();
    }

    /**
     * Acceptance Criterion 1:
     * An announcement targeted at "Grade 10" only appears for Grade 10 parents/students/teachers, not the whole school.
     */
    public function test_grade_targeted_announcement_only_visible_to_that_grade_members(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // 1. Create announcement targeted at Grade 10
        $grade10Ann = Announcement::create([
            'school_id' => $this->greenwood->id,
            'author_id' => $this->greenwoodAdmin->id,
            'title' => 'Grade 10 Mandatory Field Trip Notice',
            'body' => 'All Grade 10 students must submit permission slips by Wednesday.',
            'audience_type' => 'grade_level',
            'grade_level_id' => $this->grade10->id,
            'priority' => 'high',
            'published_at' => Carbon::now()->subMinutes(10),
        ]);

        // 2. Create announcement targeted at Grade 11
        $grade11Ann = Announcement::create([
            'school_id' => $this->greenwood->id,
            'author_id' => $this->greenwoodAdmin->id,
            'title' => 'Grade 11 College SAT Workshop',
            'body' => 'Juniors in Grade 11 only: SAT prep books available in room 301.',
            'audience_type' => 'grade_level',
            'grade_level_id' => $this->grade11->id,
            'priority' => 'normal',
            'published_at' => Carbon::now()->subMinutes(10),
        ]);

        // A. Bart (Student in Grade 10) queries announcements
        $bartRes = $this->actingAs($this->bartUser)->getJson('/api/announcements', $headers);
        $bartRes->assertOk();
        $bartTitles = collect($bartRes->json('data'))->pluck('title')->toArray();

        $this->assertContains('Grade 10 Mandatory Field Trip Notice', $bartTitles);
        $this->assertNotContains('Grade 11 College SAT Workshop', $bartTitles, 'Grade 10 student must not see Grade 11 announcement');

        // B. Homer (Parent of Bart in Grade 10) queries announcements
        $homerRes = $this->actingAs($this->homerParent)->getJson('/api/announcements', $headers);
        $homerRes->assertOk();
        $homerTitles = collect($homerRes->json('data'))->pluck('title')->toArray();

        $this->assertContains('Grade 10 Mandatory Field Trip Notice', $homerTitles);
        $this->assertNotContains('Grade 11 College SAT Workshop', $homerTitles, 'Grade 10 parent must not see Grade 11 announcement');

        // C. Edna (Homeroom teacher for Section A in Grade 10)
        $ednaRes = $this->actingAs($this->ednaTeacher)->getJson('/api/announcements', $headers);
        $ednaRes->assertOk();
        $ednaTitles = collect($ednaRes->json('data'))->pluck('title')->toArray();

        $this->assertContains('Grade 10 Mandatory Field Trip Notice', $ednaTitles);
    }

    /**
     * Acceptance Criterion 2:
     * Teacher-parent message thread is scoped to the specific student and visible to both linked parents (if two) plus the relevant teacher(s).
     */
    public function test_teacher_parent_message_thread_visible_to_both_linked_parents_and_relevant_teacher(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // 1. Edna (Teacher of Bart) starts a direct thread regarding Bart Simpson
        $createRes = $this->actingAs($this->ednaTeacher)->postJson('/api/communications/threads', [
            'student_id' => $this->bartStudent->id,
            'subject' => "Bart's Classroom Focus",
            'message' => 'Hello parents, Bart has been daydreaming during morning algebra.',
        ], $headers);

        $createRes->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subject', "Bart's Classroom Focus");

        $threadId = $createRes->json('data.id');

        // 2. Homer (Father, linked parent 1) can view the thread and reply
        $homerViewRes = $this->actingAs($this->homerParent)
            ->getJson("/api/communications/threads/{$threadId}", $headers);

        $homerViewRes->assertOk()
            ->assertJsonPath('data.subject', "Bart's Classroom Focus");

        $homerReplyRes = $this->actingAs($this->homerParent)
            ->postJson("/api/communications/threads/{$threadId}/messages", [
                'body' => "I will have a firm talk with the boy tonight!",
            ], $headers);

        $homerReplyRes->assertCreated();

        // 3. Marge (Mother, linked parent 2) can also view the same thread and see Homer's and Edna's messages
        $margeViewRes = $this->actingAs($this->margeParent)
            ->getJson("/api/communications/threads/{$threadId}", $headers);

        $margeViewRes->assertOk()
            ->assertJsonPath('data.subject', "Bart's Classroom Focus");

        $messages = collect($margeViewRes->json('data.messages'))->pluck('body')->toArray();
        $this->assertContains('Hello parents, Bart has been daydreaming during morning algebra.', $messages);
        $this->assertContains('I will have a firm talk with the boy tonight!', $messages);

        // Marge can reply as well
        $margeReplyRes = $this->actingAs($this->margeParent)
            ->postJson("/api/communications/threads/{$threadId}/messages", [
                'body' => "I have also packed brain-healthy snacks for his recess.",
            ], $headers);
        $margeReplyRes->assertCreated();

        // 4. Kirk (Parent of Milhouse, unlinked to Bart) CANNOT access this thread
        $kirkViewRes = $this->actingAs($this->kirkParent)
            ->getJson("/api/communications/threads/{$threadId}", $headers);
        $kirkViewRes->assertForbidden();

        // 5. Bart (the student himself) CANNOT access parent-teacher direct thread
        $bartViewRes = $this->actingAs($this->bartUser)
            ->getJson("/api/communications/threads/{$threadId}", $headers);
        $bartViewRes->assertForbidden();
    }

    /**
     * Acceptance Criterion 3:
     * A parent who links their Telegram account receives the same announcement via Telegram
     * as they would via email, without duplicate spam if both channels are enabled.
     */
    public function test_linked_telegram_parent_receives_announcement_without_duplicate_spam(): void
    {
        Mail::fake();
        TelegramService::clearSentMessages();

        $headers = ['X-School-Id' => $this->greenwood->id];

        // 1. Homer requests Telegram link code
        $codeRes = $this->actingAs($this->homerParent)
            ->postJson('/api/telegram/link-code', [], $headers);

        $codeRes->assertOk()
            ->assertJsonPath('success', true);

        $linkCode = $codeRes->json('data.link_code');
        $this->assertNotEmpty($linkCode);

        // 2. Link Homer's Telegram account via /start <linkCode>
        $linkRes = $this->postJson('/api/telegram/link', [
            'link_code' => $linkCode,
            'telegram_chat_id' => '123456789',
            'telegram_username' => 'homer_real',
            'first_name' => 'Homer Simpson',
        ], $headers);

        $linkRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_linked', true);

        $this->assertDatabaseHas('telegram_accounts', [
            'user_id' => $this->homerParent->id,
            'telegram_chat_id' => '123456789',
            'is_linked' => true,
        ]);

        TelegramService::clearSentMessages(); // clear welcome message

        // 3. Admin publishes a school-wide announcement with both Email and Telegram enabled
        $announcementPayload = [
            'title' => 'Important Weather Alert: Early Dismissal',
            'body' => 'Due to incoming heavy snowfall, school will dismiss at 13:00 today. School buses will depart promptly.',
            'audience_type' => 'all',
            'priority' => 'urgent',
            'channels' => ['in_app', 'email', 'telegram'],
            'publish_now' => true,
        ];

        $pubRes = $this->actingAs($this->greenwoodAdmin)
            ->postJson('/api/announcements', $announcementPayload, $headers);

        $pubRes->assertCreated();
        $announcementId = $pubRes->json('data.id');

        // Verify Email Sent/Queued to Homer
        Mail::assertQueued(AnnouncementPublishedMail::class, function ($mail) {
            return $mail->recipient->email === $this->homerParent->email
                && $mail->announcement->title === 'Important Weather Alert: Early Dismissal';
        });

        // Verify Telegram message received by Homer
        $sentTelegramMsgs = TelegramService::$sentMessages;
        $this->assertNotEmpty($sentTelegramMsgs);

        $homerTelegramMsg = collect($sentTelegramMsgs)->firstWhere('chat_id', '123456789');
        $this->assertNotNull($homerTelegramMsg);
        $this->assertStringContainsString('Important Weather Alert: Early Dismissal', $homerTelegramMsg['text']);
        $this->assertStringContainsString('heavy snowfall', $homerTelegramMsg['text']);

        // Verify In-App Notification was created for Homer
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $this->homerParent->id,
            'title' => 'Important Weather Alert: Early Dismissal',
        ]);

        // 4. Verify No Duplicate Spam:
        // Calling publish or dispatching again does NOT resend duplicate messages
        $announcement = Announcement::find($announcementId);
        $announcementService = app(\App\Services\AnnouncementService::class);
        $announcementService->dispatchAnnouncement($announcement);

        // Dispatches count for Homer on telegram must remain exactly 1
        $telegramDispatches = NotificationDispatch::where('user_id', $this->homerParent->id)
            ->where('notifiable_id', $announcementId)
            ->where('channel', 'telegram')
            ->count();
        $this->assertEquals(1, $telegramDispatches, 'Telegram dispatches must not exceed 1 (no duplicate spam)');

        // Dispatches count for Homer on email must remain exactly 1
        $emailDispatches = NotificationDispatch::where('user_id', $this->homerParent->id)
            ->where('notifiable_id', $announcementId)
            ->where('channel', 'email')
            ->count();
        $this->assertEquals(1, $emailDispatches, 'Email dispatches must not exceed 1 (no duplicate spam)');
    }

    /**
     * In-App notification center: list unread notifications, mark read, mark all read.
     */
    public function test_in_app_notification_center_feed_and_read_status(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // Homer queries notification center
        $notifRes = $this->actingAs($this->homerParent)->getJson('/api/notifications', $headers);
        $notifRes->assertOk()
            ->assertJsonPath('success', true);

        $unreadCount = $notifRes->json('data.unread_count');
        $this->assertGreaterThanOrEqual(1, $unreadCount);

        // Mark all as read
        $markAllRes = $this->actingAs($this->homerParent)->postJson('/api/notifications/read-all', [], $headers);
        $markAllRes->assertOk();

        // Query again, unread count should now be 0
        $afterRes = $this->actingAs($this->homerParent)->getJson('/api/notifications', $headers);
        $this->assertEquals(0, $afterRes->json('data.unread_count'));
    }

    /**
     * Cross-tenant isolation: Oakridge admin cannot access Greenwood announcements or threads.
     */
    public function test_cross_tenant_isolation_on_communications(): void
    {
        $oakridgeHeaders = ['X-School-Id' => $this->oakridge->id];

        // Find a Greenwood announcement
        $greenwoodAnn = Announcement::where('school_id', $this->greenwood->id)->firstOrFail();

        // Oakridge admin trying to view Greenwood announcement
        $response = $this->actingAs($this->oakridgeAdmin)
            ->getJson("/api/announcements/{$greenwoodAnn->id}", $oakridgeHeaders);

        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    /**
     * Telegram Webhook /start <link_code> links account and triggers welcome message.
     */
    public function test_telegram_webhook_processes_start_command_with_link_code(): void
    {
        $telegramService = app(TelegramService::class);
        $payload = $telegramService->generateLinkCode($this->ednaTeacher);

        $linkCode = $payload['link_code'];

        // Simulate incoming webhook from Telegram Bot API
        $webhookRes = $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 999999,
            'message' => [
                'message_id' => 101,
                'chat' => ['id' => 555444333, 'type' => 'private'],
                'from' => ['id' => 555444333, 'first_name' => 'Edna', 'username' => 'edna_k'],
                'text' => "/start {$linkCode}",
            ],
        ]);

        $webhookRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('action', 'linked');

        $this->assertDatabaseHas('telegram_accounts', [
            'user_id' => $this->ednaTeacher->id,
            'telegram_chat_id' => '555444333',
            'is_linked' => true,
        ]);
    }

    /**
     * Non-author teacher cannot edit another teacher or admin's announcement.
     */
    public function test_non_author_teacher_cannot_edit_announcement(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        $adminAnn = Announcement::where('school_id', $this->greenwood->id)
            ->where('author_id', $this->greenwoodAdmin->id)
            ->firstOrFail();

        // Hoover tries to edit admin announcement
        $this->actingAs($this->hooverTeacher)
            ->putJson("/api/announcements/{$adminAnn->id}", [
                'title' => 'Tampered Title',
                'body' => 'Tampered Body',
                'audience_type' => 'all',
            ], $headers)
            ->assertForbidden();
    }
}
