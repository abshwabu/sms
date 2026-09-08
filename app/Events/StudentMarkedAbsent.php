<?php

namespace App\Events;

use App\Models\AttendanceRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentMarkedAbsent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Student $student,
        public AttendanceRecord $attendanceRecord,
        public ?User $markedBy = null,
        public ?string $remarks = null
    ) {}
}
