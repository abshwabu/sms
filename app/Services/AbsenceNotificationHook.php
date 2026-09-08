<?php

namespace App\Services;

use App\Events\StudentMarkedAbsent;
use App\Models\AttendanceRecord;
use App\Models\Student;
use Illuminate\Support\Facades\Log;

class AbsenceNotificationHook
{
    /**
     * Optional custom callback handlers registered for absence notifications.
     */
    protected static array $handlers = [];

    /**
     * Register a callback to be triggered when a student is marked absent.
     */
    public static function registerHandler(callable $handler): void
    {
        static::$handlers[] = $handler;
    }

    /**
     * Trigger absence notification hook for a student and attendance record.
     */
    public static function trigger(Student $student, AttendanceRecord $record): array
    {
        $student->loadMissing(['user:id,name,email', 'parents.user:id,name,email,phone']);

        $parents = $student->parents;
        $primaryContacts = $parents->filter(fn ($p) => (bool) $p->pivot->is_primary_contact);
        $notifiedParents = ($primaryContacts->isNotEmpty() ? $primaryContacts : $parents)->values();

        $notificationPayload = [
            'student_id' => $student->id,
            'student_name' => $student->user?->name,
            'date' => $record->date instanceof \DateTimeInterface ? $record->date->format('Y-m-d') : (string) $record->date,
            'status' => $record->status,
            'remarks' => $record->remarks,
            'notified_recipients' => $notifiedParents->map(fn ($p) => [
                'parent_id' => $p->id,
                'name' => $p->user?->name,
                'email' => $p->user?->email,
                'phone' => $p->phone ?? $p->user?->phone,
                'relationship' => $p->pivot->relationship,
                'is_primary' => (bool) $p->pivot->is_primary_contact,
            ])->all(),
            'triggered_at' => now()->toIso8601String(),
        ];

        Log::info(sprintf(
            '[AbsenceNotificationHook] Student %s (%s) marked absent on %s. Triggered %d recipient hooks.',
            $student->id,
            $student->user?->name ?? 'Unknown',
            $notificationPayload['date'],
            count($notificationPayload['notified_recipients'])
        ), $notificationPayload);

        // Dispatch domain event
        event(new StudentMarkedAbsent($student, $record, $record->marker, $record->remarks));

        // Execute any registered custom hooks
        foreach (static::$handlers as $handler) {
            try {
                $handler($student, $record, $notificationPayload);
            } catch (\Throwable $e) {
                Log::error('[AbsenceNotificationHook] Custom handler error: ' . $e->getMessage());
            }
        }

        return $notificationPayload;
    }
}
