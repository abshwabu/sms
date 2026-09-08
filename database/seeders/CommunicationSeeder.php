<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\CommunicationMessage;
use App\Models\CommunicationThread;
use App\Models\GradeLevel;
use App\Models\InAppNotification;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Tenancy\TenantManager;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CommunicationSeeder extends Seeder
{
    public function run(): void
    {
        $tenantManager = app(TenantManager::class);

        $greenwood = School::where('subdomain', 'greenwood')->first();
        $oakridge = School::where('subdomain', 'oakridge')->first();
        $maplewood = School::where('subdomain', 'maplewood')->first();

        if ($greenwood) {
            $this->seedGreenwoodCommunications($greenwood, $tenantManager);
        }

        if ($oakridge) {
            $this->seedOakridgeCommunications($oakridge, $tenantManager);
        }

        if ($maplewood) {
            $this->seedMaplewoodCommunications($maplewood, $tenantManager);
        }
    }

    protected function seedGreenwoodCommunications(School $school, TenantManager $tenantManager): void
    {
        $tenantManager->setTenant($school);

        // 1. Update Telegram Bot settings for school
        $school->update([
            'telegram_bot_token' => 'mock_greenwood_bot_token',
            'telegram_bot_username' => 'GreenwoodHighBot',
        ]);

        $admin = User::where('email', 'admin@greenwood.edu')->first();
        $edna = User::where('email', 'teacher@greenwood.edu')->first();
        $homer = User::where('email', 'parent@greenwood.edu')->first();
        $bart = Student::where('admission_number', 'GRE-25-00101')->first();

        $grade10 = GradeLevel::where('school_id', $school->id)->where('code', 'G10')->first();
        $grade11 = GradeLevel::where('school_id', $school->id)->where('code', 'G11')->first();
        $sectionA = Section::where('school_id', $school->id)->where('name', 'Section A')->first();

        // 2. Announcements
        // Announcement A: School-wide
        Announcement::updateOrCreate(
            [
                'school_id' => $school->id,
                'title' => 'Welcome to Academic Year 2025/2026!',
            ],
            [
                'author_id' => $admin?->id ?: 1,
                'body' => "Welcome students, parents, and faculty to the new academic year at Greenwood High! Please review the campus calendar for upcoming orientations and events.",
                'audience_type' => 'all',
                'priority' => 'normal',
                'channels' => ['in_app', 'email', 'telegram'],
                'published_at' => Carbon::now()->subDays(5),
            ]
        );

        // Announcement B: Grade 10 Specific
        if ($grade10) {
            Announcement::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'title' => 'Grade 10 Annual Science Fair Information',
                ],
                [
                    'author_id' => $edna?->id ?: $admin?->id,
                    'body' => 'All Grade 10 students are required to submit their science fair project proposals by next Friday. Lab materials will be available in Room 204.',
                    'audience_type' => 'grade_level',
                    'grade_level_id' => $grade10->id,
                    'priority' => 'high',
                    'channels' => ['in_app', 'email', 'telegram'],
                    'published_at' => Carbon::now()->subDays(2),
                ]
            );
        }

        // Announcement C: Grade 11 Specific (Bart & Homer shouldn't see this)
        if ($grade11) {
            Announcement::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'title' => 'Grade 11 Senior College Preparatory Seminar',
                ],
                [
                    'author_id' => $admin?->id ?: 1,
                    'body' => 'Registration is now open for the Grade 11 college readiness and SAT prep seminar. Parents and juniors are invited to attend.',
                    'audience_type' => 'grade_level',
                    'grade_level_id' => $grade11->id,
                    'priority' => 'normal',
                    'channels' => ['in_app', 'email'],
                    'published_at' => Carbon::now()->subDays(1),
                ]
            );
        }

        // 3. Direct Communication Thread between Edna (Teacher) and Homer (Parent) regarding Bart
        if ($bart && $edna && $homer) {
            $thread = CommunicationThread::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'student_id' => $bart->id,
                    'subject' => "Bart's History Project & Classroom Progress",
                ],
                [
                    'created_by' => $edna->id,
                    'status' => 'active',
                    'last_message_at' => Carbon::now()->subHours(2),
                ]
            );

            // Initial message from teacher
            CommunicationMessage::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'thread_id' => $thread->id,
                    'sender_id' => $edna->id,
                ],
                [
                    'body' => "Hello Mr. and Mrs. Simpson, Bart has shown great creativity on his history project this week. Please remind him to complete the bibliography portion before Friday.",
                    'created_at' => Carbon::now()->subHours(5),
                ]
            );

            // Reply from parent
            CommunicationMessage::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'thread_id' => $thread->id,
                    'sender_id' => $homer->id,
                ],
                [
                    'body' => "Thanks Ms. Krabappel! We'll make sure he wraps up the bibliography tonight after dinner.",
                    'created_at' => Carbon::now()->subHours(2),
                ]
            );
        }

        // 4. Linked Telegram Account for Homer Simpson
        if ($homer) {
            TelegramAccount::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'user_id' => $homer->id,
                ],
                [
                    'telegram_chat_id' => '987654321',
                    'telegram_username' => 'homer_simpson',
                    'first_name' => 'Homer',
                    'link_code' => 'TG-HOMER77',
                    'link_code_expires_at' => Carbon::now()->addDays(7),
                    'is_linked' => true,
                    'linked_at' => Carbon::now()->subDays(3),
                    'notifications_enabled' => true,
                ]
            );
        }

        // 5. In-App Notifications for Homer
        if ($homer) {
            InAppNotification::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'user_id' => $homer->id,
                    'title' => 'Welcome to Academic Year 2025/2026!',
                ],
                [
                    'type' => 'announcement',
                    'body' => 'Welcome students, parents, and faculty to the new academic year at Greenwood High!',
                    'data' => ['priority' => 'normal'],
                    'read_at' => Carbon::now()->subDays(4),
                ]
            );

            InAppNotification::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'user_id' => $homer->id,
                    'title' => "New Message: Bart's History Project & Classroom Progress",
                ],
                [
                    'type' => 'message',
                    'body' => "Edna Krabappel: Hello Mr. and Mrs. Simpson, Bart has shown great creativity...",
                    'data' => ['student_name' => 'Bart Simpson'],
                    'read_at' => null, // Unread notification
                ]
            );
        }
    }

    protected function seedOakridgeCommunications(School $school, TenantManager $tenantManager): void
    {
        $tenantManager->setTenant($school);

        $school->update([
            'telegram_bot_token' => 'mock_oakridge_bot_token',
            'telegram_bot_username' => 'OakridgeAcademyBot',
        ]);

        $admin = User::where('email', 'admin@oakridge.edu')->first();

        Announcement::updateOrCreate(
            [
                'school_id' => $school->id,
                'title' => 'Oakridge Academy Michaelmas Term Commencement',
            ],
            [
                'author_id' => $admin?->id ?: 1,
                'body' => 'All Oakridge scholars and faculty are welcomed to the Michaelmas term opening banquet.',
                'audience_type' => 'all',
                'priority' => 'high',
                'channels' => ['in_app', 'email'],
                'published_at' => Carbon::now()->subDays(1),
            ]
        );
    }

    protected function seedMaplewoodCommunications(School $school, TenantManager $tenantManager): void
    {
        $tenantManager->setTenant($school);

        $school->update([
            'telegram_bot_token' => 'mock_maplewood_bot_token',
            'telegram_bot_username' => 'MaplewoodElementaryBot',
        ]);

        $admin = User::where('email', 'admin@maplewood.edu')->first();
        $clara = User::where('email', 'teacher@maplewood.edu')->first();
        $sarah = User::where('email', 'parent@maplewood.edu')->first();
        $tommy = Student::where('school_id', $school->id)->where('admission_number', 'MAP-25-00101')->first();

        // 1. School Announcement
        Announcement::updateOrCreate(
            [
                'school_id' => $school->id,
                'title' => 'Welcome to Maplewood Elementary - 2025/2026 School Year!',
            ],
            [
                'author_id' => $admin?->id ?: 1,
                'body' => 'Welcome Maplewood families! We are thrilled to start another wonderful year of elementary learning, friendship, and discovery.',
                'audience_type' => 'all',
                'priority' => 'normal',
                'channels' => ['in_app', 'email'],
                'published_at' => Carbon::now()->subDays(5),
            ]
        );

        // 2. Direct message thread between Clara (teacher) and Sarah (parent)
        if ($clara && $sarah && $tommy) {
            $thread = CommunicationThread::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'student_id' => $tommy->id,
                    'subject' => "Tommy's Reading Progress & Book Club",
                ],
                [
                    'created_by' => $clara->id,
                    'status' => 'active',
                    'last_message_at' => Carbon::now()->subHours(2),
                ]
            );

            CommunicationMessage::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'thread_id' => $thread->id,
                    'sender_id' => $clara->id,
                ],
                [
                    'body' => 'Hi Mrs. Vance, Tommy has been doing wonderfully in our morning reading circles. He chose Charlotte’s Web from the library this week!',
                    'created_at' => Carbon::now()->subHours(5),
                ]
            );

            CommunicationMessage::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'thread_id' => $thread->id,
                    'sender_id' => $sarah->id,
                ],
                [
                    'body' => 'Thank you Ms. Johnson! He is reading it every evening before bed. We really appreciate your encouragement.',
                    'created_at' => Carbon::now()->subHours(2),
                ]
            );

            // In-app notification for Sarah
            InAppNotification::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'user_id' => $sarah->id,
                    'title' => 'Welcome to Academic Year 2025/2026!',
                ],
                [
                    'type' => 'announcement',
                    'body' => 'Welcome Maplewood families to the 2025/2026 school year.',
                    'data' => ['priority' => 'normal'],
                    'read_at' => Carbon::now()->subDays(4),
                ]
            );
        }

        $tenantManager->clearTenant();
    }
}
