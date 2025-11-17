<?php

namespace App\Contracts;


interface TracksProgressInterface
{
    public function getWorkflowType(): string;

    public function getWorkflowState(): array;

    public function initializeWorkflowFromJobs(
        array $jobs,
        string $workflowType,
        string $workflowName
    ): void;

    public function completeWorkflow(): void;

    public function getWorkflowSteps(): array;

    public function updateWorkflowStep(
        string $stepId,
        array $data
    ): void;

    public function getCurrentStepId(): ?string;

    public function isWorkflowRunning(): bool;

    public function isWorkflowCompleted(): bool;

    public function isWorkflowFailed(): bool;
}
