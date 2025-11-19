<?php

namespace App\Traits;

use App\Services\Progress\WorkflowRegistry;
use App\Enums\ScriptJobStatus;

/**
 * Trait for models that support progress tracking
 *
 * Models using this trait must:
 * - Use HasMeta trait
 * - Implement TracksProgressInterface
 */
trait TracksProgress
{
    /**
     * Get workflow type identifier
     * Must be implemented by the model
     */
    abstract public function getWorkflowType(): string;


    /**
     * Get all workflow steps with their status
     */
    public function getWorkflowSteps(): array
    {
        $metadata = $this->getAllMeta();
        return $metadata['step_history'] ?? [];
    }

    /**
     * Update a specific workflow step
     */
    public function updateWorkflowStep(string $stepId, array $data): void
    {
        $steps = $this->getWorkflowSteps();
        $steps[$stepId] = array_merge($steps[$stepId] ?? [], $data);

        $this->addMeta(['step_history' => $steps]);
    }

    /**
     * Get current workflow step
     */
    public function getCurrentStepId(): ?string
    {
        $metadata = $this->getAllMeta();
        return $metadata['current_step'] ?? null;
    }

    /**
     * Check if workflow is running
     */
    public function isWorkflowRunning(): bool
    {
        $currentStep = ScriptJobStatus::tryFrom($this->getCurrentStepId());
        return $currentStep !== null
            && $currentStep !== ScriptJobStatus::COMPLETED
            && $currentStep !== ScriptJobStatus::FAILED;
    }

    /**
     * Check if workflow is completed
     */
    public function isWorkflowCompleted(): bool
    {
        return ScriptJobStatus::tryFrom($this->getCurrentStepId()) === ScriptJobStatus::COMPLETED;
    }

    /**
     * Check if workflow has failed
     */
    public function isWorkflowFailed(): bool
    {
        return ScriptJobStatus::tryFrom($this->getCurrentStepId()) === ScriptJobStatus::FAILED;
    }

    /**
     * Initialize workflow tracking from job array
     *
     * @param array $jobs Array of job instances
     * @param string $workflowType Workflow type identifier
     * @param string $workflowName Human-readable workflow name
     */
    public function initializeWorkflowFromJobs(
        array $jobs,
        string $workflowType,
        string $workflowName
    ): void {
        // Build workflow definition from jobs
        $workflowDefinition = WorkflowRegistry::buildFromJobs($jobs, $workflowType, $workflowName);

        // Store in metadata
        $this->addMeta([
            'workflow_definition' => $workflowDefinition,
            'workflow_started_at' => now()->toIso8601String(),
            'current_step' => null,
            'step_history' => [],
        ]);
    }

    /**
     * Get enriched workflow state
     */
    public function getWorkflowState(): array
    {
        $metadata = $this->getAllMeta();
        $workflowDefinition = $metadata['workflow_definition'] ?? null;

        if (!$workflowDefinition) {
            // Return empty workflow if not initialized
            return [
                'type' => $this->getWorkflowType(),
                'name' => 'Unknown Workflow',
                'category' => 'unknown',
                'steps' => [],
                'currentStepId' => null,
                'status' => 'idle',
                'progress' => [
                    'completed' => 0,
                    'total' => 0,
                    'percentage' => 0,
                ],
                'startedAt' => null,
                'completedAt' => null,
            ];
        }

        return WorkflowRegistry::buildWorkflowState($workflowDefinition, $metadata);
    }

    /**
     * Mark workflow as completed
     */
    public function completeWorkflow(): void
    {
        $this->addMeta([
            'current_step' => ScriptJobStatus::COMPLETED,
            'workflow_completed_at' => now()->toIso8601String(),
        ]);
    }

    public function getWorkflowStatus(): ?ScriptJobStatus
    {
        $currentStep = ScriptJobStatus::tryFrom($this->getCurrentStepId());

        if ($currentStep === ScriptJobStatus::COMPLETED) {
            return ScriptJobStatus::COMPLETED;
        }

        if ($currentStep === ScriptJobStatus::FAILED) {
            return ScriptJobStatus::FAILED;
        }

        if ($currentStep !== null) {
            return ScriptJobStatus::RUNNING;
        }

        return null;
    }
}
