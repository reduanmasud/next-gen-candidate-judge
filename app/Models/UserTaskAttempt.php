<?php

namespace App\Models;

use App\Traits\HasMeta;
use App\Traits\NotesAccessor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserTaskAttempt extends Model
{
    use HasMeta;
    use NotesAccessor;
    
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
        'notes',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
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

    public function answers(): HasMany
    {
        return $this->hasMany(UserTaskAttemptAnswer::class);
    }
}
