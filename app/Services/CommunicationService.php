<?php

namespace App\Services;

use App\Models\CommunicationMessage;
use App\Models\CommunicationThread;
use App\Models\InAppNotification;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommunicationService
{
    /**
     * Start a new direct communication thread regarding a specific student.
     * Acceptance criterion 2: Teacher-parent message thread is scoped to the specific student
     * and visible to both linked parents (if two) plus the relevant teacher(s).
     */
    public function createThread(Student $student, User $creator, string $subject, string $initialMessage): CommunicationThread
    {
        // 1. Verify creator is a participant
        $dummyThread = new CommunicationThread([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'created_by' => $creator->id,
        ]);
        $dummyThread->setRelation('student', $student);

        if (! $dummyThread->isParticipant($creator)) {
            throw new AuthorizationException('You are not authorized to start a communication thread for this student.');
        }

        return DB::transaction(function () use ($student, $creator, $subject, $initialMessage) {
            $thread = CommunicationThread::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'created_by' => $creator->id,
                'subject' => $subject,
                'status' => 'active',
                'last_message_at' => Carbon::now(),
            ]);

            $message = CommunicationMessage::create([
                'school_id' => $student->school_id,
                'thread_id' => $thread->id,
                'sender_id' => $creator->id,
                'body' => $initialMessage,
            ]);

            $this->notifyParticipants($thread, $creator, $initialMessage);

            return $thread->load(['student.user', 'creator', 'messages.sender']);
        });
    }

    /**
     * Send a new message in an existing thread.
     */
    public function sendMessage(CommunicationThread $thread, User $sender, string $body): CommunicationMessage
    {
        if (! $thread->isParticipant($sender)) {
            throw new AuthorizationException('You are not authorized to send messages in this thread.');
        }

        return DB::transaction(function () use ($thread, $sender, $body) {
            $message = CommunicationMessage::create([
                'school_id' => $thread->school_id,
                'thread_id' => $thread->id,
                'sender_id' => $sender->id,
                'body' => $body,
            ]);

            $thread->update(['last_message_at' => Carbon::now()]);

            $this->notifyParticipants($thread, $sender, $body);

            return $message->load('sender');
        });
    }

    /**
     * Resolve all authorized participant users for this student thread.
     * Both linked parents (if two) + all relevant section teachers.
     */
    public function getThreadParticipants(CommunicationThread $thread): Collection
    {
        $student = $thread->student;
        $schoolId = $thread->school_id;

        $participantUserIds = collect();

        // 1. All linked parents of this student (e.g. mother and father)
        $parentUserIds = $student->parents()->pluck('parents.user_id');
        $participantUserIds = $participantUserIds->concat($parentUserIds);

        // 2. Relevant teachers of the student's current section
        $section = $student->currentSection;
        if ($section) {
            if ($section->homeroom_teacher_id) {
                $participantUserIds->push($section->homeroom_teacher_id);
            }

            $subjectTeacherUserIds = $section->subjectTeachers()
                ->with('staff')
                ->get()
                ->pluck('staff.user_id');

            $participantUserIds = $participantUserIds->concat($subjectTeacherUserIds);
        }

        return User::where('school_id', $schoolId)
            ->whereIn('id', $participantUserIds->unique()->filter())
            ->get();
    }

    /**
     * Notify all participants (excluding sender) of a new thread message.
     */
    protected function notifyParticipants(CommunicationThread $thread, User $sender, string $messageBody): void
    {
        $participants = $this->getThreadParticipants($thread);
        $studentName = $thread->student?->user?->name ?: 'Student';

        foreach ($participants as $participant) {
            if ($participant->id === $sender->id) {
                continue;
            }

            InAppNotification::create([
                'school_id' => $thread->school_id,
                'user_id' => $participant->id,
                'type' => 'message',
                'title' => "New Message: {$thread->subject} ({$studentName})",
                'body' => "{$sender->name}: " . Str::limit($messageBody, 80),
                'data' => [
                    'thread_id' => $thread->id,
                    'student_id' => $thread->student_id,
                    'sender_name' => $sender->name,
                ],
            ]);
        }
    }
}
