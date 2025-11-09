<?php

namespace App\Jobs\Scripts\Workspace;

use App\Models\UserTaskAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Traits\AppendsNotes;

class FinalizeWorkspaceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use AppendsNotes;
    public UserTaskAttempt $attempt;

    public $timeout = 60;
    public $tries = 1;

    public function __construct(
        public Int $attemptId,
    ) {
        //
    }

    public function handle(): void
    {
        $this->attempt = UserTaskAttempt::find($this->attemptId);

        try {
            // Only finalize if the attempt is still in running status
            // (it might have been marked as failed by a previous job in the chain)
            if ($this->attempt->status === 'pending') {
                $this->attempt->update([
                    'status' => 'running',
                    'completed_at' => now(),
                    'notes' => $this->appendToNotes(
                        $this->attempt->notes,
                        sprintf("[%s] Workspace provisioning completed successfully", now()->toDateTimeString())
                    ),
                ]);

                Log::info('Workspace provisioning finalized', [
                    'attempt_id' => $this->attempt->id,
                    'task_id' => $this->attempt->task_id,
                    'user_id' => $this->attempt->user_id,
                ]);
            }

        } catch (Throwable $e) {
            Log::error('Failed to finalize workspace', [
                'attempt_id' => $this->attempt->id,
                'error' => $e->getMessage(),
            ]);

            // Don't throw - we don't want to fail the entire chain just because finalization failed
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Finalize workspace job failed', [
            'attempt_id' => $this->attempt->id,
            'error' => $exception->getMessage(),
        ]);
    }

}

