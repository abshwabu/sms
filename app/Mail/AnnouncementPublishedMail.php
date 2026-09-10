<?php

namespace App\Mail;

use App\Mail\Concerns\ScopesTenantForMail;
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
    use Queueable, ScopesTenantForMail, SerializesModels;

    public function __construct(
        public Announcement $announcement,
        public User $recipient
    ) {}

    public function envelope(): Envelope
    {
        $this->ensureTenantContext($this->announcement->school_id);

        $schoolName = $this->announcement->school?->name ?: 'Bina Schools';

        return new Envelope(
            subject: "[{$schoolName}] {$this->announcement->title}",
        );
    }

    public function content(): Content
    {
        $this->ensureTenantContext($this->announcement->school_id);

        return new Content(
            view: 'emails.announcement',
        );
    }

    public function render(): string
    {
        $this->ensureTenantContext($this->announcement->school_id);

        return parent::render();
    }
}
