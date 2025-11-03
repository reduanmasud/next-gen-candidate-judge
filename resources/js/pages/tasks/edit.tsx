import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { useForm, Head, Link, router } from '@inertiajs/react';
import { Switch } from '@/components/ui/switch';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
    
interface Task {
    id: number;
    title: string;
    description: string;
    docker_compose_yaml: string;
    score: number;
    is_active: boolean;
}

interface EditTaskProps {
    task: Task;
}

export default function EditTask({ task }: EditTaskProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Tasks',
            href: '/tasks',
        },
        {
            title: task.title,
            href: `/tasks/${task.id}`,
        },
        {
            title: 'Edit',
            href: `/tasks/${task.id}/edit`,
        },
    ];

    const { data, setData, put, processing, errors } = useForm({
        title: task.title,
        description: task.description,
        docker_compose_yaml: task.docker_compose_yaml,
        score: task.score,
        is_active: task.is_active,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/tasks/${task.id}`);
    };

    const handleDelete = () => {
        router.delete(`/tasks/${task.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${task.title}`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card className="rounded-xl">
                    <CardHeader className="flex flex-col items-start gap-2 md:flex-row md:items-center md:justify-between">
                        <div>
                            <CardTitle className="text-2xl">Edit Task</CardTitle>
                            <CardDescription>
                                Update the task details and configuration
                            </CardDescription>
                        </div>
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="destructive" disabled={processing}>
                                    Delete Task
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>Delete this task?</DialogTitle>
                                <DialogDescription>
                                    This action cannot be undone. This will permanently
                                    delete the task and its associated data.
                                </DialogDescription>
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button variant="secondary">Cancel</Button>
                                    </DialogClose>
                                    <Button
                                        variant="destructive"
                                        onClick={handleDelete}
                                        disabled={processing}
                                    >
                                        Confirm delete
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </CardHeader>
                    <Separator />
                    <CardContent className="pt-6">
                        <form onSubmit={handleSubmit} className="grid gap-8 lg:grid-cols-3">
                            <div className="space-y-6 lg:col-span-2">
                                <div className="space-y-2">
                                    <Label htmlFor="title">Task Title</Label>
                                    <Input
                                        id="title"
                                        type="text"
                                        value={data.title}
                                        onChange={(e) => setData('title', e.target.value)}
                                        placeholder="Enter task title"
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        A short, descriptive name for this task.
                                    </p>
                                    <InputError message={errors.title} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="description">Description</Label>
                                    <Textarea
                                        id="description"
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        placeholder="Enter task description"
                                        rows={4}
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Provide details or instructions for the task.
                                    </p>
                                    <InputError message={errors.description} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="docker_compose_yaml">
                                        Docker Compose Configuration
                                    </Label>
                                    <Textarea
                                        id="docker_compose_yaml"
                                        value={data.docker_compose_yaml}
                                        onChange={(e) =>
                                            setData('docker_compose_yaml', e.target.value)
                                        }
                                        placeholder="Paste your docker-compose.yaml content here"
                                        rows={14}
                                        className="font-mono text-sm"
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Paste valid YAML. Use services, volumes, and networks as needed.
                                    </p>
                                    <InputError message={errors.docker_compose_yaml} />
                                </div>
                            </div>

                            <div className="space-y-6 lg:col-span-1">
                                <div className="space-y-2">
                                    <Label htmlFor="score">Score</Label>
                                    <Input
                                        id="score"
                                        type="number"
                                        value={data.score}
                                        onChange={(e) =>
                                            setData('score', parseInt(e.target.value))
                                        }
                                        placeholder="Enter task score"
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Higher scores indicate more difficult tasks.
                                    </p>
                                    <InputError message={errors.score} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="is_active">Active</Label>
                                    <div className="flex items-center gap-3">
                                        <Switch
                                            id="is_active"
                                            checked={data.is_active}
                                            onCheckedChange={(checked) =>
                                                setData('is_active', checked)
                                            }
                                            required
                                        />
                                        <span className="text-sm text-muted-foreground">
                                            Toggle to publish or hide this task
                                        </span>
                                    </div>
                                    <InputError message={errors.is_active} />
                                </div>

                                <Separator />

                                <div className="flex flex-col gap-3 sm:flex-row">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Updating...' : 'Update Task'}
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <Link href="/tasks">Cancel</Link>
                                    </Button>
                                </div>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}