<?php

namespace App\Jobs;

use App\Mail\AnnouncementPublishedMail;
use App\Models\Announcement;
use App\Models\NotificationDispatch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAnnouncementEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Announcement $announcement,
        public User $recipient
    ) {}

    /**
     * Execute the job with deduplication protection.
     */
    public function handle(): void
    {
        if (empty($this->recipient->email)) {
            return;
        }

        // Deduplication check: prevent duplicate spam on email channel
        $alreadyDispatched = NotificationDispatch::withoutGlobalScopes()
            ->where('school_id', $this->announcement->school_id)
            ->where('notifiable_type', Announcement::class)
            ->where('notifiable_id', $this->announcement->id)
            ->where('user_id', $this->recipient->id)
            ->where('channel', 'email')
            ->exists();

        if ($alreadyDispatched) {
            return;
        }

        try {
            Mail::to($this->recipient->email)->send(
                new AnnouncementPublishedMail($this->announcement, $this->recipient)
            );

            NotificationDispatch::create([
                'school_id' => $this->announcement->school_id,
                'notifiable_type' => Announcement::class,
                'notifiable_id' => $this->announcement->id,
                'user_id' => $this->recipient->id,
                'channel' => 'email',
                'recipient_address' => $this->recipient->email,
                'status' => 'sent',
                'sent_at' => Carbon::now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed sending announcement email: {$e->getMessage()}", [
                'announcement_id' => $this->announcement->id,
                'recipient_id' => $this->recipient->id,
            ]);
        }
    }
}
