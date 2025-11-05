import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { useForm, Head, Link } from '@inertiajs/react';
import { Switch } from '@/components/ui/switch';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tasks',
        href: '/tasks',
    },
    {
        title: 'Create Task',
        href: '/tasks/create',
    },
];

export default function CreateTask({ servers = [] as { id: number; name: string; ip_address: string }[] }) {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        description: '',
        docker_compose_yaml: '',
        score: 0,
        is_active: true,
        server_id: '' as number | ''
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/tasks');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Task" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card className="rounded-xl">
                    <CardHeader>
                        <CardTitle className="text-2xl">Create New Task</CardTitle>
                        <CardDescription>
                            Fill in the details below to create a new task
                        </CardDescription>
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
                                        autoFocus
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
                                    <Label htmlFor="server_id">Target Server (optional)</Label>
                                    <select
                                        id="server_id"
                                        className="w-full rounded-md border bg-background p-2 text-sm"
                                        value={data.server_id as any}
                                        onChange={(e) => setData('server_id', e.target.value ? Number(e.target.value) : '')}
                                    >
                                        <option value="">Local (default)</option>
                                        {servers.map((s) => (
                                            <option key={s.id} value={s.id}>
                                                {s.name} ({s.ip_address})
                                            </option>
                                        ))}
                                    </select>
                                    <p className="text-xs text-muted-foreground">
                                        Choose a provisioned server to run this task on.
                                    </p>
                                    <InputError message={errors.server_id} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="score">Score</Label>
                                    <Input
                                        id="score"
                                        type="number"
                                        value={data.score}
                                        onChange={(e) => setData('score', parseInt(e.target.value))}
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
                                            onCheckedChange={(checked) => setData('is_active', checked)}
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
                                        {processing ? 'Creating...' : 'Create Task'}
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