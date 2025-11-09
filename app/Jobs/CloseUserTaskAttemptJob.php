<?php

namespace App\Jobs;

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
        $this->attempt->update([
            'status' => 'failed',
            'completed_at' => now(),
        ]);

        $this->attempt->notes = $this->appendToNotes(
            $this->attempt->notes,
            sprintf("[%s] Attempt closed", now()->toDateTimeString())
        );

        $this->attempt->save();


        if($this->attempt->task->sandbox)
        {
            DeleteWorkspaceJob::dispatch($this->attempt, $this->attempt->task->server);
        }

        
    }
}
