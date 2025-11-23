<?php

namespace App\Models;

use App\Enums\AttemptTaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Task extends Model
{
    protected $fillable = [
        'title',
        'description',
        'docker_compose_yaml',
        'score',
        'is_active',
        'server_id',
        'pre_script',
        'post_script',
        'judge_type',
        'sandbox',
        'allowssh',
        'timer',
        'warrning_timer',
        'warning_timer_sound',
        'max_submission',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sandbox' => 'boolean',
        'allowssh' => 'boolean',
        'timer' => 'integer',
        'warrning_timer' => 'integer',
        'warning_timer_sound' => 'boolean',
        'score' => 'integer',
        'server_id' => 'integer',
        'max_submission' => 'integer',
    ];

    protected $attributes = [
        'timer' => null,
        'warrning_timer' => null,
        'warning_timer_sound' => false,
        'max_submission' => 3,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }
    public function attempts(): HasMany
    {
        return $this->hasMany(UserTaskAttempt::class);
    }

    public function userAttempt(User $user): HasMany
    {
        return $this->attempts()->where('user_id', $user->id);
    }

    public function jobRuns(): HasMany
    {
        return $this->hasMany(ScriptJobRun::class);
    }

    public function quizJudges(): HasMany
    {
        return $this->hasMany(QuizJudge::class);
    }

    public function textJudges(): HasMany
    {
        return $this->hasMany(TextJudge::class);
    }

    public function aiJudges(): HasMany
    {
        return $this->hasMany(AiJudge::class);
    }

    public function autoJudge(): HasOne
    {
        return $this->hasOne(AutoJudge::class);
    }

    public function lockedUsers(): HasMany
    {
        return $this->hasMany(TaskUserLock::class);
    }

    /**
     * Check if a task is locked for a specific user.
     *
     * @param User $user
     * @return bool
     */
    public function isLockedForUser(User $user): bool
    {
        return $this->lockedUsers()->where('user_id', $user->id)->exists();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithUserAttempts($query, User $user)
    {
        return $query->with(['attempts' => function ($query) use ($user) {
            $query->where('user_id', $user->id)->latest();
        }]);
    }

    public function scopeWithUserLocks($query, User $user)
    {
        return $query->with(['lockedUsers' => function ($query) use ($user) {
            $query->where('user_id', $user->id);
        }]);
    }

    public function scopeWithUserAttemptCount($query, User $user)
    {
        return $query->withCount(['attempts as attempt_count' => function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->where('status', '!=', AttemptTaskStatus::FAILED);
        }]);
    }
}
