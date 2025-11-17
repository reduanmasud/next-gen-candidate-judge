<?php

namespace App\Services\Progress;

use App\Contracts\DescribesProgressStep;
use Carbon\Carbon;



class WorkflowRegistry
{

    public static function buildFromJobs(
        array $jobs,
        string $workflowType,
        string $workflowName
    ):array {

        $steps = [];
        foreach ($jobs as $job) {

            if( $job instanceof DescribesProgressStep) {
                $steps[] = $job::getStepMetadata();
            } else {
                $className = class_basename($job);
                $stepId = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', str_replace('Job', '', $className)));

                $steps[] = [
                    'id' => $stepId,
                    'label' => ucwords(str_replace('_', ' ', $stepId)),
                    'description' => '',
                    'icon' => 'circle',
                    'estimatedDuration' => 5,

                ];
            }
        }


        return [
            'type' => $workflowType,
            'name' => $workflowName,
            'category' => 'job_chain',
            'steps' => $steps,
        ];
    }



    public static function buildWorkflowState(
        array $workflowDefinition,
        array $metadata
    ): array {

        $currentStep = $metadata['current_step'] ?? null;
        $stepHistory = $metadata ['step_history'] ?? [];

        $steps = collect($workflowDefinition['steps'])->map(
            function ($step) use ($stepHistory) {
                $stepId = $step['id'];
                $historyEntry = $stepHistory[$stepId] ?? null;

                $status = 'pending';

                if($historyEntry) {
                    $status = $historyEntry['status']; // completed, failed, in-progress
                }

                return array_merge($step, [
                    'status' => $status,
                    'startedAt' => $historyEntry['started_at'] ?? null,
                    'completedAt' => $historyEntry['completed_at'] ?? null,
                    'failedAt' => $historyEntry['failed_at'] ?? null,
                    'errorMessage' => $historyEntry['error_message'] ?? null,
                    'duration' => $historyEntry ? self::calculateDuration($historyEntry) : null,
                ]);
            })->toArray();

            $total = count($steps);

            $completed = collect($steps)->where('status', 'completed')->count();

            $percentage = $total > 0 ? ($completed / $total) * 100 : 0;

            $status = self::determineWorkflowStatus($steps, $currentStep);

            return [
                'type' => $workflowDefinition['type'],
                'name' => $workflowDefinition['name'],
                'category' => $workflowDefinition['category'],
                'steps' => $steps,
                'currentStepId' => $currentStep,
                'status' => $status,
                'progress' => [
                    'total' => $total,
                    'completed' => $completed,
                    'percentage' => round($percentage, 2),
                ],
                'startedAt' => $metadata['workflow_started_at'] ?? null,
                'completedAt' => $metadata['workflow_completed_at'] ?? null,
                'failedAt' => $metadata['workflow_failed_at'] ?? null,
            ];

    }


    private static function calculateDuration(array $historyEntry): ?int
    {

        $startedAt = $historyEntry['started_at'] ?? null;
        $endedAt = $historyEntry['completed_at'] ?? $historyEntry['failed_at'] ?? null;

        if(!$startedAt || !$endedAt) {
            return null;
        }

        return Carbon::parse($endedAt)->diffInSeconds(Carbon::parse($startedAt));

    }


    private static function determineWorkflowStatus(
        array $steps, ?string $currentStep
    ): string {

        if($currentStep === 'failed') return 'failed';
        if($currentStep === 'completed') return 'completed';
        if(collect($steps)->contains('status', 'in-progress')) return 'running';
        if(collect($steps)->contains('status', 'running')) return 'running';
        if(collect($steps)->where('status', 'completed')->count() > 0) return 'running';
        return 'idle';
    }

}
