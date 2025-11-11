import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { useState, useMemo, useEffect } from 'react';
import { PlayIcon, Lock, Box, Terminal, CheckCircle2, Clock, Loader2 } from 'lucide-react';
import WorkspaceProgressTracker from '@/components/WorkspaceProgressTracker';

interface Task {
    id: number;
    title: string;
    description: string;
    score: number;
    is_started: boolean;
    is_completed: boolean;
    is_preparing: boolean;
    is_locked_by_penalty: boolean;
    is_locked_by_completion: boolean;
    is_failed: boolean;
    is_completed_successfully: boolean;
    attempt_id: number | null;
    attempt_count: number;
    sandbox: boolean;
    allowssh: boolean;
    timer: number | null;
    started_at: string | null;
}

interface UserTasksIndexProps {
    tasks: Task[];
}

type TaskStatus = 'available' | 'running' | 'locked' | 'done';

export default function UserTasksIndex({ tasks }: UserTasksIndexProps) {
    const [startingTaskId, setStartingTaskId] = useState<number | null>(null);
    const [activeTab, setActiveTab] = useState<TaskStatus>('available');
    const [currentTime, setCurrentTime] = useState(Date.now());

    // Progress modal state
    const [showProgressModal, setShowProgressModal] = useState(false);
    const [progressAttemptId, setProgressAttemptId] = useState<number | null>(null);
    const [progressMetadata, setProgressMetadata] = useState<any>({});
    const [progressTaskInfo, setProgressTaskInfo] = useState<{ title: string; allowssh: boolean } | null>(null);

    // Update current time every second for timer calculations
    useEffect(() => {
        const interval = setInterval(() => {
            setCurrentTime(Date.now());
        }, 1000);

        return () => clearInterval(interval);
    }, []);

    // Poll for workspace progress when modal is open
    useEffect(() => {
        if (!showProgressModal || !progressAttemptId) {
            return;
        }

        const pollProgress = async () => {
            try {
                const response = await fetch(`/my-tasks/attempts/${progressAttemptId}/status`);
                const data = await response.json();

                setProgressMetadata(data.metadata || {});

                // If workspace is ready (status is 'running'), redirect to the workspace
                if (data.status === 'running') {
                    setShowProgressModal(false);
                    router.visit(`/my-tasks/attempts/${progressAttemptId}`);
                }
            } catch (error) {
                console.error('Error polling progress:', error);
            }
        };

        // Poll immediately
        pollProgress();

        // Then poll every 2 seconds
        const interval = setInterval(pollProgress, 2000);

        return () => clearInterval(interval);
    }, [showProgressModal, progressAttemptId]);

    // Calculate penalty-adjusted score based on attempt count
    const calculatePenaltyAdjustedScore = (baseScore: number, attemptCount: number): number => {
        // First viewing (0 attempts): show full points
        // After each incorrect attempt, reduce by 10%
        const nextAttemptNumber = attemptCount + 1;
        const penaltyPercentage = (nextAttemptNumber - 1) * 10;
        const maxPercentage = Math.max(0, 100 - penaltyPercentage);
        return Math.round((baseScore * maxPercentage) / 100);
    };

    // Categorize tasks by status
    const categorizedTasks = useMemo(() => {
        const available: Task[] = [];
        const running: Task[] = [];
        const locked: Task[] = [];
        const done: Task[] = [];

        tasks.forEach((task) => {
            // Priority order: locked by penalty > locked by completion > running/preparing > available
            if (task.is_locked_by_penalty) {
                // Task is locked due to too many failed attempts
                locked.push(task);
            } else if (task.is_locked_by_completion || task.is_completed) {
                // Task is completed (either successfully or not) and locked
                done.push(task);
            } else if (task.is_started || task.is_preparing) {
                // Task is currently running or being prepared
                running.push(task);
            } else {
                // Task is available to start
                available.push(task);
            }
        });

        console.log('Categorized tasks:', { available, running, locked, done });
        return { available, running, locked, done };
    }, [tasks]);

    const handleStart = async (task: Task) => {
        if (startingTaskId !== null || task.is_locked_by_penalty) {
            return;
        }

        if (task.is_started && task.attempt_id) {
            router.visit(`/my-tasks/attempts/${task.attempt_id}`);
            return;
        }

        // Set startingTaskId immediately to prevent double-clicks
        setStartingTaskId(task.id);

        // For sandbox tasks, show progress modal
        if (task.sandbox) {
            setProgressTaskInfo({
                title: task.title,
                allowssh: task.allowssh,
            });
        }

        router.post(
            `/my-tasks/${task.id}/start`,
            {},
            {
                onSuccess: (page: any) => {
                    // Extract attempt ID from the response
                    // The backend redirects to user-tasks.show with the attempt
                    const url = page.url || '';
                    const attemptIdMatch = url.match(/\/my-tasks\/attempts\/(\d+)/);

                    if (attemptIdMatch && task.sandbox) {
                        const attemptId = parseInt(attemptIdMatch[1], 10);
                        setProgressAttemptId(attemptId);
                        setShowProgressModal(true);
                    }
                },
                onError: (errors) => {
                    console.error('Error starting task:', errors);
                    setStartingTaskId(null);
                    setShowProgressModal(false);
                },
                onFinish: () => {
                    setStartingTaskId(null);
                },
            },
        );
    };

    // Render task cards
    const renderTaskCard = (task: Task) => {
        // Calculate the potential score for the next attempt
        const nextAttemptScore = calculatePenaltyAdjustedScore(
            task.score,
            task.attempt_count
        );

        // Determine what score to display based on task state
        const getScoreDisplay = () => {
            if (task.is_locked_by_completion || task.is_completed) {
                // For completed tasks, show base score (actual earned score would come from attempt data)
                return {
                    score: task.score,
                    label: 'Base Score',
                    className: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                };
            } else if (task.is_locked_by_penalty) {
                // For penalty-locked tasks, show the base score they couldn't achieve
                return {
                    score: task.score,
                    label: 'Base Score',
                    className: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                };
            } else if (task.is_started || task.is_preparing) {
                // For running tasks, show potential score for current attempt
                return {
                    score: nextAttemptScore,
                    label: 'Max Points',
                    className: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'
                };
            } else {
                // For available tasks, show potential score for next attempt
                return {
                    score: nextAttemptScore,
                    label: task.attempt_count > 0 ? 'Next Attempt' : 'Available',
                    className: 'bg-primary/10 text-primary'
                };
            }
        };

        const scoreDisplay = getScoreDisplay();

        // Calculate timer for running tasks
        const getTimerDisplay = () => {
            if (!task.timer || !task.started_at || (!task.is_started && !task.is_preparing)) {
                return null;
            }

            const startTime = new Date(task.started_at).getTime();
            const timerDuration = task.timer * 60 * 1000; // Convert minutes to milliseconds
            const elapsed = currentTime - startTime;
            const remaining = Math.max(0, timerDuration - elapsed);
            const remainingSeconds = Math.floor(remaining / 1000);

            // Format time as HH:MM:SS or MM:SS
            const hours = Math.floor(remainingSeconds / 3600);
            const minutes = Math.floor((remainingSeconds % 3600) / 60);
            const seconds = remainingSeconds % 60;

            const timeString = hours > 0
                ? `${hours}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
                : `${minutes}:${seconds.toString().padStart(2, '0')}`;

            // Determine color based on remaining time
            const isWarning = remaining <= (task.timer * 60 * 1000 * 0.2); // Last 20%
            const isDanger = remaining <= (task.timer * 60 * 1000 * 0.1); // Last 10%
            const isExpired = remaining === 0;

            return {
                timeString,
                isWarning,
                isDanger,
                isExpired,
            };
        };

        const timerDisplay = getTimerDisplay();

        // Determine border color and animation based on task state
        const getBorderClass = () => {
            if (task.is_completed) {
                return 'border-2 border-green-200 dark:border-green-800';
            } else if (task.is_locked_by_penalty) {
                return 'border-2 border-red-200 dark:border-red-800';
            }  else if (task.is_started || task.is_preparing) {
                // Animated border for running/preparing tasks
                return 'border-2 animate-border-pulse';
            }
            return 'border';
        };

        return (
            <div
                key={task.id}
                className={`group relative overflow-hidden rounded-lg bg-card p-6 shadow-sm transition-all hover:shadow-md ${getBorderClass()}`}
            >
                <div className="space-y-4">
                    <div className="flex items-start justify-between">
                        <div className="space-y-1 flex-1">
                            <div className="flex items-center gap-2">
                                <h3 className="text-xl font-semibold">
                                    {task.title}
                                </h3>

                                {/* Status Badge */}
                                {task.is_preparing && (
                                    <span className="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                        Preparing
                                    </span>
                                )}
                                {task.is_started && !task.is_preparing && (
                                    <span className="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                        Running
                                    </span>
                                )}
                                {task.is_locked_by_penalty && (
                                    <span className="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/30 dark:text-red-400">
                                        Locked
                                    </span>
                                )}

                                {/* Timer Display for Running Tasks */}
                                {timerDisplay && (
                                    <span
                                        className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                            timerDisplay.isExpired
                                                ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                                : timerDisplay.isDanger
                                                ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 animate-pulse'
                                                : timerDisplay.isWarning
                                                ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
                                                : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'
                                        }`}
                                    >
                                        <Clock className="h-3 w-3" />
                                        {timerDisplay.isExpired ? 'Time Up!' : timerDisplay.timeString}
                                    </span>
                                )}
                            </div>
                            <div className="flex items-center gap-2">
                                <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${scoreDisplay.className}`}>
                                    {scoreDisplay.score} points
                                </span>
                                {task.attempt_count > 0 && (
                                    <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                                        Attempt {task.attempt_count + 1}
                                    </span>
                                )}
                            </div>
                        </div>

                        {/* Status Icons */}
                        <div className="flex gap-2">
                            {task.sandbox && (
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30" title="Sandbox Available">
                                    <Box className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                </div>
                            )}
                            {task.allowssh && (
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30" title="SSH Access Available">
                                    <Terminal className="h-4 w-4 text-green-600 dark:text-green-400" />
                                </div>
                            )}
                        </div>
                    </div>

                    <p className="text-sm text-muted-foreground line-clamp-3">
                        {task.description}
                    </p>

                    <div className="pt-2">
                        {task.is_locked_by_penalty ? (
                            // Task locked due to too many failed attempts
                            <Button
                                className="w-full"
                                disabled
                                variant="destructive"
                            >
                                <Lock className="mr-2 h-4 w-4" />
                                Locked - Too Many Attempts
                            </Button>
                        ) : task.is_locked_by_completion || task.is_completed ? (
                            // Task locked because it's completed
                            <Button
                                className={`w-full bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-800`}
                                disabled
                                variant={'default'}
                            >
                                <CheckCircle2 className="mr-2 h-4 w-4" />
                                Completed Successfully
                            </Button>
                        ) : task.is_preparing ? (
                            // Task is being prepared
                            <Button
                                className="w-full"
                                disabled
                                variant="secondary"
                            >
                                <div className="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                Preparing Workspace...
                            </Button>
                        ) : (
                            // Task is available or running
                            <Button
                                className="w-full"
                                onClick={() => handleStart(task)}
                                disabled={startingTaskId !== null}
                            >
                                <PlayIcon className="mr-2 h-4 w-4" />
                                {task.is_started ? 'Open Workspace' : 'Start Task'}
                            </Button>
                        )}
                    </div>
                </div>

                {/* Decorative gradient */}
                <div className="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-primary/5 blur-2xl transition-all group-hover:bg-primary/10" />
            </div>
        );
    };

    // Render empty state
    const renderEmptyState = (message: string) => (
        <div className="flex min-h-[400px] items-center justify-center rounded-lg border border-dashed">
            <div className="text-center">
                <h3 className="text-lg font-medium">No tasks {message}</h3>
                <p className="text-sm text-muted-foreground">
                    {message === 'available' && 'All tasks have been started or completed'}
                    {message === 'running' && 'No tasks are currently in progress'}
                    {message === 'locked' && 'No tasks are locked'}
                    {message === 'done' && 'No tasks have been completed yet'}
                </p>
            </div>
        </div>
    );

    return (
        <AppLayout>
            <Head title="My Tasks" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div>
                    <h1 className="text-2xl font-semibold">My Tasks</h1>
                </div>

                <Tabs defaultValue="available" className="w-full" onValueChange={(value) => setActiveTab(value as TaskStatus)}>
                    <TabsList className="grid w-full max-w-2xl grid-cols-4">
                        <TabsTrigger value="available" className="flex items-center gap-2">
                            <PlayIcon className="h-4 w-4" />
                            Available
                            {categorizedTasks.available.length > 0 && (
                                <span className="ml-1 rounded-full bg-primary/20 px-2 py-0.5 text-xs">
                                    {categorizedTasks.available.length}
                                </span>
                            )}
                        </TabsTrigger>
                        <TabsTrigger value="running" className="flex items-center gap-2">
                            <Terminal className="h-4 w-4" />
                            Running
                            {categorizedTasks.running.length > 0 && (
                                <span className="ml-1 rounded-full bg-primary/20 px-2 py-0.5 text-xs">
                                    {categorizedTasks.running.length}
                                </span>
                            )}
                        </TabsTrigger>
                        <TabsTrigger value="locked" className="flex items-center gap-2">
                            <Lock className="h-4 w-4" />
                            Locked
                            {categorizedTasks.locked.length > 0 && (
                                <span className="ml-1 rounded-full bg-primary/20 px-2 py-0.5 text-xs">
                                    {categorizedTasks.locked.length}
                                </span>
                            )}
                        </TabsTrigger>
                        <TabsTrigger value="done" className="flex items-center gap-2">
                            <CheckCircle2 className="h-4 w-4" />
                            Done
                            {categorizedTasks.done.length > 0 && (
                                <span className="ml-1 rounded-full bg-primary/20 px-2 py-0.5 text-xs">
                                    {categorizedTasks.done.length}
                                </span>
                            )}
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="available" className="mt-6">
                        {categorizedTasks.available.length > 0 ? (
                            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                                {categorizedTasks.available.map(renderTaskCard)}
                            </div>
                        ) : (
                            renderEmptyState('available')
                        )}
                    </TabsContent>

                    <TabsContent value="running" className="mt-6">
                        {categorizedTasks.running.length > 0 ? (
                            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                                {categorizedTasks.running.map(renderTaskCard)}
                            </div>
                        ) : (
                            renderEmptyState('running')
                        )}
                    </TabsContent>

                    <TabsContent value="locked" className="mt-6">
                        {categorizedTasks.locked.length > 0 ? (
                            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                                {categorizedTasks.locked.map(renderTaskCard)}
                            </div>
                        ) : (
                            renderEmptyState('locked')
                        )}
                    </TabsContent>

                    <TabsContent value="done" className="mt-6">
                        {categorizedTasks.done.length > 0 ? (
                            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                                {categorizedTasks.done.map(renderTaskCard)}
                            </div>
                        ) : (
                            renderEmptyState('done')
                        )}
                    </TabsContent>
                </Tabs>
            </div>

            {/* Workspace Progress Modal */}
            {showProgressModal && progressTaskInfo && (
                <div className="fixed inset-0 z-50 bg-white/95 dark:bg-gray-950/95 flex items-center justify-center p-4">
                    <div className="w-full max-w-lg flex flex-col gap-6 rounded-lg border bg-card px-8 py-8 shadow-lg">
                        {/* Header */}
                        <div className="text-center">
                            <h2 className="text-2xl font-bold mb-2">
                                {progressTaskInfo.title}
                            </h2>
                            <p className="text-sm text-muted-foreground mb-1">
                                Preparing your workspace...
                            </p>
                            <p className="text-xs text-muted-foreground">
                                This may take a few moments
                            </p>
                        </div>

                        {/* Progress Tracker */}
                        <div className="space-y-4">
                            <WorkspaceProgressTracker
                                currentStep={progressMetadata.current_step || null}
                                allowSsh={progressTaskInfo.allowssh}
                            />
                        </div>

                        {/* Loading Indicator */}
                        <div className="flex items-center justify-center gap-2 text-sm text-muted-foreground">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            <span>Setting up your environment...</span>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
