<?php

namespace App\Jobs;

use App\Enums\AttemptTaskStatus;
use App\Jobs\Scripts\Workspace\DeleteWorkspaceJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\UserTaskAttempt;
use App\Traits\AppendsNotes;

class CloseUserTaskAttemptJob implements ShouldQueue
{
    use Queueable;
    use AppendsNotes;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public UserTaskAttempt $attempt,
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Refresh the attempt to get the latest status
        $this->attempt->refresh();

        // Define final statuses that should not be overwritten
        $finalStatuses = [
            AttemptTaskStatus::COMPLETED,
            AttemptTaskStatus::TERMINATED,
            AttemptTaskStatus::ATTEMPTED_FAILED,
        ];

        // Only update status if the attempt is not already in a final state
        if (!in_array($this->attempt->status, $finalStatuses)) {
            $this->attempt->update([
                'status' => AttemptTaskStatus::TERMINATED,
                'completed_at' => now(),
            ]);

            $this->attempt->appendNote("Attempt closed by timeout");
        }

        // Always clean up workspace if it's a sandbox task, regardless of status
        if($this->attempt->task->sandbox)
        {
            DeleteWorkspaceJob::dispatch($this->attempt->id, $this->attempt->task->server->id);
        }
    }
}
