import { useEffect, useState } from 'react';
import { CheckCircle2, Loader2 } from 'lucide-react';

interface ProgressStep {
  key: string;
  label: string;
  completedKey: string;
}

interface ServerProvisionProgressTrackerProps {
  currentStep: string | null;
}

const PROGRESS_STEPS: ProgressStep[] = [
  { key: 'starting_provision', label: 'Starting Provisioning', completedKey: 'starting_provision_completed' },
  { key: 'updating_packages', label: 'Updating Server Packages', completedKey: 'updating_packages_completed' },
  { key: 'installing_packages', label: 'Installing Necessary Packages', completedKey: 'installing_packages_completed' },
  { key: 'installing_docker', label: 'Installing Docker', completedKey: 'installing_docker_completed' },
  { key: 'updating_firewall', label: 'Updating Firewall Rules', completedKey: 'updating_firewall_completed' },
  { key: 'installing_traefik', label: 'Installing & Setting up Traefik', completedKey: 'completed' },
];

export default function ServerProvisionProgressTracker({ currentStep }: ServerProvisionProgressTrackerProps) {
  const [completedSteps, setCompletedSteps] = useState<Set<string>>(new Set());
  const [appearingSteps, setAppearingSteps] = useState<Set<string>>(new Set());
  const [visibleStepsSet, setVisibleStepsSet] = useState<Set<string>>(new Set());

  useEffect(() => {
    if (!currentStep) return;

    // Mark completed steps
    const completedStep = PROGRESS_STEPS.find(step => step.completedKey === currentStep);
    if (completedStep) {
      setCompletedSteps(prev => new Set(prev).add(completedStep.key));
    }

    // Mark current step as visible and animate
    const activeStep = PROGRESS_STEPS.find(step => step.key === currentStep);
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
  }, [currentStep]);

  const getStepStatus = (step: ProgressStep) => {
    if (completedSteps.has(step.key)) return 'completed';
    if (currentStep === step.key) return appearingSteps.has(step.key) ? 'appearing' : 'in-progress';
    return 'not-started';
  };

  const visibleSteps = PROGRESS_STEPS.filter(step => visibleStepsSet.has(step.key));

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

