<?php

namespace App\Enums;

enum AttemptTaskStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case ATTEMPTED_FAILED = 'attempted_failed'; // This is not a typo, it's the correct spelling
    case TERMINATED = 'terminated';
    case EVALUATING = 'evaluating';
    case LOCKED = 'locked';
    case DONE = 'done';
    case STARTED = 'started';
    case PREPARING = 'preparing';


    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Preparing',
            self::RUNNING => 'In Progress',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::ATTEMPTED_FAILED => 'Attempted Failed',
            self::TERMINATED => 'Terminated',
            self::EVALUATING => 'Evaluating',
            self::LOCKED => 'Locked',
            self::DONE => 'Done',
            self::STARTED => 'Started',
            self::PREPARING => 'Preparing',
        };
    }
}
