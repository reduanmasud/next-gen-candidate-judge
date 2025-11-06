import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface TaskResource {
    id: number;
    title: string;
    description: string;
    score: number;
}

interface AttemptResource {
    id: number;
    status: string;
    started_at: string | null;
    container_id: string | null;
    container_name: string | null;
    container_port: number | null;
    notes: string | null;
}

interface WorkspaceResource {
    terminal_url: string | null;
    username: string | null;
    password: string | null;
    mode: string | null;
    path: string | null;
}

interface UserTaskWorkspaceProps {
    task: TaskResource;
    attempt: AttemptResource;
    workspace: WorkspaceResource;
}

const statusLabels: Record<string, string> = {
    pending: 'Preparing',
    running: 'In Progress',
    completed: 'Completed',
    failed: 'Failed',
    terminated: 'Terminated',
};

export default function UserTaskWorkspace({ task, attempt, workspace }: UserTaskWorkspaceProps) {
    const [terminalReady, setTerminalReady] = useState(false);
    const [isRestarting, setIsRestarting] = useState(false);

    const startedAtText = useMemo(() => {
        if (!attempt.started_at) {
            return 'Not started yet';
        }

        try {
            return new Intl.DateTimeFormat(undefined, {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(new Date(attempt.started_at));
        } catch (error) {
            return attempt.started_at;
        }
    }, [attempt.started_at]);

    const statusText = statusLabels[attempt.status] ?? attempt.status;

    const handleRestart = () => {
        if (isRestarting) {
            return;
        }

        router.post(
            `/my-tasks/attempts/${attempt.id}/restart`,
            {},
            {
                onStart: () => setIsRestarting(true),
                onError: () => setIsRestarting(false),
                onCancel: () => setIsRestarting(false),
            },
        );
    };

    return (
        <AppLayout>
            <Head title={`${task.title} · Workspace`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-hidden p-4">

                {attempt.status === 'pending' ? (
                    
                    <div className="flex w-full max-w-md flex-col items-center gap-4 rounded-lg border bg-card px-8 py-6 text-center shadow-lg">
                        <Spinner className="h-8 w-8" />
                        <div>
                            <p className="text-lg font-semibold">
                                Preparing your workspace…
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Setting up Docker environment for{' '}
                                <span className="font-medium">{task.title}</span>. This may take a moment.
                            </p>
                        </div>
                    </div>
                
                ) : (

                <>
                <div className="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{task.title}</h1>
                        <p className="text-sm text-muted-foreground">
                            Score: {task.score} · Status: {statusText}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => router.visit('/my-tasks')}>
                            Back to Tasks
                        </Button>
                        <Button
                            variant="default"
                            onClick={handleRestart}
                            disabled={isRestarting || attempt.status === 'pending'}
                        >
                            {isRestarting || attempt.status === 'pending' ? (
                                <>
                                    <Spinner className="mr-2 h-4 w-4" />
                                    Restarting...
                                </>
                            ) : (
                                'Re-start'
                            )}
                        </Button>
                    </div>
                </div>

                <div className="grid flex-1 gap-4 lg:grid-cols-[minmax(0,400px)_minmax(0,1fr)]">
                    <Card className="flex h-full flex-col gap-4 overflow-hidden p-6">
                        <div>
                            <h2 className="text-lg font-semibold">Instructions</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Follow the guidance below to complete the task.
                            </p>
                        </div>
                        <Separator />
                        <div className="flex-1 overflow-auto whitespace-pre-wrap rounded-md bg-muted/40 p-4 text-sm leading-relaxed">
                            {task.description}
                        </div>
                        <Separator />
                        <div className="space-y-3 text-sm">
                            <div>
                                <h3 className="text-sm font-semibold">Attempt Details</h3>
                                <dl className="mt-2 space-y-2">
                                    <div className="flex justify-between">
                                        <dt className="text-muted-foreground">Started</dt>
                                        <dd>{startedAtText}</dd>
                                    </div>
                                    <div className="flex justify-between">
                                        <dt className="text-muted-foreground">Container</dt>
                                        <dd>{attempt.container_name ?? 'Pending'}</dd>
                                    </div>
                                    {attempt.container_port && (
                                        <div className="flex justify-between">
                                            <dt className="text-muted-foreground">Port</dt>
                                            <dd>{attempt.container_port}</dd>
                                        </div>
                                    )}
                                    {workspace.mode && (
                                        <div className="flex justify-between">
                                            <dt className="text-muted-foreground">Mode</dt>
                                            <dd className="capitalize">{workspace.mode}</dd>
                                        </div>
                                    )}
                                    {workspace.path && (
                                        <div className="flex justify-between">
                                            <dt className="text-muted-foreground">Workspace path</dt>
                                            <dd className="truncate text-xs font-mono">{workspace.path}</dd>
                                        </div>
                                    )}
                                </dl>
                            </div>
                            {(workspace.username || workspace.password) && (
                                <div>
                                    <h3 className="text-sm font-semibold">Workspace Credentials</h3>
                                    <dl className="mt-2 space-y-2">
                                        {workspace.username && (
                                            <div className="flex justify-between">
                                                <dt className="text-muted-foreground">Username</dt>
                                                <dd>{workspace.username}</dd>
                                            </div>
                                        )}
                                        {workspace.password && (
                                            <div className="flex justify-between">
                                                <dt className="text-muted-foreground">Password</dt>
                                                <dd className="font-mono">{workspace.password}</dd>
                                            </div>
                                        )}
                                    </dl>
                                </div>
                            )}
                            {workspace.mode === 'local' && (
                                <div className="rounded-md bg-muted/40 p-3 text-xs text-muted-foreground">
                                    Containers run under the web server user. No extra SSH credentials are required.
                                </div>
                            )}
                        </div>
                    </Card>

                    <Card className="relative flex h-full flex-col overflow-hidden">
                        <div className="border-b p-4">
                            <h2 className="text-lg font-semibold">Terminal</h2>
                            <p className="text-sm text-muted-foreground">
                                Interact with your Docker workspace on the right.
                            </p>
                        </div>
                        <div className="relative flex-1 overflow-hidden bg-black">
                            {workspace.terminal_url ? (
                                <>
                                    {!terminalReady && (
                                        <div className="absolute inset-0 z-10 flex items-center justify-center bg-black/80">
                                            <div className="flex flex-col items-center gap-3 text-center text-white">
                                                <Spinner className="h-6 w-6" />
                                                <p className="text-sm">Connecting to terminal…</p>
                                            </div>
                                        </div>
                                    )}
                                    <iframe
                                        src={workspace.terminal_url}
                                        title="Workspace Terminal"
                                        onLoad={() => setTerminalReady(true)}
                                        className="h-full w-full border-0"
                                        allow="clipboard-read; clipboard-write"
                                    />
                                </>
                            ) : (
                                <div className="flex h-full items-center justify-center p-6 text-center text-sm text-muted-foreground">
                                    Terminal connection details are not available. Please refresh or contact support.
                                </div>
                            )}
                        </div>
                    </Card>

                    {/* only for admin */}
                    
                    <Card className="rounded-xl">
                            <CardHeader>
                                <CardTitle>Notes</CardTitle>
                                <CardDescription>Provisioning logs and notes</CardDescription>
                            </CardHeader>
                            <Separator />
                            <CardContent className="pt-6">
                                {attempt.notes ? (
                                    <pre className="max-h-96 overflow-auto rounded-lg bg-muted p-4 text-xs font-mono whitespace-pre-wrap">
                                        {attempt.notes}
                                    </pre>
                                ) : (
                                    <p className="text-sm text-muted-foreground">No notes available</p>
                                )}
                            </CardContent>
                    </Card> 

                </div>
                </>
                )}


            </div>
        </AppLayout>
    );
}
