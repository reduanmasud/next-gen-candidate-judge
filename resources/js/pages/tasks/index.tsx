import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tasks',
        href: '/tasks',
    },
];

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

interface TasksIndexProps {
    tasks: {
        data: Task[];
        links: any[];
        meta: any;
    };
}

export default function TasksIndex({ tasks }: TasksIndexProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tasks" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Tasks</h1>
                        <p className="text-sm text-muted-foreground">
                            Manage and create tasks for your application
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/tasks/create">
                            <PlusIcon className="mr-2 h-4 w-4" />
                            Create Task
                        </Link>
                    </Button>
                </div>

                <div className="rounded-lg border">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-sm font-medium">
                                        Title
                                    </th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">
                                        Description
                                    </th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">
                                        Score
                                    </th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {tasks.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-8 text-center text-sm text-muted-foreground"
                                        >
                                            No tasks found. Create your first task to get started.
                                        </td>
                                    </tr>
                                ) : (
                                    tasks.data.map((task) => (
                                        <tr key={task.id} className="hover:bg-muted/50">
                                            <td className="px-4 py-3 text-sm font-medium">
                                                {task.title}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-muted-foreground">
                                                {task.description.substring(0, 50)}
                                                {task.description.length > 50 ? '...' : ''}
                                            </td>
                                            <td className="px-4 py-3 text-sm">
                                                {task.score}
                                            </td>
                                            <td className="px-4 py-3 text-sm">
                                                <span
                                                    className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${
                                                        task.is_active
                                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                            : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
                                                    }`}
                                                >
                                                    {task.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-sm">
                                                <div className="flex gap-2">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link href={`/tasks/${task.id}`}>
                                                            View
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link href={`/tasks/${task.id}/edit`}>
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}