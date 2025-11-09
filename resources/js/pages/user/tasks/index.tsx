import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { PlayIcon, Lock } from 'lucide-react';

interface Task {
    id: number;
    title: string;
    description: string;
    score: number;
    is_started: boolean;
    is_completed: boolean;
    is_locked: boolean;
    attempt_id: number | null;
}

interface UserTasksIndexProps {
    tasks: Task[];
}

export default function UserTasksIndex({ tasks }: UserTasksIndexProps) {
    const [startingTaskId, setStartingTaskId] = useState<number | null>(null);

    const handleStart = (task: Task) => {
        if (startingTaskId !== null || task.is_locked) {
            return;
        }

        if (task.is_started && task.attempt_id) {
            router.visit(`/my-tasks/attempts/${task.attempt_id}`);
            return;
        }

        // Set startingTaskId immediately to prevent double-clicks
        setStartingTaskId(task.id);

        router.post(
            `/my-tasks/${task.id}/start`,
            {},
            {
                onError: (errors) => {
                    console.error('Error starting task:', errors);
                    setStartingTaskId(null);
                },
                onFinish: () => {
                    setStartingTaskId(null);
                },
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
                                    {task.is_locked ? (
                                        <Button
                                            className="w-full"
                                            disabled
                                            variant="destructive"
                                        >
                                            <Lock className="mr-2 h-4 w-4" />
                                            Task Locked
                                        </Button>
                                    ) : (
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
        </AppLayout>
    );
}
