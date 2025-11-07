<?php

namespace App\Services\JudgeServices;

use App\Interfaces\SolutionCheckerInterface;
use App\Models\Task;
use App\Models\UserTaskAttempt;

class AutoJudgeService implements SolutionCheckerInterface
{
    public function check(Task $task, UserTaskAttempt $attempt): array|string
    {
        return 'Not implemented';
    }
}