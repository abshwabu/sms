<?php

namespace App\Services;

use App\Mail\ReportCardPublishedMail;
use App\Mail\StudentAbsenceMail;
use App\Models\AttendanceRecord;
use App\Models\InAppNotification;
use App\Models\Notification;
use App\Models\NotificationDispatch;
use App\Models\NotificationPreference;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationPipelineService
{
    public function __construct(
        protected TenantManager $tenantManager,
        protected TelegramService $telegramService
    ) {}

    /**
     * Unified Trigger 1: Student marked absent -> notify linked parents (in-app + email if enabled).
     * Acceptance criterion: Marking a student absent triggers a parent notification
     * (in-app + email if enabled) within the same request/queue job.
     */
    public function notifyAbsence(Student $student, AttendanceRecord $record): array
    {
        $student->loadMissing(['user:id,name,email', 'currentSection.gradeLevel', 'parents.user:id,name,email,phone']);
        $schoolId = $student->school_id;

        $parents = $student->parents;
        if ($parents->isEmpty()) {
            return [];
        }

        $dateStr = $record->date instanceof \DateTimeInterface
            ? $record->date->format('M d, Y')
            : (string) $record->date;

        $studentName = $student->user?->name ?: "Student #{$student->admission_number}";
        $sectionName = $student->currentSection?->name ?: 'Homeroom';

        $title = "Absence Notice: {$studentName}";
        $body = "{$studentName} was marked absent for daily attendance on {$dateStr} in {$sectionName}.";
        if ($record->remarks) {
            $body .= " (Remarks: {$record->remarks})";
        }

        $payload = [
            'student_id' => $student->id,
            'student_name' => $studentName,
            'admission_number' => $student->admission_number,
            'date' => $dateStr,
            'status' => 'absent',
            'remarks' => $record->remarks,
            'action_url' => '/parents',
        ];

        $results = [];

        foreach ($parents as $parent) {
            $parentUser = $parent->user;
            if (! $parentUser) {
                continue;
            }

            $mailable = new StudentAbsenceMail($student, $record, $parentUser);

            $dispatched = $this->send(
                recipient: $parentUser,
                type: 'attendance_absence',
                title: $title,
                body: $body,
                payload: $payload,
                category: 'attendance_absence',
                mailable: $mailable,
                schoolId: $schoolId,
                notifiableType: AttendanceRecord::class,
                notifiableId: $record->id
            );

            $results[] = [
                'parent_id' => $parent->id,
                'user_id' => $parentUser->id,
                'dispatched' => $dispatched,
            ];
        }

        return $results;
    }

    /**
     * Unified Trigger 2: Report card published -> notify linked parent(s).
     * Acceptance criterion: Publishing a report card notifies the linked parent(s).
     */
    public function notifyReportCardPublished(ReportCard $reportCard): array
    {
        $reportCard->loadMissing(['student.user:id,name,email', 'student.parents.user:id,name,email,phone', 'term', 'academicYear', 'school']);
        $student = $reportCard->student;
        $schoolId = $reportCard->school_id;

        if (! $student) {
            return [];
        }

        $parents = $student->parents;
        if ($parents->isEmpty()) {
            return [];
        }

        $studentName = $student->user?->name ?: "Student #{$student->admission_number}";
        $termName = $reportCard->term?->name ?: 'Current Term';

        $title = "Report Card Published: {$studentName}";
        $body = "The official {$termName} report card for {$studentName} has been published. Overall Grade: {$reportCard->overall_grade} ({$reportCard->average_percentage}%).";

        $payload = [
            'report_card_id' => $reportCard->id,
            'student_id' => $student->id,
            'student_name' => $studentName,
            'term_name' => $termName,
            'overall_grade' => $reportCard->overall_grade,
            'average_percentage' => $reportCard->average_percentage,
            'gpa' => $reportCard->gpa,
            'action_url' => '/parents',
        ];

        $results = [];

        foreach ($parents as $parent) {
            $parentUser = $parent->user;
            if (! $parentUser) {
                continue;
            }

            $mailable = new ReportCardPublishedMail($reportCard, $parentUser);

            $dispatched = $this->send(
                recipient: $parentUser,
                type: 'report_card_published',
                title: $title,
                body: $body,
                payload: $payload,
                category: 'report_card_published',
                mailable: $mailable,
                schoolId: $schoolId,
                notifiableType: ReportCard::class,
                notifiableId: $reportCard->id
            );

            $results[] = [
                'parent_id' => $parent->id,
                'user_id' => $parentUser->id,
                'dispatched' => $dispatched,
            ];
        }

        return $results;
    }

    /**
     * Core multi-channel dispatch delivery pipeline.
     * Channels: in-app (always), email (configurable per user), telegram (if linked and enabled),
     * SMS/push hook points.
     */
    public function send(
        User $recipient,
        string $type,
        string $title,
        string $body,
        array $payload = [],
        ?string $category = null,
        ?Mailable $mailable = null,
        ?int $schoolId = null,
        ?string $notifiableType = null,
        ?int $notifiableId = null
    ): array {
        $resolvedSchoolId = $schoolId
            ?: $recipient->school_id
            ?: $this->tenantManager->getTenantId()
            ?: 1;

        $channelsDispatched = [];

        // 1. In-App Channel (ALWAYS delivered)
        $notification = Notification::withoutGlobalScopes()->create([
            'school_id' => $resolvedSchoolId,
            'user_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'payload' => $payload,
        ]);

        // Keep InAppNotification synchronized for header notification center
        InAppNotification::withoutGlobalScopes()->create([
            'school_id' => $resolvedSchoolId,
            'user_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $payload,
        ]);

        $channelsDispatched['in_app'] = true;

        $targetType = $notifiableType ?: Notification::class;
        $targetId = $notifiableId ?: $notification->id;

        $this->recordDispatch($resolvedSchoolId, $targetType, $targetId, $recipient->id, 'in_app', 'sent');

        // Resolve user preferences
        $pref = NotificationPreference::forUser($recipient, $resolvedSchoolId);

        // 2. Email Channel (configurable per user)
        if ($mailable && ! empty($recipient->email) && $pref->shouldSend('email', $category)) {
            if (! $this->isAlreadyDispatched($resolvedSchoolId, $targetType, $targetId, $recipient->id, 'email')) {
                try {
                    Mail::to($recipient->email)->send($mailable);
                    $this->recordDispatch($resolvedSchoolId, $targetType, $targetId, $recipient->id, 'email', 'sent', $recipient->email);
                    $channelsDispatched['email'] = true;
                } catch (\Throwable $e) {
                    Log::error('[NotificationPipeline] Email dispatch failed: ' . $e->getMessage());
                    $channelsDispatched['email'] = false;
                }
            } else {
                $channelsDispatched['email'] = 'already_dispatched';
            }
        } else {
            $channelsDispatched['email'] = false;
        }

        // 3. Telegram Channel (configurable per user + requires linked account)
        if ($pref->shouldSend('telegram', $category)) {
            $recipient->loadMissing('telegramAccount');
            $telegram = $recipient->telegramAccount;

            if ($telegram && $telegram->is_linked && $telegram->notifications_enabled && ! empty($telegram->telegram_chat_id)) {
                if (! $this->isAlreadyDispatched($resolvedSchoolId, $targetType, $targetId, $recipient->id, 'telegram')) {
                    $telegramMessage = "📢 <b>{$title}</b>\n\n{$body}";
                    $sent = $this->telegramService->sendMessage($resolvedSchoolId, (int) $telegram->telegram_chat_id, $telegramMessage);
                    if ($sent) {
                        $this->recordDispatch($resolvedSchoolId, $targetType, $targetId, $recipient->id, 'telegram', 'sent', $telegram->telegram_chat_id);
                        $channelsDispatched['telegram'] = true;
                    } else {
                        $channelsDispatched['telegram'] = false;
                    }
                } else {
                    $channelsDispatched['telegram'] = 'already_dispatched';
                }
            }
        }

        // 4. SMS Hook Point
        if ($pref->shouldSend('sms', $category) && ! empty($recipient->phone)) {
            $smsDispatched = SmsNotificationHook::dispatch($recipient, "{$title}: {$body}", $payload);
            $channelsDispatched['sms'] = $smsDispatched;
        }

        // 5. Mobile Push Hook Point
        if ($pref->shouldSend('push', $category)) {
            $pushDispatched = PushNotificationHook::dispatch($recipient, $title, $body, $payload);
            $channelsDispatched['push'] = $pushDispatched;
        }

        return [
            'notification_id' => $notification->id,
            'channels' => $channelsDispatched,
        ];
    }

    /**
     * Check if a dispatch for this notifiable, user, and channel already occurred.
     */
    protected function isAlreadyDispatched(int $schoolId, string $notifiableType, int $notifiableId, int $userId, string $channel): bool
    {
        return NotificationDispatch::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->where('user_id', $userId)
            ->where('channel', $channel)
            ->exists();
    }

    /**
     * Record a channel dispatch in the deduplication ledger.
     */
    protected function recordDispatch(
        int $schoolId,
        string $notifiableType,
        int $notifiableId,
        int $userId,
        string $channel,
        string $status = 'sent',
        ?string $recipientAddress = null
    ): ?NotificationDispatch {
        try {
            return NotificationDispatch::withoutGlobalScopes()->create([
                'school_id' => $schoolId,
                'notifiable_type' => $notifiableType,
                'notifiable_id' => $notifiableId,
                'user_id' => $userId,
                'channel' => $channel,
                'recipient_address' => $recipientAddress,
                'status' => $status,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
