import { CheckCircle2, Loader2, XCircle, AlertCircle } from 'lucide-react';
import { WorkflowState } from '@/hooks/use-workflow-progress';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';

interface WorkflowProgressTrackerProps {
  workflow: WorkflowState;
  onRetry?: () => void;
  variant?: 'default' | 'compact';
}

export default function WorkflowProgressTracker({
  workflow,
  onRetry,
  variant = 'default',
}: WorkflowProgressTrackerProps) {
  const { steps, progress, status } = workflow;

  if (variant === 'compact') {
    return (
      <div className="space-y-2">
        <div className="flex items-center justify-between text-sm">
          <span className="font-medium">{workflow.name}</span>
          <span className="text-muted-foreground">
            {progress.completed}/{progress.total} steps
          </span>
        </div>
        <Progress value={progress.percentage} className="h-2" />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-semibold">{workflow.name}</h3>
          <span className="text-sm text-muted-foreground">
            {progress.percentage.toFixed(0)}% Complete
          </span>
        </div>
        <Progress value={progress.percentage} className="h-2" />
      </div>

      {/* Steps */}
      <div className="space-y-3">
        {steps.map(step => {
          const isInProgress = step.status === 'in-progress';
          const isCompleted = step.status === 'completed';
          const isFailed = step.status === 'failed';
          const isPending = step.status === 'pending';

          return (
            <div
              key={step.id}
              className={`flex items-start gap-3 transition-all duration-300 ${
                isInProgress ? 'animate-in fade-in slide-in-from-bottom-2' : ''
              }`}
            >
              {/* Icon */}
              <div className="relative h-5 w-5 flex-shrink-0 mt-0.5">
                {isInProgress && (
                  <Loader2 className="h-5 w-5 animate-spin text-primary" />
                )}
                {isCompleted && (
                  <CheckCircle2 className="h-5 w-5 text-green-500" />
                )}
                {isFailed && (
                  <XCircle className="h-5 w-5 text-destructive" />
                )}
                {isPending && (
                  <div className="h-5 w-5 rounded-full border-2 border-muted-foreground/30" />
                )}
              </div>

              {/* Content */}
              <div className="flex-1 space-y-1">
                <div className="flex items-center justify-between">
                  <p className={`font-medium ${
                    isInProgress ? 'text-primary' :
                    isCompleted ? 'text-green-600' :
                    isFailed ? 'text-destructive' :
                    'text-muted-foreground'
                  }`}>
                    {step.label}
                  </p>

                  {/* Duration or estimated time */}
                  {step.duration !== null && (
                    <span className="text-xs text-muted-foreground">
                      {formatDuration(step.duration)}
                    </span>
                  )}
                  {step.duration === null && isInProgress && (
                    <span className="text-xs text-muted-foreground">
                      ~{step.estimatedDuration}s
                    </span>
                  )}
                </div>

                {/* Description */}
                <p className="text-sm text-muted-foreground">
                  {step.description}
                </p>

                {/* Error message for failed steps */}
                {isFailed && step.errorMessage && (
                  <div className="flex items-start gap-2 mt-2 p-2 bg-destructive/10 rounded-md">
                    <AlertCircle className="h-4 w-4 text-destructive flex-shrink-0 mt-0.5" />
                    <p className="text-sm text-destructive">
                      {step.errorMessage}
                    </p>
                  </div>
                )}

                {/* Timestamps */}
                {(step.startedAt || step.completedAt || step.failedAt) && (
                  <div className="text-xs text-muted-foreground space-y-0.5">
                    {step.startedAt && (
                      <div>Started: {formatTimestamp(step.startedAt)}</div>
                    )}
                    {step.completedAt && (
                      <div>Completed: {formatTimestamp(step.completedAt)}</div>
                    )}
                    {step.failedAt && (
                      <div>Failed: {formatTimestamp(step.failedAt)}</div>
                    )}
                  </div>
                )}
              </div>
            </div>
          );
        })}
      </div>

      {/* Footer - Retry button for failed workflows */}
      {status === 'failed' && onRetry && (
        <div className="pt-4 border-t">
          <Button onClick={onRetry} variant="outline" className="w-full">
            Retry Workflow
          </Button>
        </div>
      )}

      {/* Completed message */}
      {status === 'completed' && (
        <div className="flex items-center gap-2 p-3 bg-green-50 dark:bg-green-950/20 rounded-md">
          <CheckCircle2 className="h-5 w-5 text-green-600" />
          <p className="text-sm font-medium text-green-600">
            Workflow completed successfully!
          </p>
        </div>
      )}
    </div>
  );
}

/**
 * Format duration in seconds to human-readable format
 */
function formatDuration(seconds: number): string {
  if (seconds < 60) {
    return `${seconds}s`;
  }

  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = seconds % 60;

  if (remainingSeconds === 0) {
    return `${minutes}m`;
  }

  return `${minutes}m ${remainingSeconds}s`;
}

/**
 * Format ISO timestamp to human-readable format
 */
function formatTimestamp(timestamp: string): string {
  const date = new Date(timestamp);
  return date.toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
}
