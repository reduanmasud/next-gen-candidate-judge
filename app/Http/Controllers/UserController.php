<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\UserTaskAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of users with statistics.
     */
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $roleFilter = $request->query('role');
        $sort = $request->query('sort');

        $query = User::query()
            ->withCount('attempts')
            ->with('roles');

        // Add search functionality
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Add role filter
        if ($roleFilter && $roleFilter !== 'all') {
            $query->whereHas('roles', function ($q) use ($roleFilter) {
                $q->where('name', $roleFilter);
            });
        }

        // Get users with their total scores
        $usersQuery = $query->get()->map(function ($user) {
            // Calculate total score for each user
            $totalScore = UserTaskAttempt::where('user_id', $user->id)
                ->sum('score');

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'attempts_count' => $user->attempts_count,
                'total_score' => $totalScore ?? 0,
                'is_admin' => $user->hasRole('admin'),
                'role' => $user->roles->first()?->name ?? 'user',
            ];
        });

        // Apply sorting
        if ($sort && $sort !== 'default') {
            switch ($sort) {
                case 'score_asc':
                    $usersQuery = $usersQuery->sortBy('total_score');
                    break;
                case 'score_desc':
                    $usersQuery = $usersQuery->sortByDesc('total_score');
                    break;
                case 'attempts_asc':
                    $usersQuery = $usersQuery->sortBy('attempts_count');
                    break;
                case 'attempts_desc':
                    $usersQuery = $usersQuery->sortByDesc('attempts_count');
                    break;
                default:
                    // Default: latest users first (by created_at desc)
                    $usersQuery = $usersQuery->sortByDesc('created_at');
            }
        } else {
            // Default: latest users first
            $usersQuery = $usersQuery->sortByDesc('created_at');
        }

        // Paginate the results
        $perPage = 15;
        $currentPage = $request->query('page', 1);
        $usersCollection = collect($usersQuery->values()->all());
        $users = new \Illuminate\Pagination\LengthAwarePaginator(
            $usersCollection->forPage($currentPage, $perPage),
            $usersCollection->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Calculate summary statistics
        $totalUsers = User::count();
        $normalUsers = User::whereDoesntHave('roles', function ($query) {
            $query->where('name', 'admin');
        })->count();

        // Get all available roles for the filter dropdown
        $availableRoles = Role::all()->pluck('name')->toArray();

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => $roleFilter,
                'sort' => $sort,
            ],
            'stats' => [
                'total_users' => $totalUsers,
                'normal_users' => $normalUsers,
            ],
            'availableRoles' => $availableRoles,
        ]);
    }

    /**
     * Determine the display status based on attempt status and notes.
     */
    private function getDisplayStatus(UserTaskAttempt $attempt): string
    {
        $status = $attempt->status->value;

        // Check if it's a timeout by examining the notes
        if ($status === 'terminated' && str_contains($attempt->notes ?? '', 'timeout')) {
            return 'timeout';
        }

        // Map other statuses
        return match ($status) {
            'attempted_failed' => 'failed',
            'completed' => 'completed',
            'terminated' => 'terminated',
            default => $status,
        };
    }

    /**
     * Display the specified user with detailed statistics.
     */
    public function show(User $user): Response
    {
        // Load user with all necessary relationships
        $user->load([
            'attempts' => function ($query) {
                $query->with(['task', 'answers'])
                    ->latest();
            },
            'taskLocks' => function ($query) {
                $query->with('task')
                    ->latest();
            },
            'roles',
        ]);

        // Calculate statistics
        $totalScore = $user->attempts->sum('score');
        $totalAttempts = $user->attempts->count();

        // Calculate right/wrong ratio from attempt answers
        $correctAnswers = 0;
        $totalAnswers = 0;

        foreach ($user->attempts as $attempt) {
            if ($attempt->answers->isNotEmpty()) {
                foreach ($attempt->answers as $answer) {
                    $answerData = json_decode($answer->notes, true);
                    if (isset($answerData['correct_count']) && isset($answerData['total_count'])) {
                        $correctAnswers += $answerData['correct_count'];
                        $totalAnswers += $answerData['total_count'];
                    }
                }
            }
        }

        // Group attempts by task and calculate aggregated data
        $taskAttemptsSummary = $user->attempts
            ->groupBy('task_id')
            ->map(function ($attempts) {
                $task = $attempts->first()->task;
                $totalSubmissions = $attempts->sum('submission_count');

                return [
                    'task_id' => $task->id,
                    'task_title' => $task->title,
                    'task_score' => $task->score,
                    'attempt_count' => $attempts->count(),
                    'total_submissions' => $totalSubmissions,
                    'best_score' => $attempts->max('score'),
                    'latest_attempt_date' => $attempts->first()->started_at,
                ];
            })
            ->values()
            ->sortByDesc('latest_attempt_date')
            ->values();

        // Add display_status to attempts
        $attemptsWithDisplayStatus = $user->attempts->map(function ($attempt) {
            $attemptArray = $attempt->toArray();
            $attemptArray['display_status'] = $this->getDisplayStatus($attempt);
            return $attemptArray;
        });

        return Inertia::render('users/show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'is_admin' => $user->hasRole('admin'),
                'role' => $user->roles->first()?->name ?? 'user',
                'attempts' => $attemptsWithDisplayStatus,
                'task_locks' => $user->taskLocks,
            ],
            'stats' => [
                'total_score' => $totalScore,
                'total_attempts' => $totalAttempts,
                'correct_answers' => $correctAnswers,
                'total_answers' => $totalAnswers,
                'locked_tasks_count' => $user->taskLocks->count(),
            ],
            'taskAttemptsSummary' => $taskAttemptsSummary,
        ]);
    }

    /**
     * Display detailed task attempts for a specific task by a user.
     */
    public function showTaskAttempts(User $user, Task $task): Response
    {
        // Load all attempts for this user and task with submissions
        $attempts = UserTaskAttempt::where('user_id', $user->id)
            ->where('task_id', $task->id)
            ->with(['answers' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }])
            ->orderBy('started_at', 'desc')
            ->get();

        // Format attempts with detailed submission data
        $formattedAttempts = $attempts->map(function ($attempt) {
            $duration = null;
            if ($attempt->started_at && $attempt->completed_at) {
                $duration = $attempt->started_at->diffInSeconds($attempt->completed_at);
            }

            $submissions = $attempt->answers->map(function ($answer) {
                return [
                    'id' => $answer->id,
                    'score' => $answer->score,
                    'notes' => $answer->notes,
                    'answers' => $answer->answers,
                    'submitted_at' => $answer->created_at,
                ];
            });

            return [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'display_status' => $this->getDisplayStatus($attempt),
                'score' => $attempt->score,
                'started_at' => $attempt->started_at,
                'completed_at' => $attempt->completed_at,
                'duration_seconds' => $duration,
                'submission_count' => $attempt->submission_count,
                'submissions' => $submissions,
            ];
        });

        return Inertia::render('users/task-attempts', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'score' => $task->score,
            ],
            'attempts' => $formattedAttempts,
        ]);
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): Response
    {
        // Get all available roles
        $allRoles = Role::all()->pluck('name')->toArray();

        // Get user's current role (assuming single role per user)
        $userRole = $user->roles->first()?->name ?? 'user';

        return Inertia::render('users/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $userRole,
            ],
            'availableRoles' => $allRoles,
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        // Get all available roles for validation
        $availableRoles = Role::all()->pluck('name')->toArray();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', 'string', Rule::in($availableRoles)],
        ]);

        // Update user basic information
        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
        ]);

        // Sync user role (remove all existing roles and assign the new one)
        $user->syncRoles([$validated['role']]);

        return redirect()->route('users.show', $user)
            ->with('success', 'User updated successfully.');
    }
}

