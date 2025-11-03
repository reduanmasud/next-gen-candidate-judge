import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { PlayIcon } from 'lucide-react';

interface Task {
    id: number;
    title: string;
    description: string;
    score: number;
    is_started: boolean;
    attempt_id: number | null;
}

interface UserTasksIndexProps {
    tasks: Task[];
}

export default function UserTasksIndex({ tasks }: UserTasksIndexProps) {
    const handleStart = (taskId: number) => {
        // We'll implement this later
        alert(`Starting task ${taskId}`);
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
                                        onClick={() => handleStart(task.id)}
                                    >
                                        <PlayIcon className="mr-2 h-4 w-4" />
                                        Start Task
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
        </AppLayout>
    );
}