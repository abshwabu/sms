<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Enums\UserStatus;
use App\Jobs\SendAnnouncementEmailJob;
use App\Jobs\SendAnnouncementTelegramJob;
use App\Models\Announcement;
use App\Models\GradeLevel;
use App\Models\InAppNotification;
use App\Models\NotificationDispatch;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnnouncementService
{
    public function __construct(
        protected TelegramService $telegramService
    ) {}

    /**
     * Create a new announcement and dispatch if published immediately.
     */
    public function createAnnouncement(array $data, User $author): Announcement
    {
        return DB::transaction(function () use ($data, $author) {
            $data['school_id'] = $author->school_id;
            $data['author_id'] = $author->id;

            if (empty($data['channels'])) {
                $data['channels'] = ['in_app', 'email', 'telegram'];
            }

            $isPublishing = ! empty($data['publish_now']) || (! empty($data['published_at']) && Carbon::parse($data['published_at'])->isPast());
            unset($data['publish_now']);

            if ($isPublishing && empty($data['published_at'])) {
                $data['published_at'] = Carbon::now();
            }

            $announcement = Announcement::create($data);

            if ($isPublishing) {
                $this->dispatchAnnouncement($announcement);
            }

            return $announcement;
        });
    }

    /**
     * Update an announcement and publish if requested.
     */
    public function updateAnnouncement(Announcement $announcement, array $data): Announcement
    {
        return DB::transaction(function () use ($announcement, $data) {
            $isPublishing = ! empty($data['publish_now']) || (! empty($data['published_at']) && Carbon::parse($data['published_at'])->isPast());
            unset($data['publish_now']);

            if ($isPublishing && ! $announcement->isPublished()) {
                $data['published_at'] = Carbon::now();
            }

            $announcement->update($data);

            if ($isPublishing) {
                $this->dispatchAnnouncement($announcement);
            }

            return $announcement;
        });
    }

    /**
     * Publish an existing draft announcement and dispatch notifications.
     */
    public function publishAnnouncement(Announcement $announcement): Announcement
    {
        if (! $announcement->isPublished()) {
            $announcement->update(['published_at' => Carbon::now()]);
        }

        $this->dispatchAnnouncement($announcement);

        return $announcement;
    }

    /**
     * Resolve all recipient users who match the announcement audience targeting.
     * Acceptance criterion 1: An announcement targeted at "Grade 3" only appears for Grade 3 parents/students/teachers, not the whole school.
     */
    public function getRecipientsForAudience(Announcement $announcement): Collection
    {
        $schoolId = $announcement->school_id;
        $targetRole = $announcement->target_role;
        $audienceType = $announcement->audience_type;

        // Base query for active users in this school
        $baseUsersQuery = User::where('school_id', $schoolId)
            ->where('status', UserStatus::ACTIVE);

        // 1. Audience: School-wide (all)
        if ($audienceType === 'all') {
            if ($targetRole && $targetRole !== 'all') {
                $baseUsersQuery->where('role', $targetRole);
            }
            return $baseUsersQuery->get();
        }

        // 2. Audience: Role specific
        if ($audienceType === 'role') {
            if ($targetRole && $targetRole !== 'all') {
                $baseUsersQuery->where('role', $targetRole);
            }
            return $baseUsersQuery->get();
        }

        // Determine relevant section IDs
        $sectionIds = collect();

        if ($audienceType === 'grade_level' && $announcement->grade_level_id) {
            $sectionIds = Section::where('school_id', $schoolId)
                ->where('grade_level_id', $announcement->grade_level_id)
                ->pluck('id');
        } elseif ($audienceType === 'section' && $announcement->section_id) {
            $sectionIds = collect([$announcement->section_id]);
        }

        if ($sectionIds->isEmpty()) {
            return collect();
        }

        $recipientUsers = collect();

        // A. Students in these sections
        if (! $targetRole || in_array($targetRole, ['all', RoleEnum::STUDENT->value])) {
            $studentUsers = User::where('school_id', $schoolId)
                ->where('role', RoleEnum::STUDENT->value)
                ->where('status', UserStatus::ACTIVE)
                ->whereHas('student', function ($q) use ($sectionIds) {
                    $q->whereIn('current_section_id', $sectionIds);
                })
                ->get();

            $recipientUsers = $recipientUsers->concat($studentUsers);
        }

        // B. Parents linked to students in these sections
        if (! $targetRole || in_array($targetRole, ['all', RoleEnum::PARENT->value])) {
            $parentUsers = User::where('school_id', $schoolId)
                ->where('role', RoleEnum::PARENT->value)
                ->where('status', UserStatus::ACTIVE)
                ->whereHas('parentProfile.students', function ($q) use ($sectionIds) {
                    $q->whereIn('current_section_id', $sectionIds);
                })
                ->get();

            $recipientUsers = $recipientUsers->concat($parentUsers);
        }

        // C. Teachers assigned to these sections (homeroom or subject teachers)
        if (! $targetRole || in_array($targetRole, ['all', RoleEnum::TEACHER->value])) {
            // Homeroom teachers
            $homeroomTeacherIds = Section::whereIn('id', $sectionIds)
                ->whereNotNull('homeroom_teacher_id')
                ->pluck('homeroom_teacher_id');

            // Subject teachers
            $subjectStaffUserIds = Staff::whereHas('subjectTeachers', function ($q) use ($sectionIds) {
                $q->whereIn('section_id', $sectionIds);
            })->pluck('user_id');

            $teacherUserIds = $homeroomTeacherIds->concat($subjectStaffUserIds)->unique()->filter();

            if ($teacherUserIds->isNotEmpty()) {
                $teacherUsers = User::where('school_id', $schoolId)
                    ->whereIn('id', $teacherUserIds)
                    ->where('status', UserStatus::ACTIVE)
                    ->get();

                $recipientUsers = $recipientUsers->concat($teacherUsers);
            }
        }

        return $recipientUsers->unique('id')->values();
    }

    /**
     * Dispatch an announcement to its audience across in-app, email, and telegram channels.
     * Acceptance criterion 3: A parent who links their Telegram account receives the same announcement
     * via Telegram as they would via email, without duplicate spam if both channels are enabled.
     */
    public function dispatchAnnouncement(Announcement $announcement): array
    {
        $recipients = $this->getRecipientsForAudience($announcement);
        $channels = $announcement->channels ?: ['in_app', 'email', 'telegram'];

        $inAppCount = 0;
        $emailCount = 0;
        $telegramCount = 0;

        foreach ($recipients as $recipient) {
            // 1. In-App Notification Center
            if (in_array('in_app', $channels)) {
                $alreadyDispatched = NotificationDispatch::withoutGlobalScopes()
                    ->where('school_id', $announcement->school_id)
                    ->where('notifiable_type', Announcement::class)
                    ->where('notifiable_id', $announcement->id)
                    ->where('user_id', $recipient->id)
                    ->where('channel', 'in_app')
                    ->exists();

                if (! $alreadyDispatched) {
                    InAppNotification::create([
                        'school_id' => $announcement->school_id,
                        'user_id' => $recipient->id,
                        'type' => 'announcement',
                        'title' => $announcement->title,
                        'body' => $announcement->body,
                        'data' => [
                            'announcement_id' => $announcement->id,
                            'priority' => $announcement->priority,
                            'author' => $announcement->author?->name,
                        ],
                    ]);

                    NotificationDispatch::create([
                        'school_id' => $announcement->school_id,
                        'notifiable_type' => Announcement::class,
                        'notifiable_id' => $announcement->id,
                        'user_id' => $recipient->id,
                        'channel' => 'in_app',
                        'recipient_address' => (string) $recipient->id,
                        'status' => 'sent',
                        'sent_at' => Carbon::now(),
                    ]);

                    $inAppCount++;
                }
            }

            // 2. Email Dispatch
            if (in_array('email', $channels) && ! empty($recipient->email)) {
                // If in testing or sync mode, execute directly or queue
                if (app()->environment('testing')) {
                    SendAnnouncementEmailJob::dispatchSync($announcement, $recipient);
                } else {
                    SendAnnouncementEmailJob::dispatch($announcement, $recipient);
                }
                $emailCount++;
            }

            // 3. Telegram Dispatch
            if (in_array('telegram', $channels)) {
                if ($recipient->telegramAccount && $recipient->telegramAccount->is_linked) {
                    if (app()->environment('testing')) {
                        SendAnnouncementTelegramJob::dispatchSync($announcement, $recipient);
                    } else {
                        SendAnnouncementTelegramJob::dispatch($announcement, $recipient);
                    }
                    $telegramCount++;
                }
            }
        }

        return [
            'recipients_count' => $recipients->count(),
            'in_app_count' => $inAppCount,
            'email_count' => $emailCount,
            'telegram_count' => $telegramCount,
        ];
    }
}
