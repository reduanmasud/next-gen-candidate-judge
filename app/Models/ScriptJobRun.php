<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScriptJobRun extends Model
{
    protected $fillable = [
        'job_id',
        'script_name',
        'script_path',
        'status',
        'script_content',
        'output',
        'error_output',
        'exit_code',
        'notes',
        'metadata',
        'started_at',
        'completed_at',
        'failed_at',
        'terminated_at',
        'cancelled_at',
        'timed_out_at',
        'user_id',
        'server_id',
        'task_id',
        'attempt_id',
    ];  

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'terminated_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'timed_out_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(UserTaskAttempt::class, 'attempt_id');
    }

    // Scopes for filtering
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
