<?php

namespace App\Jobs\Scripts\Workspace;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Jobs\Scripts\Concerns\HandlesScriptExecution;
use App\Models\UserTaskAttempt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base class for workspace-related jobs.
 *
 * Provides common defaults and a shared failed() implementation to reduce duplication.
 */
abstract class BaseWorkspaceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HandlesScriptExecution;

    // sensible default for workspace jobs; child classes may override
    public $timeout = 900;
    public $tries = 1;

    /**
     * Default failed handler for workspace jobs.
     * Updates the associated attempt (if present) and logs the error.
     */
    public function failed(Throwable $exception): void
    {
        try {
            if (property_exists($this, 'attempt') && $this->attempt instanceof UserTaskAttempt) {
                $this->attempt->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'notes' => $this->appendToNotes(
                        $this->attempt->notes,
                        sprintf("[%s] Job failed: %s", now()->toDateTimeString(), $exception->getMessage())
                    ),
                ]);
            }
        } catch (Throwable $e) {
            // swallow - we don't want failed() to throw and break the queue infrastructure
            Log::error('Error while running failed() handler on workspace job', [
                'exception' => $e->getMessage(),
            ]);
        }

        Log::error('Workspace job failed', [
            'exception' => $exception->getMessage(),
            'job_class' => static::class,
        ]);
    }
}
