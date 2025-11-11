<?php

namespace App\Jobs\Scripts\Workspace;

use App\Enums\AttemptTaskStatus;
use App\Models\UserTaskAttempt;
use Throwable;

class FinalizeWorkspaceJob extends BaseWorkspaceJob
{
    public UserTaskAttempt $attempt;

    public function __construct(
        public Int $attemptId,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->attempt = UserTaskAttempt::find($this->attemptId);

        try {
            // Update progress: job started
            $this->attempt->addMeta(['current_step' => 'finalizing_workspace']);

            if ($this->attempt->status === AttemptTaskStatus::PREPARING) {
                $this->attempt->update([
                    'status' => AttemptTaskStatus::RUNNING,
                    'started_at' => now(),

                ]);
                $this->attempt->appendNote("Workspace provisioning completed successfully");

                // Update progress: all jobs completed
                $this->attempt->addMeta(['current_step' => 'completed']);
            }

        } catch (Throwable $e) {
            $this->attempt->update([
                'status' => AttemptTaskStatus::FAILED,
                'failed_at' => now(),
            ]);
            $this->attempt->addMeta(['current_step' => 'failed', 'failed_step' => 'finalizing_workspace']);
            $this->attempt->appendNote("Failed to finalize workspace: ".$e->getMessage());

            // Don't throw - we don't want to fail the entire chain just because finalization failed
        }
    }

}

