<?php
namespace App\Repositories;

use App\Models\Task;
use App\Models\User;

class TaskRepository
{
    public function getTasksForUser(User $user)
    {
        return Task::query()
            ->active()
            ->withUserAttempts($user)
            ->withUserLocks($user)
            ->withUserAttemptCount($user)
            ->get();
    }
}
