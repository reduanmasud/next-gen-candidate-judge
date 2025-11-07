<?php

namespace App\Interfaces;

use App\Models\Task;
use App\Models\UserTaskAttempt;

interface SolutionCheckerInterface
{
    public function check(Task $task, UserTaskAttempt $attempt): array|string;
}
