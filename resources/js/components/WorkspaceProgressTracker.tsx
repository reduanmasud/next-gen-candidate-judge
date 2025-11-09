import { useEffect, useState } from 'react';
import { CheckCircle2, Loader2 } from 'lucide-react';

interface ProgressStep {
  key: string;
  label: string;
  completedKey: string;
}

interface WorkspaceProgressTrackerProps {
  currentStep: string | null;
  allowSsh: boolean;
}

const PROGRESS_STEPS: ProgressStep[] = [
  { key: 'creating_user', label: 'Creating User', completedKey: 'creating_user_completed' },
  { key: 'finding_free_port', label: 'Finding Free Port', completedKey: 'finding_free_port_completed' },
  { key: 'setting_docker_compose', label: 'Setting Docker Compose Configuration', completedKey: 'setting_docker_compose_completed' },
  { key: 'starting_docker_compose', label: 'Starting Docker Compose', completedKey: 'starting_docker_compose_completed' },
  { key: 'setting_ssh_access', label: 'Setting SSH Access', completedKey: 'setting_ssh_access_completed' },
  { key: 'finalizing_workspace', label: 'Finalizing Workspace', completedKey: 'completed' },
];

export default function WorkspaceProgressTracker({ currentStep, allowSsh }: WorkspaceProgressTrackerProps) {
  const [completedSteps, setCompletedSteps] = useState<Set<string>>(new Set());
  const [appearingSteps, setAppearingSteps] = useState<Set<string>>(new Set());
  const [visibleStepsSet, setVisibleStepsSet] = useState<Set<string>>(new Set());

  // Filter steps based on SSH requirement
  const steps = PROGRESS_STEPS.filter(step => allowSsh || step.key !== 'setting_ssh_access');

  useEffect(() => {
    if (!currentStep) return;

    // Mark completed steps
    const completedStep = steps.find(step => step.completedKey === currentStep);
    if (completedStep) {
      setCompletedSteps(prev => new Set(prev).add(completedStep.key));
    }

    // Mark current step as visible and animate
    const activeStep = steps.find(step => step.key === currentStep);
    if (activeStep) {
      // Add to visible steps
      setVisibleStepsSet(prev => new Set(prev).add(activeStep.key));

      // Trigger appearing animation
      setAppearingSteps(prev => new Set(prev).add(activeStep.key));
      const timer = setTimeout(() => {
        setAppearingSteps(prev => {
          const newSet = new Set(prev);
          newSet.delete(activeStep.key);
          return newSet;
        });
      }, 500); // match animation duration

      return () => clearTimeout(timer);
    }
  }, [currentStep, steps]);

  const getStepStatus = (step: ProgressStep) => {
    if (completedSteps.has(step.key)) return 'completed';
    if (currentStep === step.key) return appearingSteps.has(step.key) ? 'appearing' : 'in-progress';
    return 'not-started';
  };

  const visibleSteps = steps.filter(step => visibleStepsSet.has(step.key));

  if (visibleSteps.length === 0) return null;

  return (
    <div className="space-y-3">
      {visibleSteps.map(step => {
        const status = getStepStatus(step);

        return (
          <div
            key={step.key}
            className={`flex items-center gap-3 transition-all duration-500 ease-out ${
              status === 'appearing' ? 'animate-in fade-in slide-in-from-bottom-2' : 'opacity-100 translate-y-0'
            }`}
          >
            <div className="relative h-5 w-5">
            { (status === 'appearing' || status === 'in-progress') ? (
                <Loader2 className="absolute inset-0 h-5 w-5 animate-spin text-primary transition-opacity duration-300" />
            ): (
                <CheckCircle2 className="absolute inset-0 h-5 w-5 text-green-500 animate-in fade-in duration-300" />
            )}
            </div>
            <span
              className={`text-sm transition-colors duration-300 ${
                status === 'appearing' || status === 'in-progress' ? 'font-medium text-foreground' : 'text-muted-foreground'
              }`}
            >
              {step.label}
            </span>
          </div>
        );
      })}
    </div>
  );
}
