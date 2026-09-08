<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case PRESENT = 'present';
    case ABSENT = 'absent';
    case LATE = 'late';
    case EXCUSED = 'excused';

    public function isAttended(): bool
    {
        return in_array($this, [self::PRESENT, self::LATE], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::PRESENT => 'Present',
            self::ABSENT => 'Absent',
            self::LATE => 'Late / Tardy',
            self::EXCUSED => 'Excused Absence',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
