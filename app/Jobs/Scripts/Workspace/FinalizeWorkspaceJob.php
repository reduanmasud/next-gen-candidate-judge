<?php

namespace App\Jobs\Scripts\Workspace;

use App\Contracts\TracksProgressInterface;
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
    public static function getStepMetadata(): array
    {
        return [
            'id' => 'finalizing_workspace',
            'label' => 'Finalizing Workspace',
            'description' => 'Finalizing workspace setup',
            'icon' => 'check',
            'estimatedDuration' => 5,
        ];
    }
    public function getTrackableModel(): TracksProgressInterface
    {
        if(!isset($this->attempt)) {
            $this->attempt = UserTaskAttempt::find($this->attemptId);
        }
        return $this->attempt;
    }
    protected function failed(Throwable $exception): void
    {
        $this->attempt->appendNote("Failed to finalize workspace: ".$exception->getMessage());
        $this->jobRun->update([
            'status' => 'failed',
            'error_output' => "Failed to finalize workspace: " . $exception->getMessage(),
            'failed_at' => now(),
            'completed_at' => now(),
        ]);
        $this->attempt->update([
            'status' => 'failed',
            'failed_at' => now(),
        ]);
    }

    protected function execute(): void
    {
        $this->attempt = UserTaskAttempt::find($this->attemptId);

        if ($this->attempt->status === AttemptTaskStatus::PREPARING) {
            $this->attempt->update([
                'status' => AttemptTaskStatus::RUNNING,
                'started_at' => now(),
            ]);
        }
        $this->attempt->appendNote("Workspace provisioning completed successfully");
        $this->getTrackableModel()->completeWorkflow();
    }

}

