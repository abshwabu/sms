<?php

namespace Tests\Feature;

use App\Mail\AnnouncementPublishedMail;
use App\Mail\InvitationMail;
use App\Mail\ReportCardPublishedMail;
use App\Mail\StudentAbsenceMail;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\Invitation;
use App\Models\ParentProfile;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
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
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class UnifiedEmailAndInvitationPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected User $greenwoodAdmin;
    protected User $ednaTeacher;
    protected User $homerParent;
    protected Student $bartStudent;

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
        $this->bartStudent = Student::where('admission_number', 'GRE-25-00101')->firstOrFail();
    }

    protected function actingAsTenant(User $user): static
    {
        $this->app['auth']->forgetGuards();
        app(\App\Tenancy\TenantManager::class)->setTenant($this->greenwood);
        $token = $user->createToken('test-token')->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $this->greenwood->id);
    }

    public function test_admin_can_invite_staff_and_dispatches_invitation_email(): void
    {
        Mail::fake();

        $payload = [
            'email' => 'newfaculty@greenwood.edu',
            'role' => 'teacher',
        ];

        $response = $this->actingAsTenant($this->greenwoodAdmin)
            ->postJson('/api/admin/invitations', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'newfaculty@greenwood.edu');

        $this->assertDatabaseHas('invitations', [
            'school_id' => $this->greenwood->id,
            'email' => 'newfaculty@greenwood.edu',
            'role' => 'teacher',
        ]);

        Mail::assertQueued(InvitationMail::class, function (InvitationMail $mail) {
            return $mail->hasTo('newfaculty@greenwood.edu')
                && $mail->roleLabel === 'Teacher / Faculty Staff';
        });
    }

    public function test_admin_can_invite_parent_and_dispatches_invitation_email_with_temporary_password(): void
    {
        Mail::fake();

        $payload = [
            'email' => 'newparent@somedomain.org',
            'name' => 'Ned Flanders',
            'student_id' => $this->bartStudent->id,
            'relationship' => 'guardian',
        ];

        $response = $this->actingAsTenant($this->greenwoodAdmin)
            ->postJson('/api/parents/invite', $payload);

        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('data.temporary_password'));
        $this->assertNotEmpty($response->json('data.invitation_token'));

        $this->assertDatabaseHas('users', [
            'school_id' => $this->greenwood->id,
            'email' => 'newparent@somedomain.org',
            'role' => 'parent',
        ]);

        Mail::assertQueued(InvitationMail::class, function (InvitationMail $mail) {
            return $mail->hasTo('newparent@somedomain.org')
                && ! empty($mail->temporaryPassword)
                && $mail->recipientName === 'Ned Flanders';
        });
    }

    public function test_forgot_password_dispatches_reset_email_without_crashing(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'admin@greenwood.edu',
        ]);

        $response->assertStatus(200);

        Notification::assertSentTo(
            $this->greenwoodAdmin,
            ResetPassword::class,
            function (ResetPassword $notification) {
                // Verify notification has valid reset token
                return ! empty($notification->token);
            }
        );
    }

    public function test_staff_creation_auto_generates_unique_email_when_omitted(): void
    {
        $payload = [
            'name' => 'Professor Albus Percival',
            'role_title' => 'Senior Lecturer',
            'department' => 'Science',
        ];

        $response = $this->actingAsTenant($this->greenwoodAdmin)
            ->postJson('/api/staff', $payload);

        $response->assertStatus(201);

        $createdEmail = $response->json('data.user.email');
        $this->assertNotEmpty($createdEmail);
        $this->assertStringContainsString('@greenwood.edu', $createdEmail);
        $this->assertStringContainsString('professor.albus.percival', $createdEmail);

        $this->assertDatabaseHas('users', [
            'school_id' => $this->greenwood->id,
            'email' => $createdEmail,
        ]);
    }

    public function test_parent_creation_auto_generates_unique_email_when_omitted(): void
    {
        $payload = [
            'name' => 'Luann Van Houten',
            'relationship' => 'mother',
            'student_id' => $this->bartStudent->id,
        ];

        $response = $this->actingAsTenant($this->greenwoodAdmin)
            ->postJson('/api/parents', $payload);

        $response->assertStatus(201);

        $createdEmail = $response->json('data.user.email');
        $this->assertNotEmpty($createdEmail);
        $this->assertStringContainsString('@greenwood.edu', $createdEmail);
        $this->assertStringContainsString('luann.van.houten', $createdEmail);

        $this->assertDatabaseHas('users', [
            'school_id' => $this->greenwood->id,
            'email' => $createdEmail,
        ]);
    }

    public function test_mailables_render_safely_even_without_active_tenant_in_queue(): void
    {
        // 1. StudentAbsenceMail render without tenant
        $attendance = AttendanceRecord::first();
        if (! $attendance) {
            $attendance = AttendanceRecord::withoutGlobalScopes()->create([
                'school_id' => $this->greenwood->id,
                'student_id' => $this->bartStudent->id,
                'section_id' => $this->bartStudent->current_section_id,
                'date' => '2025-10-21',
                'status' => 'absent',
            ]);
        }

        $absenceMail = new StudentAbsenceMail($this->bartStudent, $attendance, $this->homerParent);
        $serializedAbsence = serialize($absenceMail);

        // Clear tenant context to simulate queue worker
        app(\App\Tenancy\TenantManager::class)->clearTenant();
        /** @var StudentAbsenceMail $unserializedAbsence */
        $unserializedAbsence = unserialize($serializedAbsence);

        $htmlAbsence = $unserializedAbsence->render();
        $this->assertNotEmpty($htmlAbsence);
        $this->assertStringContainsString('Bart Simpson', $htmlAbsence);

        // 2. ReportCardPublishedMail render without tenant
        app(\App\Tenancy\TenantManager::class)->setTenant($this->greenwood);
        $reportCard = ReportCard::withoutGlobalScopes()->where('school_id', $this->greenwood->id)->first();
        if (! $reportCard) {
            $term = AcademicTerm::withoutGlobalScopes()->where('school_id', $this->greenwood->id)->first();
            $year = AcademicYear::withoutGlobalScopes()->where('school_id', $this->greenwood->id)->first();
            $reportCard = ReportCard::withoutGlobalScopes()->create([
                'school_id' => $this->greenwood->id,
                'student_id' => $this->bartStudent->id,
                'academic_year_id' => $year->id,
                'academic_term_id' => $term->id,
                'overall_grade' => 'A',
                'average_percentage' => 92.5,
                'status' => 'published',
            ]);
        }

        $reportMail = new ReportCardPublishedMail($reportCard, $this->homerParent);
        $serializedReport = serialize($reportMail);

        app(\App\Tenancy\TenantManager::class)->clearTenant();
        /** @var ReportCardPublishedMail $unserializedReport */
        $unserializedReport = unserialize($serializedReport);

        $htmlReport = $unserializedReport->render();
        $this->assertNotEmpty($htmlReport);
        $this->assertStringContainsString('Report Card', $htmlReport);

        // 3. AnnouncementPublishedMail render without tenant
        app(\App\Tenancy\TenantManager::class)->setTenant($this->greenwood);
        $announcement = Announcement::withoutGlobalScopes()->where('school_id', $this->greenwood->id)->first();
        if (! $announcement) {
            $announcement = Announcement::withoutGlobalScopes()->create([
                'school_id' => $this->greenwood->id,
                'author_id' => $this->greenwoodAdmin->id,
                'title' => 'Quarterly Meeting',
                'body' => 'Meeting scheduled for tomorrow at auditorium.',
                'audience_type' => 'all',
                'priority' => 'normal',
                'channels' => ['in_app', 'email'],
                'published_at' => now(),
            ]);
        }

        $annMail = new AnnouncementPublishedMail($announcement, $this->homerParent);
        $serializedAnn = serialize($annMail);

        app(\App\Tenancy\TenantManager::class)->clearTenant();
        /** @var AnnouncementPublishedMail $unserializedAnn */
        $unserializedAnn = unserialize($serializedAnn);

        $htmlAnn = $unserializedAnn->render();
        $this->assertNotEmpty($htmlAnn);
        $this->assertStringContainsString($announcement->title, $htmlAnn);

        // 4. InvitationMail render without tenant
        $invitation = Invitation::withoutGlobalScopes()->where('school_id', $this->greenwood->id)->first();
        if (! $invitation) {
            $invitation = Invitation::withoutGlobalScopes()->create([
                'school_id' => $this->greenwood->id,
                'email' => 'sample@domain.edu',
                'role' => 'teacher',
                'token' => Invitation::generateToken(),
                'invited_by' => $this->greenwoodAdmin->id,
                'expires_at' => now()->addDays(7),
            ]);
        }

        $invMail = new InvitationMail($invitation, $this->greenwood, 'Teacher / Faculty Staff', 'TempPass123!', 'Principal Skinner', 'Seymour');
        $serializedInv = serialize($invMail);

        app(\App\Tenancy\TenantManager::class)->clearTenant();
        /** @var InvitationMail $unserializedInv */
        $unserializedInv = unserialize($serializedInv);

        $htmlInv = $unserializedInv->render();
        $this->assertNotEmpty($htmlInv);
        $this->assertStringContainsString('Official School Invitation', $htmlInv);
        $this->assertStringContainsString('TempPass123!', $htmlInv);
    }

    public function test_invited_user_can_accept_invitation_and_activate_account(): void
    {
        $invitation = Invitation::withoutGlobalScopes()->create([
            'school_id' => $this->greenwood->id,
            'email' => 'newhire@greenwood.edu',
            'role' => 'teacher',
            'token' => Invitation::generateToken(),
            'invited_by' => $this->greenwoodAdmin->id,
            'expires_at' => now()->addDays(7),
        ]);

        $payload = [
            'token' => $invitation->token,
            'name' => 'Prof. Walter White',
            'password' => 'SecurePass2026!',
            'phone' => '+15559876543',
        ];

        $response = $this->postJson('/api/invitations/accept', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.email', 'newhire@greenwood.edu')
            ->assertJsonPath('data.user.name', 'Prof. Walter White');

        $this->assertNotEmpty($response->json('data.token'));

        $this->assertDatabaseHas('users', [
            'school_id' => $this->greenwood->id,
            'email' => 'newhire@greenwood.edu',
            'name' => 'Prof. Walter White',
            'role' => 'teacher',
        ]);

        $this->assertTrue($invitation->fresh()->isAccepted());
    }
}
