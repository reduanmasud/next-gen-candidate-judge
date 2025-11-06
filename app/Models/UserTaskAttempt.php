<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserTaskAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'task_id',
        'container_id',
        'container_name',
        'container_port',
        'status',
        'started_at',
        'completed_at',
        'score',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function jobRuns(): HasMany
    {
        return $this->hasMany(ScriptJobRun::class, 'attempt_id');
    }
}
