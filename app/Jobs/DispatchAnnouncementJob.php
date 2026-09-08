<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchAnnouncementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Announcement $announcement
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AnnouncementService $announcementService): void
    {
        $announcementService->dispatchAnnouncement($this->announcement);
    }
}
