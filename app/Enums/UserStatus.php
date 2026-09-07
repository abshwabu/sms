<?php

namespace App\Enums;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INVITED = 'invited';
    case SUSPENDED = 'suspended';

    /**
     * Get all status values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
