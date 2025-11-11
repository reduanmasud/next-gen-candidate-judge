<?php

namespace App\Enums;

enum TaskUserLockStatus: string
{
    case PENALTY = 'penalty';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PENALTY => 'Penalty',
            self::COMPLETED => 'Completed',
        };
    }
}
