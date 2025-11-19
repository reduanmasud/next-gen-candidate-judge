<?php

namespace App\Jobs\Scripts;

use App\Contracts\DescribesProgressStep;
use App\Contracts\TracksProgressInterface;
use App\Enums\ScriptJobStatus;
use App\Models\ScriptJobRun;
use App\Models\User;
use App\Services\ScriptWrapper;
use App\Traits\AppendsNotes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Base class for all script-related jobs.`
 */
abstract class BaseScriptJob implements ShouldQueue, DescribesProgressStep
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use AppendsNotes;

    public $timeout = 900; // 15 minutes
    public $tries = 1;

    protected ScriptJobRun $jobRun;

    protected ScriptWrapper $wrapper;
    protected string $script;
    public ?User $authUser;

    public function __construct()
    {
        $this->authUser = Auth::user();
        $this->wrapper = new ScriptWrapper();
    }


    final public function handle(): void
    {
        $trackable = $this->getTrackableModel();
        $stepMetadata = static::getStepMetadata();
        $stepId = $stepMetadata['id'];

        try {
            $this->trackStepStart($trackable, $stepId);
            $this->execute();
            $this->trackStepComplete($trackable, $stepId);
        } catch (\Throwable $e) {
            $this->trackStepFailed($trackable, $stepId, $e);
            $this->failed($e);
            throw $e;
        }
    }

    abstract protected function failed(Throwable $exception): void;

    abstract protected function execute(): void;

    abstract public function getTrackableModel(): TracksProgressInterface;

    abstract public static function getStepMetadata(): array;

    private function trackStepStart(TracksProgressInterface $trackable, string $stepId): void
    {
        $steps = $trackable->getWorkflowSteps();

        $steps[$stepId] = [
            'status' => ScriptJobStatus::IN_PROGRESS,
            'started_at' => now()->toIso8601String(),
            'completed_at' => null,
            'failed_at' => null,
            'error_message' => null,
        ];

        $trackable->updateWorkflowStep($stepId, $steps[$stepId]);

        $trackable->addMeta(['current_step' => $stepId]);
    }


    private function trackStepComplete(TracksProgressInterface $trackable, string $stepId): void
    {
        $trackable->updateWorkflowStep($stepId, [
            'status' => ScriptJobStatus::COMPLETED,
            'completed_at' => now()->toIso8601String(),
        ]);


        $metadata = $trackable->getAllMeta();
        $workflowDefinition = $metadata['workflow_definition'] ?? null;

        if ($workflowDefinition) {
            $totalSteps = count($workflowDefinition['steps']);
            $completedSteps = collect($trackable->getWorkflowSteps())
                ->where('status', ScriptJobStatus::COMPLETED)
                ->count();

            if ($completedSteps === $totalSteps) {
                // All steps completed - mark workflow as completed
                $trackable->completeWorkflow();
            } else {
                // More steps to go
                $trackable->addMeta(['current_step' => $stepId]);
            }
        }
    }

    private function trackStepFailed(TracksProgressInterface $trackable, string $stepId, Throwable $e): void
    {
        $trackable->updateWorkflowStep($stepId, [
            'status' => ScriptJobStatus::FAILED,
            'failed_at' => now()->toIso8601String(),
            'error_message' => $e->getMessage(),
        ]);

        $trackable->addMeta([
            'current_step' => ScriptJobStatus::FAILED,
            'failed_step' => $stepId,
            // 'current_step' => "{$stepId}_failed"
        ]);
    }


    protected function getWrapper(): ScriptWrapper
    {
        if (!isset($this->wrapper)) {
            $this->wrapper = new ScriptWrapper();
        }
        return $this->wrapper;
    }
}
