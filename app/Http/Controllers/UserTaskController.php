<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Inertia\Response;
use Inertia\Inertia;

class UserTaskController extends Controller
{
    public function index(): Response
    {
        $tasks = Task::where('is_active', true)->get()->map(function ($task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'score' => $task->score,
                'is_started' => false,
                'is_completed' => false,
                'attempt_id' => null,

            ];
        });

        return Inertia::render('user/tasks/index',[
            'tasks' => $tasks,
        ]);
    }
}
