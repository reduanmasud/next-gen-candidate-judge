<?php

namespace App\Jobs\Scripts\Workspace;

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
            if ($this->attempt->status === 'pending') {
                $this->attempt->update([
                    'status' => 'running',
                    'started_at' => now(),

                ]);
                $this->attempt->appendNote("Workspace provisioning completed successfully");

            }

        } catch (Throwable $e) {
            $this->attempt->update([
                'status' => 'failed',
                'failed_at' => now(),
            ]);
            $this->attempt->appendNote("Failed to finalize workspace: ".$e->getMessage());

            // Don't throw - we don't want to fail the entire chain just because finalization failed
        }
    }

}

