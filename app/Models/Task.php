<?php

namespace App\Models;

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
}
