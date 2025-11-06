import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { PlayIcon } from 'lucide-react';
import { Spinner } from '@/components/ui/spinner';

interface Task {
    id: number;
    title: string;
    description: string;
    score: number;
    is_started: boolean;
    is_completed: boolean;
    attempt_id: number | null;
}

interface UserTasksIndexProps {
    tasks: Task[];
}

export default function UserTasksIndex({ tasks }: UserTasksIndexProps) {
    const [preparingTaskId, setPreparingTaskId] = useState<number | null>(null);

    const preparingTask = useMemo(
        () => tasks.find((task) => task.id === preparingTaskId) ?? null,
        [preparingTaskId, tasks],
    );

    const handleStart = (task: Task) => {
        if (preparingTaskId !== null) {
            return;
        }

        if (task.is_started && task.attempt_id) {
            router.visit(`/my-tasks/attempts/${task.attempt_id}`);
            return;
        }

        // Set preparingTaskId immediately to prevent double-clicks
        setPreparingTaskId(task.id);

        router.post(
            `/my-tasks/${task.id}/start`,
            {},
            {
                onError: () => setPreparingTaskId(null),
                onCancel: () => setPreparingTaskId(null),
            },
        );
    };

    return (
        <AppLayout>
            <Head title="My Tasks" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div>
                    <h1 className="text-2xl font-semibold">My Tasks</h1>
                </div>

                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {tasks.map((task) => (
                        <div
                            key={task.id}
                            className="group relative overflow-hidden rounded-lg border bg-card p-6 shadow-sm transition-all hover:shadow-md"
                        >
                            <div className="space-y-4">
                                <div className="flex items-start justify-between">
                                    <div className="space-y-1">
                                        <h3 className="text-xl font-semibold">
                                            {task.title}
                                        </h3>
                                        <div className="flex items-center gap-2">
                                            <span className="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">
                                                {task.score} points
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <p className="text-sm text-muted-foreground line-clamp-3">
                                    {task.description}
                                </p>

                                <div className="pt-2">
                                    <Button
                                        className="w-full"
                                        onClick={() => handleStart(task)}
                                        disabled={preparingTaskId !== null}
                                    >
                                        <PlayIcon className="mr-2 h-4 w-4" />
                                        {task.is_started ? 'Open Workspace' : 'Start Task'}
                                    </Button>
                                </div>
                            </div>

                            {/* Decorative gradient */}
                            <div className="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-primary/5 blur-2xl transition-all group-hover:bg-primary/10" />
                        </div>
                    ))}
                </div>

                {tasks.length === 0 && (
                    <div className="flex min-h-[400px] items-center justify-center rounded-lg border border-dashed">
                        <div className="text-center">
                            <h3 className="text-lg font-medium">No tasks available</h3>
                            <p className="text-sm text-muted-foreground">
                                Check back later for new challenges
                            </p>
                        </div>
                    </div>
                )}
            </div>

            {preparingTask && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur-sm">
                    <div className="flex w-full max-w-md flex-col items-center gap-4 rounded-lg border bg-card px-8 py-6 text-center shadow-lg">
                        <Spinner className="h-8 w-8" />
                        <div>
                            <p className="text-lg font-semibold">
                                Preparing your workspace…
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Setting up Docker environment for{' '}
                                <span className="font-medium">{preparingTask.title}</span>. This may take a moment.
                            </p>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
