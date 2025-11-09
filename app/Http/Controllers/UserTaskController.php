<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\UserTaskAttempt;
use App\Services\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\Inertia;

class UserTaskController extends Controller
{
    public function __construct(
        protected WorkspaceService $workspace,
    ) {
        //
    }

    public function index(): Response
    {
        $user = auth()->user();

        $tasks = Task::where('is_active', true)
            ->with(['attempts' => function ($query) use ($user) {
                $query->where('user_id', $user->id)->latest();
            }])
            ->get()
            ->map(function ($task) {
                $latestAttempt = $task->attempts->first();
                $isStarted = in_array(optional($latestAttempt)->status, ['pending', 'running'], true);

                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'score' => $task->score,
                    'is_started' => $isStarted,
                    'is_completed' => optional($latestAttempt)->status === 'completed',
                    'attempt_id' => optional($latestAttempt)->id,
                ];
            });

        return Inertia::render('user/tasks/index', [
            'tasks' => $tasks,
        ]);
    }

    public function start(Request $request, Task $task): RedirectResponse
    {
        $user = $request->user();

        // Load the server relationship if it exists
        $task->loadMissing('server');

        // Use database transaction to prevent race condition
        return \DB::transaction(function () use ($user, $task) {
            // Lock the row to prevent concurrent attempts
            $existingAttempt = UserTaskAttempt::query()
                ->where('user_id', $user->id)
                ->where('task_id', $task->id)
                ->whereIn('status', ['pending', 'running'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existingAttempt) {
                return redirect()->route('user-tasks.show', $existingAttempt);
            }

            $attempt = $this->workspace->start($task, $user);

            return redirect()->route('user-tasks.show', $attempt);
        });
    }

    public function show(Request $request, UserTaskAttempt $attempt): Response
    {
        $user = $request->user();

        if ($attempt->user_id !== $user->id) {
            abort(403);
        }

        $attempt->loadMissing('task');
        $task = $attempt->task;

        // Load judge configurations
        $task->load(['aiJudges', 'quizJudges.quizQuestionAnswers', 'textJudges', 'autoJudge']);

        // Get metadata from attempt
        $metadata = $attempt->getAllMeta();

        $terminalConfig = config('services.workspace');
        $terminalProtocol = $terminalConfig['terminal_protocol'] ?? $request->getScheme();
        $terminalHost = $terminalConfig['terminal_host'] ?? $request->getHost();
        $terminalPath = $terminalConfig['terminal_path'] ?? '/';

        $terminalUrl = null;

        if ($attempt->container_port) {
            $baseUrl = sprintf(
                '%s://%s:%d',
                $terminalProtocol,
                $terminalHost,
                $attempt->container_port
            );

            $path = trim($terminalPath, '/');

            $terminalUrl = $path === ''
                ? $baseUrl
                : sprintf('%s/%s', $baseUrl, $path);
        }

        // Prepare judge data based on judge type
        $judgeData = null;
        if ($task->judge_type === 'AiJudge') {
            $judgeData = $task->aiJudges->map(function ($aiJudge) {
                return [
                    'id' => $aiJudge->id,
                    'question' => $aiJudge->question,
                    'prompt' => $aiJudge->prompt,
                ];
            })->toArray();
        } elseif ($task->judge_type === 'QuizJudge') {
            $judgeData = $task->quizJudges->map(function ($quizJudge) {
                return [
                    'id' => $quizJudge->id,
                    'question' => json_decode($quizJudge->questions, true),
                    'options' => $quizJudge->quizQuestionAnswers->map(function ($answer) {
                        return [
                            'id' => $answer->id,
                            'choice' => $answer->choice,
                        ];
                    })->toArray(),
                ];
            })->toArray();
        } elseif ($task->judge_type === 'TextJudge') {
            $judgeData = $task->textJudges->map(function ($textJudge) {
                return [
                    'id' => $textJudge->id,
                    'question' => $textJudge->questions,
                ];
            })->toArray();
        }

        return Inertia::render('user/tasks/show', [
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'score' => $task->score,
                'judge_type' => $task->judge_type,
                'timer' => $task->timer,
                'allowssh' => $task->allowssh,
                'sandbox' => $task->sandbox,
            ],
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'started_at' => optional($attempt->started_at)->toIso8601String(),
                'container_id' => $attempt->container_id,
                'container_name' => $attempt->container_name,
                'container_port' => $attempt->container_port,
                'notes' => $attempt->notes,
            ],
            'metadata' => $metadata,
            'workspace' => [
                'terminal_url' => $terminalUrl,
            ],
            'judgeData' => $judgeData,
        ]);
    }

    public function restart(Request $request, UserTaskAttempt $attempt): RedirectResponse
    {
        $user = $request->user();

        if ($attempt->user_id !== $user->id) {
            abort(403);
        }

        $attempt->loadMissing('task');
        $task = $attempt->task;

        // Terminate the current attempt
        if (in_array($attempt->status, ['pending', 'running'])) {
            $attempt->status = 'terminated';
            $attempt->completed_at = now();
            $attempt->save();
        }

        // Load the server relationship if it exists
        $task->loadMissing('server');

        // Create a new attempt
        $newAttempt = $this->workspace->start($task, $user);

        return redirect()->route('user-tasks.show', $newAttempt);
    }

    public function status(Request $request, UserTaskAttempt $attempt)
    {
        $user = $request->user();

        if ($attempt->user_id !== $user->id) {
            abort(403);
        }

        // Get metadata from attempt
        $metadata = $attempt->getAllMeta();

        return response()->json([
            'status' => $attempt->status,
            'started_at' => optional($attempt->started_at)->toIso8601String(),
            'container_id' => $attempt->container_id,
            'container_name' => $attempt->container_name,
            'container_port' => $attempt->container_port,
            'metadata' => $metadata,
        ]);
    }
}
