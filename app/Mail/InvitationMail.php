<?php

namespace App\Mail;

use App\Mail\Concerns\ScopesTenantForMail;
use App\Models\Invitation;
use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, ScopesTenantForMail, SerializesModels;

    public function __construct(
        public Invitation $invitation,
        public ?School $school = null,
        public ?string $roleLabel = null,
        public ?string $temporaryPassword = null,
        public ?string $inviterName = null,
        public ?string $recipientName = null
    ) {
        $this->school = $school ?? $invitation->school;
    }

    public function envelope(): Envelope
    {
        $this->ensureTenantContext($this->invitation->school_id);

        $schoolName = $this->school?->name ?: 'Bina Schools';
        $role = $this->roleLabel ?: ucfirst(str_replace('_', ' ', $this->invitation->role));

        return new Envelope(
            subject: "[{$schoolName}] Invitation to join as {$role}",
        );
    }

    public function content(): Content
    {
        $this->ensureTenantContext($this->invitation->school_id);

        $schoolName = $this->school?->name ?: 'Bina Schools';
        $role = $this->roleLabel ?: ucfirst(str_replace('_', ' ', $this->invitation->role));
        $inviteUrl = url('/register?invitation_token=' . $this->invitation->token . '&email=' . urlencode($this->invitation->email));
        $loginUrl = url('/login?email=' . urlencode($this->invitation->email));

        return new Content(
            view: 'emails.invitation',
            with: [
                'invitation' => $this->invitation,
                'school' => $this->school,
                'schoolName' => $schoolName,
                'roleLabel' => $role,
                'inviteUrl' => $inviteUrl,
                'loginUrl' => $loginUrl,
                'temporaryPassword' => $this->temporaryPassword,
                'inviterName' => $this->inviterName ?: 'The School Administration',
                'recipientName' => $this->recipientName ?: 'Colleague / Guardian',
            ],
        );
    }

    public function render(): string
    {
        $this->ensureTenantContext($this->invitation->school_id);

        return parent::render();
    }
}
