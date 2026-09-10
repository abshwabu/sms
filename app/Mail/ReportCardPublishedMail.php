<?php

namespace App\Mail;

use App\Mail\Concerns\ScopesTenantForMail;
use App\Models\ReportCard;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportCardPublishedMail extends Mailable implements ShouldQueue
{
    use Queueable, ScopesTenantForMail, SerializesModels;

    public function __construct(
        public ReportCard $reportCard,
        public User $parent
    ) {}

    public function envelope(): Envelope
    {
        $this->ensureTenantContext($this->reportCard->school_id);

        $schoolName = $this->reportCard->school?->name ?: 'Bina Schools';
        $studentName = $this->reportCard->student?->user?->name ?: 'Student';
        $termName = $this->reportCard->term?->name ?: 'Term';

        return new Envelope(
            subject: "[{$schoolName}] Official Report Card Published: {$studentName} ({$termName})",
        );
    }

    public function content(): Content
    {
        $this->ensureTenantContext($this->reportCard->school_id);

        return new Content(
            view: 'emails.report_card_published',
        );
    }

    public function render(): string
    {
        $this->ensureTenantContext($this->reportCard->school_id);

        return parent::render();
    }
}
