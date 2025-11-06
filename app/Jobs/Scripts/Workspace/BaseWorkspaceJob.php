<?php

namespace App\Jobs\Scripts\Workspace;

use App\Jobs\Scripts\BaseScriptJob;
use App\Models\UserTaskAttempt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base class for workspace-related jobs.
 *
 * Extends BaseScriptJob and adds workspace-specific failed() handling
 * that updates the associated UserTaskAttempt record.
 */
abstract class BaseWorkspaceJob extends BaseScriptJob
{
    /**
     * Default failed handler for workspace jobs.
     *
     * Updates the associated attempt (if present) and logs the error,
     * then calls parent failed() to handle ScriptJobRun updates.
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

        // Call parent to handle ScriptJobRun updates and logging
        parent::failed($exception);
    }
}
