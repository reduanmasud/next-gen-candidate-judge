import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Task {
    id: number;
    title: string;
    description: string;
    docker_compose_yaml: string;
    score: number;
    is_active: boolean;
    created_at: string;
    user?: {
        name: string;
    };
}

interface ShowTaskProps {
    task: Task;
}

export default function ShowTask({ task }: ShowTaskProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Tasks',
            href: '/tasks',
        },
        {
            title: task.title,
            href: `/tasks/${task.id}`,
        },
    ];

    const handleCopyYaml = () => {
        if (!task.docker_compose_yaml) return;
        void navigator.clipboard.writeText(task.docker_compose_yaml);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={task.title} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                <div className="flex items-start justify-between rounded-lg border bg-muted/50 p-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">{task.title}</h1>
                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <span>Created {new Date(task.created_at).toLocaleDateString()}</span>
                            {task.user && <span>• by {task.user.name}</span>}
                            <span>•</span>
                            <span
                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium ${
                                    task.is_active
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                        : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
                                }`}
                            >
                                {task.is_active ? 'Active' : 'Inactive'}
                            </span>
                            <span className="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
                                Score: {task.score}
                            </span>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button asChild>
                            <Link href={`/tasks/${task.id}/edit`}>Edit Task</Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/tasks">Back</Link>
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div className="space-y-6 md:col-span-2">
                        <div className="rounded-lg border p-6">
                            <h3 className="mb-2 text-sm font-medium text-muted-foreground">Description</h3>
                            <p className="text-sm leading-6 text-foreground/90">
                                {task.description?.trim() ? task.description : 'No description provided.'}
                            </p>
                        </div>

                        <div className="rounded-lg border">
                            <div className="flex items-center justify-between border-b p-4">
                                <h3 className="text-sm font-medium text-muted-foreground">Docker Compose Configuration</h3>
                                <Button size="sm" variant="outline" onClick={handleCopyYaml}>Copy</Button>
                            </div>
                            <div className="p-0">
                                <pre className="max-h-[60vh] overflow-x-auto overflow-y-auto rounded-b-lg bg-muted p-4 text-xs leading-relaxed">
                                    <code className="whitespace-pre-wrap">{task.docker_compose_yaml?.trim() || '# No docker-compose content'}</code>
                                </pre>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-6">
                        <div className="rounded-lg border p-6">
                            <h3 className="mb-4 text-sm font-medium text-muted-foreground">Details</h3>
                            <div className="space-y-3 text-sm">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Status</span>
                                    <span
                                        className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium ${
                                            task.is_active
                                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
                                        }`}
                                    >
                                        {task.is_active ? 'Active' : 'Inactive'}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Score</span>
                                    <span className="rounded-md bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">{task.score}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Owner</span>
                                    <span className="text-foreground/80">{task.user?.name || '—'}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Created</span>
                                    <span className="text-foreground/80">{new Date(task.created_at).toLocaleDateString()}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}