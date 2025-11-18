<?php

namespace App\Contracts;

/**
 * Interface for models that support progress tracking
 *
 * Implementations MUST also use the HasMeta trait
 */
interface TracksProgressInterface
{
    public function getWorkflowType(): string;

     // Add these methods from HasMeta trait
    public function getAllMeta(): array;
    public function addMeta(array $data, bool $overwrite = true): self;

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
