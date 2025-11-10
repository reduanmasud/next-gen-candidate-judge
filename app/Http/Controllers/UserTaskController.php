<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskUserLock;
use App\Models\UserTaskAttempt;
use App\Models\UserTaskAttemptAnswer;
use App\Services\JudgeServices\AiJudgeService;
use App\Services\JudgeServices\QuizJudgeService;
use App\Services\JudgeServices\TextJudgeService;
use App\Services\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
            ->with(['lockedUsers' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }])
            ->get()
            ->map(function ($task) use ($user) {
                $latestAttempt = $task->attempts->first();
                $isStarted = in_array(optional($latestAttempt)->status, ['pending', 'running'], true);
                $isLocked = $task->lockedUsers->isNotEmpty();

                // Count total attempts for this user and task
                $attemptCount = UserTaskAttempt::where('user_id', $user->id)
                    ->where('task_id', $task->id)
                    ->count();

                // Check if task was completed successfully (all questions correct)
                // A task is completed successfully if:
                // 1. Latest attempt status is 'completed'
                // 2. The score equals the maximum possible score for that attempt
                $isCompletedSuccessfully = false;
                if (optional($latestAttempt)->status === 'completed') {
                    $attemptNumber = UserTaskAttempt::where('user_id', $user->id)
                        ->where('task_id', $task->id)
                        ->where('id', '<=', $latestAttempt->id)
                        ->count();
                    $maxPossibleScore = $this->calculateMaxScore($task->score, $attemptNumber);
                    $isCompletedSuccessfully = $latestAttempt->score >= $maxPossibleScore;
                }

                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'score' => $task->score,
                    'is_started' => $isStarted,
                    'is_completed' => optional($latestAttempt)->status === 'completed',
                    'is_locked' => $isLocked,
                    'is_completed_successfully' => $isCompletedSuccessfully,
                    'attempt_id' => optional($latestAttempt)->id,
                    'attempt_count' => $attemptCount,
                    'sandbox' => $task->sandbox,
                    'allowssh' => $task->allowssh,
                ];
            });

        return Inertia::render('user/tasks/index', [
            'tasks' => $tasks,
        ]);
    }

    public function start(Request $request, Task $task): RedirectResponse
    {
        $user = $request->user();

        // Check if task is locked for this user
        if ($task->isLockedForUser($user)) {
            return redirect()->route('user-tasks.index')
                ->with('error', 'This task is locked for you. You cannot attempt it again.');
        }

        // Check if the next attempt would have a max score below 20% threshold
        $attemptCount = UserTaskAttempt::where('user_id', $user->id)
            ->where('task_id', $task->id)
            ->count();

        $nextAttemptNumber = $attemptCount + 1;
        $nextAttemptMaxScore = $this->calculateMaxScore($task->score, $nextAttemptNumber);
        $lockingThreshold = $task->score * 0.2;

        if ($nextAttemptMaxScore < $lockingThreshold) {
            // Create lock record if it doesn't exist
            TaskUserLock::firstOrCreate(
                [
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                ],
                [
                    'reason' => sprintf(
                        'Task locked: Maximum possible score for attempt #%d would be %.2f points, which is below the 20%% threshold (%.2f points required)',
                        $nextAttemptNumber,
                        $nextAttemptMaxScore,
                        $lockingThreshold
                    ),
                ]
            );

            return redirect()->route('user-tasks.index')
                ->with('error', sprintf(
                    'This task is locked. You have made %d attempts and the maximum possible score for the next attempt would be %.2f points, which is below the 20%% threshold (%.2f points required).',
                    $attemptCount,
                    $nextAttemptMaxScore,
                    $lockingThreshold
                ));
        }

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
            // Only send questions to users, NOT the answers
            // Answers are kept server-side for verification during submission
            $judgeData = $task->textJudges->map(function ($textJudge) {
                return [
                    'id' => $textJudge->id,
                    'question' => $textJudge->questions,
                    // 'answers' field is intentionally excluded for security
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
            'current_step' => $attempt->getMeta('current_step'),
        ]);
    }

    /**
     * Submit and evaluate answers for a task attempt.
     */
    public function submit(Request $request, UserTaskAttempt $attempt): JsonResponse
    {
        $user = $request->user();

        // Verify the attempt belongs to the user
        if ($attempt->user_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        // Verify the attempt is still in progress
        if ($attempt->status === 'completed') {
            return response()->json([
                'error' => 'This attempt has already been completed.',
            ], 400);
        }

        $task = $attempt->task;

        // Verify the task is not locked for this user
        if ($task->isLockedForUser($user)) {
            return response()->json([
                'error' => 'This task is locked for you.',
            ], 403);
        }

        // Validate request based on judge type
        $validated = $request->validate([
            'answers' => 'required|array',
        ]);

        $answers = $validated['answers'];

        // Evaluate based on judge type
        if ($task->judge_type === 'TextJudge') {
            $judgeService = new TextJudgeService();
            $result = $judgeService->evaluate($task, $attempt, $answers);

            // Save the attempt answer
            UserTaskAttemptAnswer::create([
                'user_task_attempt_id' => $attempt->id,
                'answers' => json_encode($answers),
                'score' => $result['score'],
                'notes' => $this->formatResultNotes($result),
            ]);

            // Update the attempt
            $attempt->update([
                'status' => 'completed',
                'completed_at' => now(),
                'score' => $result['score'],
            ]);

            // Add note to attempt with scoring details
            $noteMessage = sprintf(
                "Attempt #%d completed. Score: %.2f/%.2f (%.1f%%). Correct answers: %d/%d.",
                $result['attempt_number'],
                $result['score'],
                $result['max_score'],
                ($result['score'] / $task->score) * 100,
                $result['correct_count'],
                $result['total_count']
            );
            $attempt->appendNote($noteMessage);

            // Check if all answers are correct (100% success)
            $allCorrect = $result['correct_count'] === $result['total_count'];
            $shouldLockDueToSuccess = false;

            if ($allCorrect) {
                // Lock the task immediately when user gets 100% correct
                TaskUserLock::firstOrCreate(
                    [
                        'task_id' => $task->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'reason' => sprintf(
                            'Task completed successfully with all answers correct (%d/%d) on attempt #%d. Score: %.2f/%.2f points.',
                            $result['correct_count'],
                            $result['total_count'],
                            $result['attempt_number'],
                            $result['score'],
                            $result['max_score']
                        ),
                    ]
                );

                $attempt->appendNote(sprintf(
                    'Task completed successfully! All answers correct (%d/%d). Task has been locked.',
                    $result['correct_count'],
                    $result['total_count']
                ));

                $shouldLockDueToSuccess = true;
            } elseif ($result['should_lock']) {
                // Lock due to penalty threshold (too many failed attempts)
                TaskUserLock::create([
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                    'reason' => sprintf(
                        'Task locked: Next attempt\'s maximum possible score (%.2f points) would be below 20%% threshold (%.2f points required)',
                        $result['next_attempt_max_score'],
                        $task->score * 0.2
                    ),
                ]);

                $attempt->appendNote(sprintf(
                    'Task has been locked. Next attempt would have a maximum possible score of %.2f points, which is below the 20%% threshold (%.2f points).',
                    $result['next_attempt_max_score'],
                    $task->score * 0.2
                ));
            }

            return response()->json([
                'success' => true,
                'result' => $result,
                'locked' => $result['should_lock'] || $shouldLockDueToSuccess,
                'locked_due_to_success' => $shouldLockDueToSuccess,
            ]);
        } elseif ($task->judge_type === 'QuizJudge') {
            $judgeService = new QuizJudgeService();
            $result = $judgeService->evaluate($task, $attempt, $answers);

            // Save the attempt answer
            UserTaskAttemptAnswer::create([
                'user_task_attempt_id' => $attempt->id,
                'answers' => json_encode($answers),
                'score' => $result['score'],
                'notes' => $this->formatResultNotes($result),
            ]);

            // Update the attempt
            $attempt->update([
                'status' => 'completed',
                'completed_at' => now(),
                'score' => $result['score'],
            ]);

            // Add note to attempt with scoring details
            $noteMessage = sprintf(
                "Attempt #%d completed. Score: %.2f/%.2f (%.1f%%). Correct answers: %d/%d.",
                $result['attempt_number'],
                $result['score'],
                $result['max_score'],
                ($result['score'] / $task->score) * 100,
                $result['correct_count'],
                $result['total_count']
            );
            $attempt->appendNote($noteMessage);

            // Check if all answers are correct (100% success)
            $allCorrect = $result['correct_count'] === $result['total_count'];
            $shouldLockDueToSuccess = false;

            if ($allCorrect) {
                // Lock the task immediately when user gets 100% correct
                TaskUserLock::firstOrCreate(
                    [
                        'task_id' => $task->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'reason' => sprintf(
                            'Task completed successfully with all answers correct (%d/%d) on attempt #%d. Score: %.2f/%.2f points.',
                            $result['correct_count'],
                            $result['total_count'],
                            $result['attempt_number'],
                            $result['score'],
                            $result['max_score']
                        ),
                    ]
                );

                $attempt->appendNote(sprintf(
                    'Task completed successfully! All answers correct (%d/%d). Task has been locked.',
                    $result['correct_count'],
                    $result['total_count']
                ));

                $shouldLockDueToSuccess = true;
            } elseif ($result['should_lock']) {
                // Lock due to penalty threshold (too many failed attempts)
                TaskUserLock::create([
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                    'reason' => sprintf(
                        'Task locked: Next attempt\'s maximum possible score (%.2f points) would be below 20%% threshold (%.2f points required)',
                        $result['next_attempt_max_score'],
                        $task->score * 0.2
                    ),
                ]);

                $attempt->appendNote(sprintf(
                    'Task has been locked. Next attempt would have a maximum possible score of %.2f points, which is below the 20%% threshold (%.2f points).',
                    $result['next_attempt_max_score'],
                    $task->score * 0.2
                ));
            }

            return response()->json([
                'success' => true,
                'result' => $result,
                'locked' => $result['should_lock'] || $shouldLockDueToSuccess,
                'locked_due_to_success' => $shouldLockDueToSuccess,
            ]);
        } elseif ($task->judge_type === 'AiJudge') {
            $judgeService = new AiJudgeService();
            $result = $judgeService->evaluate($task, $attempt, $answers);

            // Save the attempt answer
            UserTaskAttemptAnswer::create([
                'user_task_attempt_id' => $attempt->id,
                'answers' => json_encode($answers),
                'score' => $result['score'],
                'notes' => $this->formatResultNotes($result),
            ]);

            // Update the attempt
            $attempt->update([
                'status' => 'completed',
                'completed_at' => now(),
                'score' => $result['score'],
            ]);

            // Add note to attempt with scoring details
            $noteMessage = sprintf(
                "Attempt #%d completed. Score: %.2f/%.2f (%.1f%%). Correct answers: %d/%d.",
                $result['attempt_number'],
                $result['score'],
                $result['max_score'],
                ($result['score'] / $task->score) * 100,
                $result['correct_count'],
                $result['total_count']
            );
            $attempt->appendNote($noteMessage);

            // Check if all answers are correct (100% success)
            $allCorrect = $result['correct_count'] === $result['total_count'];
            $shouldLockDueToSuccess = false;

            if ($allCorrect) {
                // Lock the task immediately when user gets 100% correct
                TaskUserLock::firstOrCreate(
                    [
                        'task_id' => $task->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'reason' => sprintf(
                            'Task completed successfully with all answers correct (%d/%d) on attempt #%d. Score: %.2f/%.2f points.',
                            $result['correct_count'],
                            $result['total_count'],
                            $result['attempt_number'],
                            $result['score'],
                            $result['max_score']
                        ),
                    ]
                );

                $attempt->appendNote(sprintf(
                    'Task completed successfully! All answers correct (%d/%d). Task has been locked.',
                    $result['correct_count'],
                    $result['total_count']
                ));

                $shouldLockDueToSuccess = true;
            } elseif ($result['should_lock']) {
                // Lock due to penalty threshold (too many failed attempts)
                TaskUserLock::create([
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                    'reason' => sprintf(
                        'Task locked: Next attempt\'s maximum possible score (%.2f points) would be below 20%% threshold (%.2f points required)',
                        $result['next_attempt_max_score'],
                        $task->score * 0.2
                    ),
                ]);

                $attempt->appendNote(sprintf(
                    'Task has been locked. Next attempt would have a maximum possible score of %.2f points, which is below the 20%% threshold (%.2f points).',
                    $result['next_attempt_max_score'],
                    $task->score * 0.2
                ));
            }

            return response()->json([
                'success' => true,
                'result' => $result,
                'locked' => $result['should_lock'] || $shouldLockDueToSuccess,
                'locked_due_to_success' => $shouldLockDueToSuccess,
            ]);
        }

        return response()->json([
            'error' => 'Judge type not supported yet.',
        ], 400);
    }

    /**
     * Format the evaluation result into a readable note.
     */
    protected function formatResultNotes(array $result): string
    {
        $notes = "Evaluation Results:\n\n";

        foreach ($result['details'] as $index => $detail) {
            $status = $detail['is_correct'] ? '✓ Correct' : '✗ Incorrect';
            $notes .= sprintf(
                "Question %d: %s\n",
                $index + 1,
                $status
            );

            // Include AI feedback if available
            if (isset($detail['feedback'])) {
                $notes .= sprintf(
                    "  Feedback: %s\n",
                    $detail['feedback']
                );
            }

            // Include AI score if available
            if (isset($detail['score'])) {
                $notes .= sprintf(
                    "  Score: %.2f/1.00\n",
                    $detail['score']
                );
            }
        }

        $notes .= sprintf(
            "\nTotal Score: %.2f/%.2f (%.1f%%)\n",
            $result['score'],
            $result['max_score'],
            ($result['correct_count'] / $result['total_count']) * 100
        );

        return $notes;
    }

    /**
     * Calculate maximum possible score based on attempt number.
     * Each attempt reduces the max score by 10%.
     *
     * @param int $taskScore
     * @param int $attemptNumber
     * @return float
     */
    protected function calculateMaxScore(int $taskScore, int $attemptNumber): float
    {
        // Attempt 1: 100%, Attempt 2: 90%, Attempt 3: 80%, etc.
        $penaltyPercentage = ($attemptNumber - 1) * 10;
        $maxPercentage = max(0, 100 - $penaltyPercentage);

        return ($taskScore * $maxPercentage) / 100;
    }
}
