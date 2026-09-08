<?php

namespace App\Mail;

use App\Models\AttendanceRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentAbsenceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Student $student,
        public AttendanceRecord $record,
        public User $parent
    ) {}

    public function envelope(): Envelope
    {
        $schoolName = $this->student->school?->name ?: 'Bina Schools';
        $studentName = $this->student->user?->name ?: 'Your child';
        $dateStr = $this->record->date instanceof \DateTimeInterface
            ? $this->record->date->format('M d, Y')
            : (string) $this->record->date;

        return new Envelope(
            subject: "[{$schoolName}] Absence Alert: {$studentName} marked absent on {$dateStr}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.absence_alert',
        );
    }
}
