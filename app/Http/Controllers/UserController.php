<?php

namespace App\Http\Controllers;

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
                'attempts' => $user->attempts,
                'task_locks' => $user->taskLocks,
            ],
            'stats' => [
                'total_score' => $totalScore,
                'total_attempts' => $totalAttempts,
                'correct_answers' => $correctAnswers,
                'total_answers' => $totalAnswers,
                'locked_tasks_count' => $user->taskLocks->count(),
            ],
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

