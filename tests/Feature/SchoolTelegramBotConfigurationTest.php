<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\School;
use App\Models\User;
use App\Services\TelegramService;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SchoolSeeder;
use Database\Seeders\StaffSeeder;
use Database\Seeders\StudentSeeder;
use Database\Seeders\ParentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolTelegramBotConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected School $oakridge;
    protected User $greenwoodAdmin;
    protected User $oakridgeAdmin;
    protected User $teacher;
    protected User $parent;
    protected User $superAdmin;

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
        ]);

        $this->greenwood = School::where('subdomain', 'greenwood')->firstOrFail();
        $this->oakridge = School::where('subdomain', 'oakridge')->firstOrFail();

        $this->greenwoodAdmin = User::where('email', 'admin@greenwood.edu')->firstOrFail();
        $this->teacher = User::where('email', 'teacher@greenwood.edu')->firstOrFail();
        $this->parent = User::where('email', 'parent@greenwood.edu')->firstOrFail();
        $this->superAdmin = User::where('email', 'superadmin@bina.test')->firstOrFail();

        $this->oakridgeAdmin = User::where('email', 'admin@oakridge.edu')->first()
            ?: User::where('school_id', $this->oakridge->id)->firstOrFail();

        app(\App\Tenancy\TenantManager::class)->setTenant($this->greenwood);
    }

    public function test_school_admin_can_retrieve_current_school_telegram_bot_settings(): void
    {
        $this->greenwood->update([
            'telegram_bot_token' => '123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ123456',
            'telegram_bot_username' => 'GreenwoodHighBot',
        ]);

        $response = $this->actingAs($this->greenwoodAdmin, 'sanctum')
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->getJson('/api/admin/school/telegram');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.school_id', $this->greenwood->id)
            ->assertJsonPath('data.school_name', $this->greenwood->name)
            ->assertJsonPath('data.telegram_bot_username', 'GreenwoodHighBot')
            ->assertJsonPath('data.has_telegram_bot', true);

        // Token must be masked, not plaintext
        $maskedToken = $response->json('data.telegram_bot_token');
        $this->assertStringContainsString('•', $maskedToken);
        $this->assertNotEquals('123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ123456', $maskedToken);
    }

    public function test_school_admin_can_update_school_telegram_bot_settings(): void
    {
        $response = $this->actingAs($this->greenwoodAdmin, 'sanctum')
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->putJson('/api/admin/school/telegram', [
                'telegram_bot_token' => '987654321:XYZ987654321AbcDefGhIjKlMnOpQrStUv',
                'telegram_bot_username' => '@GreenwoodOfficialBot',
                'register_webhook' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.telegram_bot_username', 'GreenwoodOfficialBot')
            ->assertJsonPath('data.has_telegram_bot', true);

        $this->greenwood->refresh();
        $this->assertEquals('987654321:XYZ987654321AbcDefGhIjKlMnOpQrStUv', $this->greenwood->telegram_bot_token);
        // Stripped leading @
        $this->assertEquals('GreenwoodOfficialBot', $this->greenwood->telegram_bot_username);
    }

    public function test_submitting_masked_token_preserves_existing_token(): void
    {
        $this->greenwood->update([
            'telegram_bot_token' => '111222333:ORIGINAL_SECRET_KEY_NEVER_OVERWRITE',
            'telegram_bot_username' => 'OriginalBot',
        ]);

        $maskedToken = $this->greenwood->masked_telegram_bot_token;

        $response = $this->actingAs($this->greenwoodAdmin, 'sanctum')
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->putJson('/api/admin/school/telegram', [
                'telegram_bot_token' => $maskedToken,
                'telegram_bot_username' => 'NewUsernameBot',
            ]);

        $response->assertOk();

        $this->greenwood->refresh();
        $this->assertEquals('111222333:ORIGINAL_SECRET_KEY_NEVER_OVERWRITE', $this->greenwood->telegram_bot_token);
        $this->assertEquals('NewUsernameBot', $this->greenwood->telegram_bot_username);
    }

    public function test_school_admin_can_test_telegram_bot_token(): void
    {
        $response = $this->actingAs($this->greenwoodAdmin, 'sanctum')
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->postJson('/api/admin/school/telegram/test', [
                'token' => 'mock_token_123456789:ABCdef',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.result.is_bot', true);
    }

    public function test_school_admin_can_register_webhook(): void
    {
        $this->greenwood->update([
            'telegram_bot_token' => 'mock_token_987654',
            'telegram_bot_username' => 'GreenwoodBot',
        ]);

        $response = $this->actingAs($this->greenwoodAdmin, 'sanctum')
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->postJson('/api/admin/school/telegram/register-webhook');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ok', true);

        $this->assertStringContainsString('/api/telegram/webhook/' . $this->greenwood->id, $response->json('data.url'));
    }

    public function test_non_admin_cannot_access_or_update_school_telegram_settings(): void
    {
        // Teacher
        $this->actingAs($this->teacher, 'sanctum')
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->getJson('/api/admin/school/telegram')
            ->assertForbidden();

        $this->actingAs($this->teacher, 'sanctum')
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->putJson('/api/admin/school/telegram', [
                'telegram_bot_token' => '12345:malicious_token',
                'telegram_bot_username' => 'HackedBot',
            ])
            ->assertForbidden();

        // Parent
        $this->actingAs($this->parent, 'sanctum')
            ->withHeader('X-School-Id', (string) $this->greenwood->id)
            ->putJson('/api/admin/school/telegram', [
                'telegram_bot_token' => '12345:malicious_token',
                'telegram_bot_username' => 'HackedBot',
            ])
            ->assertForbidden();
    }

    public function test_cross_tenant_isolation_school_admin_cannot_update_other_school_bot(): void
    {
        // Greenwood Admin attempts to configure Oakridge bot directly
        $response = $this->actingAs($this->greenwoodAdmin, 'sanctum')
            ->putJson("/api/schools/{$this->oakridge->id}/telegram", [
                'telegram_bot_token' => '12345:cross_tenant_exploit',
                'telegram_bot_username' => 'ExploitBot',
            ]);

        $response->assertForbidden();

        $this->oakridge->refresh();
        $this->assertNotEquals('12345:cross_tenant_exploit', $this->oakridge->telegram_bot_token);
    }

    public function test_super_admin_can_update_any_school_telegram_bot(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/schools/{$this->oakridge->id}/telegram", [
                'telegram_bot_token' => '555666777:OakridgeDedicatedBotKey12345',
                'telegram_bot_username' => '@OakridgeAcademyBot',
                'register_webhook' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.school_id', $this->oakridge->id)
            ->assertJsonPath('data.telegram_bot_username', 'OakridgeAcademyBot');

        $this->oakridge->refresh();
        $this->assertEquals('555666777:OakridgeDedicatedBotKey12345', $this->oakridge->telegram_bot_token);
        $this->assertEquals('OakridgeAcademyBot', $this->oakridge->telegram_bot_username);
    }

    public function test_telegram_service_uses_school_configured_bot_token_and_username(): void
    {
        $this->greenwood->update([
            'telegram_bot_token' => 'fake_greenwood_bot_token',
            'telegram_bot_username' => 'GreenwoodCustomBot',
        ]);

        /** @var TelegramService $service */
        $service = app(TelegramService::class);

        // Generate link code for teacher
        $linkData = $service->generateLinkCode($this->teacher);

        $this->assertEquals('GreenwoodCustomBot', $linkData['bot_username']);
        $this->assertStringStartsWith('https://t.me/GreenwoodCustomBot?start=', $linkData['deep_link']);
    }

    public function test_user_starts_bot_without_code_is_prompted_for_email_or_phone(): void
    {
        $response = $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1001,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => 999888111, 'type' => 'private'],
                'from' => ['id' => 999888111, 'first_name' => 'Anonymous'],
                'text' => '/start',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'prompted_for_contact');

        $this->assertNotEmpty(TelegramService::$sentMessages);
        $lastMsg = end(TelegramService::$sentMessages);
        $this->assertEquals('999888111', $lastMsg['chat_id']);
        $this->assertStringContainsString('Email Address or Phone Number', $lastMsg['text']);
    }

    public function test_parent_replies_with_email_links_telegram_and_receives_parent_specific_info(): void
    {
        // Parent replies with email
        $response = $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1002,
            'message' => [
                'message_id' => 2,
                'chat' => ['id' => 999888222, 'type' => 'private'],
                'from' => ['id' => 999888222, 'first_name' => 'Homer', 'username' => 'homer_simpson'],
                'text' => 'parent@greenwood.edu',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('action', 'linked_by_contact')
            ->assertJsonPath('user_id', $this->parent->id);

        $this->assertDatabaseHas('telegram_accounts', [
            'school_id' => $this->greenwood->id,
            'user_id' => $this->parent->id,
            'telegram_chat_id' => '999888222',
            'is_linked' => true,
        ]);

        $lastMsg = end(TelegramService::$sentMessages);
        $this->assertEquals('999888222', $lastMsg['chat_id']);
        $this->assertStringContainsString('Parent / Guardian', $lastMsg['text']);
        $this->assertStringContainsString('Linked Children', $lastMsg['text']);
        $this->assertStringContainsString('Bart Simpson', $lastMsg['text']);
        $this->assertStringContainsString('Attendance', $lastMsg['text']);
        $this->assertStringContainsString('Report Cards', $lastMsg['text']);
    }

    public function test_parent_replies_with_phone_number_links_telegram(): void
    {
        // Homer's phone in seeder is '+1 (555) 733-4663'
        $response = $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1003,
            'message' => [
                'message_id' => 3,
                'chat' => ['id' => 999888333, 'type' => 'private'],
                'from' => ['id' => 999888333, 'first_name' => 'Homer'],
                'text' => '(555) 733-4663',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('action', 'linked_by_contact')
            ->assertJsonPath('user_id', $this->parent->id);

        $this->assertDatabaseHas('telegram_accounts', [
            'school_id' => $this->greenwood->id,
            'user_id' => $this->parent->id,
            'telegram_chat_id' => '999888333',
            'is_linked' => true,
        ]);
    }

    public function test_student_replies_with_admission_or_email_receives_student_specific_info(): void
    {
        $studentUser = User::where('email', 'bart.simpson@greenwood.edu')->firstOrFail();

        $response = $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1004,
            'message' => [
                'message_id' => 4,
                'chat' => ['id' => 999888444, 'type' => 'private'],
                'from' => ['id' => 999888444, 'first_name' => 'Bart'],
                'text' => 'GRE-25-00101', // Admission number
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('action', 'linked_by_contact')
            ->assertJsonPath('user_id', $studentUser->id);

        $lastMsg = end(TelegramService::$sentMessages);
        $this->assertEquals('999888444', $lastMsg['chat_id']);
        $this->assertStringContainsString('Student', $lastMsg['text']);
        $this->assertStringContainsString('GRE-25-00101', $lastMsg['text']);
        $this->assertStringContainsString('Timetable', $lastMsg['text']);
        $this->assertStringContainsString('Library Book', $lastMsg['text']);
    }

    public function test_teacher_replies_with_email_receives_teacher_specific_info(): void
    {
        $response = $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1005,
            'message' => [
                'message_id' => 5,
                'chat' => ['id' => 999888555, 'type' => 'private'],
                'from' => ['id' => 999888555, 'first_name' => 'Edna'],
                'text' => 'teacher@greenwood.edu',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('action', 'linked_by_contact')
            ->assertJsonPath('user_id', $this->teacher->id);

        $lastMsg = end(TelegramService::$sentMessages);
        $this->assertEquals('999888555', $lastMsg['chat_id']);
        $this->assertStringContainsString('Teacher', $lastMsg['text']);
        $this->assertStringContainsString('Direct Messages from Parents', $lastMsg['text']);
    }

    public function test_unregistered_contact_returns_error_and_prompts_again(): void
    {
        $response = $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1006,
            'message' => [
                'message_id' => 6,
                'chat' => ['id' => 999888666, 'type' => 'private'],
                'from' => ['id' => 999888666, 'first_name' => 'Stranger'],
                'text' => 'unknown.stranger@other.com',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('reason', 'user_not_found');

        $lastMsg = end(TelegramService::$sentMessages);
        $this->assertEquals('999888666', $lastMsg['chat_id']);
        $this->assertStringContainsString('Account Not Found', $lastMsg['text']);
    }

    public function test_already_linked_user_can_view_status_or_unlink(): void
    {
        // 1. Link Homer
        $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1007,
            'message' => [
                'chat' => ['id' => 999888777, 'type' => 'private'],
                'from' => ['id' => 999888777],
                'text' => 'parent@greenwood.edu',
            ],
        ]);

        // 2. Homer sends /status
        $statusRes = $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1008,
            'message' => [
                'chat' => ['id' => 999888777, 'type' => 'private'],
                'from' => ['id' => 999888777],
                'text' => '/status',
            ],
        ]);
        $statusRes->assertOk()->assertJsonPath('status', 'status_sent');

        // 3. Homer sends /unlink
        $unlinkRes = $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1009,
            'message' => [
                'chat' => ['id' => 999888777, 'type' => 'private'],
                'from' => ['id' => 999888777],
                'text' => '/unlink',
            ],
        ]);
        $unlinkRes->assertOk()->assertJsonPath('status', 'unlinked');

        $this->assertDatabaseHas('telegram_accounts', [
            'school_id' => $this->greenwood->id,
            'user_id' => $this->parent->id,
            'is_linked' => false,
        ]);
    }

    public function test_linked_parent_receives_role_targeted_announcement_on_telegram(): void
    {
        TelegramService::clearSentMessages();

        // 1. Link Parent Homer via email
        $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1010,
            'message' => [
                'chat' => ['id' => 999888999, 'type' => 'private'],
                'from' => ['id' => 999888999],
                'text' => 'parent@greenwood.edu',
            ],
        ]);

        TelegramService::clearSentMessages();

        // 2. Publish an announcement specifically for parents
        $announcementService = app(\App\Services\AnnouncementService::class);
        $announcement = $announcementService->createAnnouncement([
            'title' => 'PTA Meeting Tomorrow',
            'body' => 'All parents please gather in the auditorium at 6 PM.',
            'audience_type' => 'role',
            'target_role' => 'parent',
            'priority' => 'high',
            'channels' => ['telegram'],
            'publish_now' => true,
        ], $this->greenwoodAdmin);

        // Check that Telegram message was sent to Homer's chat ID
        $parentMessages = array_filter(TelegramService::$sentMessages, fn ($m) => $m['chat_id'] === '999888999');
        $this->assertNotEmpty($parentMessages);
        $firstParentMsg = reset($parentMessages);
        $this->assertStringContainsString('PTA Meeting Tomorrow', $firstParentMsg['text']);
    }

    public function test_parent_receives_role_keyboard_and_can_prompt_receipt_upload(): void
    {
        TelegramService::clearSentMessages();

        // 1. Link Homer as parent
        $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1020,
            'message' => [
                'chat' => ['id' => 777666555, 'type' => 'private'],
                'from' => ['id' => 777666555],
                'text' => 'parent@greenwood.edu',
            ],
        ]);

        $lastMsg = end(TelegramService::$sentMessages);
        $this->assertArrayHasKey('reply_markup', $lastMsg);
        $keyboard = $lastMsg['reply_markup']['keyboard'];

        // Parent keyboard must contain Upload Receipt and Excuse buttons
        $flatKeyboard = array_merge(...$keyboard);
        $texts = array_column($flatKeyboard, 'text');
        $this->assertContains('💳 Upload Payment Receipt', $texts);
        $this->assertContains('📝 Excuse Child Absence', $texts);
        $this->assertContains('📊 Term Report Cards', $texts);

        // 2. Create an invoice for Homer's child
        app(\App\Tenancy\TenantManager::class)->setTenant($this->greenwood);
        $student = $this->parent->parentProfile->students()->firstOrFail();
        $year = \App\Models\AcademicYear::withoutGlobalScopes()->where('school_id', $this->greenwood->id)->firstOrFail();
        $term = \App\Models\Term::withoutGlobalScopes()->where('school_id', $this->greenwood->id)->firstOrFail();

        $invoice = \App\Models\Invoice::withoutGlobalScopes()->create([
            'school_id' => $this->greenwood->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'term_id' => $term->id,
            'invoice_number' => 'INV-2026-TEST',
            'total_amount' => 2000.00,
            'paid_amount' => 500.00,
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'unpaid',
        ]);

        TelegramService::clearSentMessages();

        // 3. Parent taps "💳 Upload Payment Receipt" button
        $response = $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1021,
            'message' => [
                'chat' => ['id' => 777666555, 'type' => 'private'],
                'from' => ['id' => 777666555],
                'text' => '💳 Upload Payment Receipt',
            ],
        ]);

        $response->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('action', 'receipt_prompt_sent');

        $promptMsg = end(TelegramService::$sentMessages);
        $this->assertStringContainsString('INV-2026-TEST', $promptMsg['text']);
        $this->assertStringContainsString('1,500.00', $promptMsg['text']);
        $this->assertStringContainsString('Upload Bank Receipt', $promptMsg['text']);
    }

    public function test_parent_uploading_receipt_photo_creates_pending_payment_record(): void
    {
        // 1. Link Homer as parent
        $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1025,
            'message' => [
                'chat' => ['id' => 888777111, 'type' => 'private'],
                'from' => ['id' => 888777111],
                'text' => 'parent@greenwood.edu',
            ],
        ]);

        app(\App\Tenancy\TenantManager::class)->setTenant($this->greenwood);
        $student = $this->parent->parentProfile->students()->firstOrFail();
        $year = \App\Models\AcademicYear::withoutGlobalScopes()->where('school_id', $this->greenwood->id)->firstOrFail();
        $term = \App\Models\Term::withoutGlobalScopes()->where('school_id', $this->greenwood->id)->firstOrFail();

        $invoice = \App\Models\Invoice::withoutGlobalScopes()->create([
            'school_id' => $this->greenwood->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'term_id' => $term->id,
            'invoice_number' => 'INV-RECEIPT-99',
            'total_amount' => 1200.00,
            'paid_amount' => 0.00,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'unpaid',
        ]);

        TelegramService::clearSentMessages();

        // 2. Parent sends receipt photo
        $response = $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1026,
            'message' => [
                'chat' => ['id' => 888777111, 'type' => 'private'],
                'from' => ['id' => 888777111],
                'photo' => [
                    ['file_id' => 'thumb_123', 'file_unique_id' => 'u1', 'width' => 90, 'height' => 90],
                    ['file_id' => 'highres_bank_slip_456', 'file_unique_id' => 'u2', 'width' => 1200, 'height' => 1600],
                ],
                'caption' => 'Bank transfer for INV-RECEIPT-99 CBE slip',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('action', 'receipt_uploaded');

        $this->assertDatabaseHas('payments', [
            'school_id' => $this->greenwood->id,
            'invoice_id' => $invoice->id,
            'gateway' => 'telegram',
            'gateway_reference' => 'highres_bank_slip_456',
            'gateway_status' => 'pending_verification',
            'recorded_by' => $this->parent->id,
        ]);

        $reply = end(TelegramService::$sentMessages);
        $this->assertStringContainsString('Receipt Received Successfully', $reply['text']);
        $this->assertStringContainsString('Pending Verification', $reply['text']);
        $this->assertStringContainsString('INV-RECEIPT-99', $reply['text']);
    }

    public function test_parent_submitting_excuse_creates_excused_attendance_record(): void
    {
        // 1. Link Homer as parent
        $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1030,
            'message' => [
                'chat' => ['id' => 666555444, 'type' => 'private'],
                'from' => ['id' => 666555444],
                'text' => 'parent@greenwood.edu',
            ],
        ]);

        app(\App\Tenancy\TenantManager::class)->setTenant($this->greenwood);
        $student = $this->parent->parentProfile->students()->firstOrFail();
        $studentName = $student->user->name;

        TelegramService::clearSentMessages();

        // 2. Parent submits excuse for their child
        $response = $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1031,
            'message' => [
                'chat' => ['id' => 666555444, 'type' => 'private'],
                'from' => ['id' => 666555444],
                'text' => "Excuse {$studentName} - Severe flu and doctor appointment today",
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('action', 'excuse_submitted')
            ->assertJsonPath('student_id', $student->id);

        $today = now()->toDateString();
        $this->assertDatabaseHas('attendance_records', [
            'school_id' => $this->greenwood->id,
            'student_id' => $student->id,
            'date' => $today,
            'status' => 'excused',
            'marked_by' => $this->parent->id,
        ]);

        $reply = end(TelegramService::$sentMessages);
        $this->assertStringContainsString('Absence Excuse Submitted', $reply['text']);
        $this->assertStringContainsString('Excused', $reply['text']);
    }

    public function test_unlinked_user_receives_phone_share_and_instructions_buttons(): void
    {
        TelegramService::clearSentMessages();

        $response = $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1040,
            'message' => [
                'chat' => ['id' => 555444333, 'type' => 'private'],
                'from' => ['id' => 555444333],
                'text' => '/start',
            ],
        ]);

        $response->assertOk()->assertJsonPath('status', 'prompted_for_contact');

        $lastMsg = end(TelegramService::$sentMessages);
        $this->assertArrayHasKey('reply_markup', $lastMsg);
        $keyboard = $lastMsg['reply_markup']['keyboard'];
        $flatKeyboard = array_merge(...$keyboard);

        $this->assertTrue(collect($flatKeyboard)->contains('text', '📱 Share Phone Number'));
        $this->assertTrue(collect($flatKeyboard)->contains('text', 'ℹ️ How to Link Account'));
    }

    public function test_student_receives_student_keyboard_and_can_check_timetable(): void
    {
        $studentUser = User::where('school_id', $this->greenwood->id)->whereHas('student')->firstOrFail();

        // 1. Link student
        $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1050,
            'message' => [
                'chat' => ['id' => 444333222, 'type' => 'private'],
                'from' => ['id' => 444333222],
                'text' => $studentUser->email,
            ],
        ]);

        $lastMsg = end(TelegramService::$sentMessages);
        $flatKeyboard = array_merge(...$lastMsg['reply_markup']['keyboard']);
        $texts = array_column($flatKeyboard, 'text');
        $this->assertContains('📅 My Class Timetable', $texts);
        $this->assertContains('📊 My Term Grades', $texts);
        $this->assertContains('📚 My Library Books', $texts);

        TelegramService::clearSentMessages();

        // 2. Student taps "📅 My Class Timetable"
        $response = $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1051,
            'message' => [
                'chat' => ['id' => 444333222, 'type' => 'private'],
                'from' => ['id' => 444333222],
                'text' => '📅 My Class Timetable',
            ],
        ]);

        $response->assertOk();
        $this->assertNotEmpty(TelegramService::$sentMessages);
        $reply = end(TelegramService::$sentMessages);
        $this->assertStringContainsString('Class Timetable', $reply['text']);
    }

    public function test_teacher_receives_teacher_keyboard_and_can_check_attendance(): void
    {
        // 1. Link teacher
        $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1060,
            'message' => [
                'chat' => ['id' => 333222111, 'type' => 'private'],
                'from' => ['id' => 333222111],
                'text' => $this->teacher->email,
            ],
        ]);

        $lastMsg = end(TelegramService::$sentMessages);
        $flatKeyboard = array_merge(...$lastMsg['reply_markup']['keyboard']);
        $texts = array_column($flatKeyboard, 'text');
        $this->assertContains('📅 My Teaching Timetable', $texts);
        $this->assertContains("📝 Today's Attendance", $texts);
        $this->assertContains('📢 School Bulletins', $texts);

        TelegramService::clearSentMessages();

        // 2. Teacher taps "📝 Today's Attendance"
        $response = $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1061,
            'message' => [
                'chat' => ['id' => 333222111, 'type' => 'private'],
                'from' => ['id' => 333222111],
                'text' => "📝 Today's Attendance",
            ],
        ]);

        $response->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('action', 'attendance_summary_sent');
        $reply = end(TelegramService::$sentMessages);
        $this->assertStringContainsString("Today's Attendance Summary", $reply['text']);
    }

    public function test_admin_receives_admin_keyboard_and_can_check_metrics(): void
    {
        // 1. Link admin
        $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1070,
            'message' => [
                'chat' => ['id' => 222111000, 'type' => 'private'],
                'from' => ['id' => 222111000],
                'text' => $this->greenwoodAdmin->email,
            ],
        ]);

        $lastMsg = end(TelegramService::$sentMessages);
        $flatKeyboard = array_merge(...$lastMsg['reply_markup']['keyboard']);
        $texts = array_column($flatKeyboard, 'text');
        $this->assertContains('📊 School Summary Metrics', $texts);
        $this->assertContains('⚙️ Bot Settings', $texts);

        TelegramService::clearSentMessages();

        // 2. Admin taps "📊 School Summary Metrics"
        $response = $this->postJson("/api/telegram/webhook/{$this->greenwood->id}", [
            'update_id' => 1071,
            'message' => [
                'chat' => ['id' => 222111000, 'type' => 'private'],
                'from' => ['id' => 222111000],
                'text' => '📊 School Summary Metrics',
            ],
        ]);

        $response->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('action', 'metrics_sent');
        $reply = end(TelegramService::$sentMessages);
        $this->assertStringContainsString('Summary Metrics', $reply['text']);
    }
}

