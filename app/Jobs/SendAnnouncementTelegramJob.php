<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAnnouncementTelegramJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Announcement $announcement,
        public User $recipient
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TelegramService $telegramService): void
    {
        $telegramService->dispatchAnnouncement($this->announcement, $this->recipient);
    }
}
