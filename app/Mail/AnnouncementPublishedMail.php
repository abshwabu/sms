<?php

namespace App\Mail;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnnouncementPublishedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Announcement $announcement,
        public User $recipient
    ) {}

    public function envelope(): Envelope
    {
        $schoolName = $this->announcement->school?->name ?: 'Bina Schools';

        return new Envelope(
            subject: "[{$schoolName}] {$this->announcement->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.announcement',
        );
    }
}
