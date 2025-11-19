<?php

namespace App\Models;

use App\Events\ScriptJobRunCreatedEvent;
use App\Events\ScriptJobRunStatusUpdatedEvent;
use App\Enums\ScriptJobStatus;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use App\Services\ScriptWrapper;
use App\Traits\HasMeta;
use App\Traits\NotesAccessor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\broadcast;
use Throwable;

class ScriptJobRun extends Model
{
    use HasMeta, NotesAccessor;

    protected $table = 'script_job_runs';

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
        'status' => ScriptJobStatus::class,
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


    protected function execute(ScriptEngine $engine, ?Server $server): array
    {
        if ($server) {
            $engine->setServer($server);
        }

        $this->update([
            'status' => ScriptJobStatus::RUNNING,
            'started_at' => now(),
        ]);

        $result = [];
        try {
            $result = $engine->executeViaStdin($this->script_content);

            if (!$result['successful']) {
                throw new \RuntimeException('Failed to execute script: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }

            $this->update([
                'status' => ScriptJobStatus::COMPLETED,
                'output' => $result['output'] ?? '',
                'error_output' => $result['error_output'] ?? '',
                'exit_code' => $result['exit_code'] ?? 0,
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {

            $this->update([
                'status' => ScriptJobStatus::FAILED,
                'error_output' => $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            throw $e;
        }

        return [$this, $result];
    }

    public static function createAndExecute(
        ScriptDescriptor $script,
        ScriptEngine $engine,
        ?UserTaskAttempt $attempt = null,
        ?Server $server = null,
        array $metadata = []
    ): array {

        $wrappedScript = (new ScriptWrapper())->wrap(view($script->template, $script->data)->render());

        $jobRun = static::create([
            'script_name' => $script->name,
            'script_path' => $script->template,
            'status' => ScriptJobStatus::PENDING,
            'user_id' => $attempt?->user_id,
            'server_id' => $server?->id,
            'attempt_id' => $attempt?->id,
            'script_content' => $wrappedScript,
            'metadata' => $metadata,
        ]);

        return $jobRun->execute($engine, $server);
    }


    // Scopes for filtering
    public function scopePending($query)
    {
        return $query->where('status', ScriptJobStatus::PENDING);
    }

    public function scopeRunning($query)
    {
        return $query->where('status', ScriptJobStatus::RUNNING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', ScriptJobStatus::COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', ScriptJobStatus::FAILED);
    }


    protected static function booted()
    {

        // Broadcast new job runs
        static::created(function ($jobRun) {
            broadcast(new ScriptJobRunCreatedEvent($jobRun))
                ->toOthers();
        });

        // Broadcast status updates
        static::updated(function ($jobRun) {
            if ($jobRun->wasChanged('status'))
                broadcast(new ScriptJobRunStatusUpdatedEvent(
                    jobRunId: $jobRun->id,
                    status: $jobRun->status
                ))
                    ->toOthers();
        });
    }
}
