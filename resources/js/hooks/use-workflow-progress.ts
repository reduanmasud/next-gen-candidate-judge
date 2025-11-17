import { useEffect, useState } from 'react';
import { useEcho } from '@laravel/echo-react';


export interface WorkflowStep {
    id: string;
    label: string;
    description: string;
    icon: string;
    estimatedDuration: number;
    status: 'pending' | 'in-progress' | 'completed' | 'failed';
    startedAt: string | null;
    completedAt: string | null;
    failedAt: string | null;
    errorMessage: string | null;
    duration: number | null;
}

export interface WorkflowProgress {
    completed: number;
    total: number;
    percentage: number;
}

export interface WorkflowState {
    type: string;
    name: string;
    category: string;
    steps: WorkflowStep[];
    currentStepId: string | null;
    status: 'idle' | 'running' | 'completed' | 'failed';
    progress: WorkflowProgress;
    startedAt: string | null;
    completedAt: string | null;
    failedAt: string | null;
}

export interface UseWorkflowProgressOptions {
    initialWorkflow: WorkflowState;
    channel: string;
    event: string;
    onComplete?: () => void;
    onFail?: (errorMessage: string | null) => void;
}

export interface UseWorkflowProgressReturn {
    workflow: WorkflowState;
    currentStep: WorkflowStep | null;
    isRunning: boolean;
    isCompleted: boolean;
    isFailed: boolean;
    isIdle: boolean;
    progress: WorkflowProgress;
}


export function useWorkflowProgress(
    options: UseWorkflowProgressOptions
): UseWorkflowProgressReturn {

    const { initialWorkflow, channel, event, onComplete, onFail } = options;

    const [workflow, setWorkflow] = useState<WorkflowState>(initialWorkflow);

    useEcho(channel, event, (eventData: any) => {
        console.log('Workflow event received:', eventData);

        const updatedWorkflow = rebuildWorkflowFromMetadata(workflow, eventData.metadata || {});
        setWorkflow(updatedWorkflow);

        if(updatedWorkflow.status === 'completed' && onComplete) {
            onComplete();
        } else if(updatedWorkflow.status === 'failed' && onFail) {
            const failedStep = updatedWorkflow.steps.find(step => step.status === 'failed');
            onFail(failedStep?.errorMessage || null);
        }
    });

    const currentStep = workflow.steps.find(step => step.id === workflow.currentStepId) || null;
    const isRunning = workflow.status === 'running';
    const isCompleted = workflow.status === 'completed';
    const isFailed = workflow.status === 'failed';
    const isIdle = workflow.status === 'idle';

    return {
        workflow,
        currentStep,
        isRunning,
        isCompleted,
        isFailed,
        isIdle,
        progress: workflow.progress,
    };
}

function rebuildWorkflowFromMetadata(
    currentWorkflow: WorkflowState,
    metadata: Record<string, any>
): WorkflowState {
    const stepHistory = metadata.step_history ?? {};
    const currentStepId = metadata.current_step ?? null;

    const updatedSteps = currentWorkflow.steps.map(step => {
        const historyEntry = stepHistory[step.id] ?? null;

        if(historyEntry) {
            return {
                ...step,
                status: historyEntry.status,
                startedAt: historyEntry.started_at,
                completedAt: historyEntry.completed_at,
                failedAt: historyEntry.failed_at,
                errorMessage: historyEntry.error_message,
                duration: calculateDuration(historyEntry),
            };
        }
        return step;
    });

    const completed = updatedSteps.filter(step => step.status === 'completed').length;
    const total = updatedSteps.length;
    const percentage = total > 0 ? (completed / total) * 100 : 0;

    let status: WorkflowState['status'] = 'idle';
    if(currentStepId === 'completed') {
        status = 'completed';
    } else if(currentStepId === 'failed') {
        status = 'failed';
    } else if(updatedSteps.some(step => step.status === 'in-progress')) {
        status = 'running';
    }

    return {
        ...currentWorkflow,
        steps: updatedSteps,
        currentStepId,
        status,
        progress: {
            completed,
            total,
            percentage: Math.round(percentage*100)/100,
        },
        completedAt: metadata.workflow_completed_at ?? currentWorkflow.completedAt
    }
}

function calculateDuration(historyEntry: any): number | null {
  const startedAt = historyEntry.started_at;
  const endedAt = historyEntry.completed_at || historyEntry.failed_at;

  if (startedAt && endedAt) {
    const start = new Date(startedAt).getTime();
    const end = new Date(endedAt).getTime();
    return Math.floor((end - start) / 1000); // seconds
  }

  return null;
}
