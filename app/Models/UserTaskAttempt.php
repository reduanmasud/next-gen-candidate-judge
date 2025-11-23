<?php

namespace App\Models;

use App\Contracts\TracksProgressInterface;
use App\Enums\AttemptTaskStatus;
use App\Events\WorkspaceCreatedEvent;
use App\Events\WorkspaceStatusUpdatedEvent;
use App\Traits\HasMeta;
use App\Traits\NotesAccessor;
use App\Traits\TracksProgress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserTaskAttempt extends Model implements TracksProgressInterface
{
    use HasMeta;
    use NotesAccessor;
    use TracksProgress;

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
        'submission_count',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
        'status' => AttemptTaskStatus::class,
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

    public function getWorkflowType(): string
    {
        return 'workspace_provisioning';
    }

    protected static function booted(): void
    {
        static::created(function ($attempt) {
            broadcast(new WorkspaceCreatedEvent($attempt));
        });

        static::updated(function ($attempt) {
            if ($attempt->wasChanged('status') || $attempt->wasChanged('metadata')) {
                $metadata = $attempt->getAllMeta();
                broadcast(new WorkspaceStatusUpdatedEvent(
                    attemptId: $attempt->id,
                    status: $attempt->status->value,
                    currentStep: $metadata['current_step'] ?? null,
                    metadata: $metadata
                ));
            }
        });
    }
}
