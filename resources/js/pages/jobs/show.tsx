// resources/js/pages/jobs/show.tsx

import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

interface JobRun {
    id: number;
    script_name: string;
    script_path: string | null;
    script_content: string | null;
    status: 'pending' | 'running' | 'completed' | 'failed';
    output: string | null;
    error_output: string | null;
    exit_code: number | null;
    notes: string | null;
    metadata: Record<string, any> | null;
    user?: { id: number; name: string };
    task?: { id: number; title: string };
    server?: { id: number; name: string };
    attempt?: { id: number; status: string };
    created_at: string;
    started_at: string | null;
    completed_at: string | null;
}

interface Props {
    jobRun: JobRun;
}

export default function JobShow({ jobRun: initialJobRun }: Props) {
    const [jobRun, setJobRun] = useState(initialJobRun);

    // Poll for updates if job is pending or running
    useEffect(() => {
        if (jobRun.status === 'pending' || jobRun.status === 'running') {
            const interval = setInterval(async () => {
                const response = await fetch(`/jobs/${jobRun.id}/status`);
                const data = await response.json();

                setJobRun(prev => ({
                    ...prev,
                    status: data.status,
                    output: data.output,
                    error_output: data.error_output,
                    exit_code: data.exit_code,
                    script_content: data.script_content,
                    completed_at: data.completed_at,
                }));

                // Stop polling if completed or failed
                if (data.status === 'completed' || data.status === 'failed') {
                    clearInterval(interval);
                }
            }, 2000); // Poll every 2 seconds

            return () => clearInterval(interval);
        }
    }, [jobRun.id, jobRun.status]);

    return (
        <AppLayout>
            <Head title={`Job: ${jobRun.script_name}`} />
            
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                <div className="flex justify-between items-center">
                    <h1 className="text-3xl font-bold">{jobRun.script_name}</h1>
                    <Badge
                        variant={jobRun.status === 'failed' ? 'destructive' : 'default'}
                        className={jobRun.status === 'completed' ? 'bg-green-600 hover:bg-green-700' : ''}
                    >
                        {jobRun.status}
                    </Badge>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Job Details</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {jobRun?.user && <p><strong>Triggered by:</strong> {jobRun.user.name}</p>}
                        {jobRun?.task && <p><strong>Task:</strong> {jobRun.task.title}</p>}
                        {jobRun?.server && <p><strong>Server:</strong> {jobRun.server.name}</p>}
                        {jobRun?.attempt && (
                            <p><strong>Attempt ID:</strong> {jobRun.attempt.id} ({jobRun.attempt.status})</p>
                        )}
                        {jobRun.script_path && <p><strong>Script Path:</strong> {jobRun.script_path}</p>}
                        <p><strong>Created:</strong> {new Date(jobRun.created_at).toLocaleString()}</p>
                        {jobRun.started_at && (
                            <p><strong>Started:</strong> {new Date(jobRun.started_at).toLocaleString()}</p>
                        )}
                        {jobRun.completed_at && (
                            <p><strong>Completed:</strong> {new Date(jobRun.completed_at).toLocaleString()}</p>
                        )}
                        {jobRun.exit_code !== null && (
                            <p><strong>Exit Code:</strong> <span className={jobRun.exit_code === 0 ? 'text-green-600' : 'text-red-600'}>{jobRun.exit_code}</span></p>
                        )}
                    </CardContent>
                </Card>

                {jobRun.metadata && Object.keys(jobRun.metadata).length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Input Parameters</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                {Object.entries(jobRun.metadata).map(([key, value]) => (
                                    <p key={key}>
                                        <strong className="capitalize">{key.replace(/_/g, ' ')}:</strong>{' '}
                                        {typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value)}
                                    </p>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {jobRun.script_content && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Script Content (Input)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <pre className="bg-muted p-4 rounded-lg overflow-x-auto text-sm">
                                <code>{jobRun.script_content}</code>
                            </pre>
                        </CardContent>
                    </Card>
                )}

                {jobRun.output && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Standard Output (stdout)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <pre className="bg-slate-950 text-green-400 p-4 rounded-lg overflow-x-auto text-sm font-mono">
                                <code>{jobRun.output}</code>
                            </pre>
                        </CardContent>
                    </Card>
                )}

                {jobRun.error_output && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Error Output (stderr)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <pre className="bg-red-950 text-red-300 p-4 rounded-lg overflow-x-auto text-sm font-mono">
                                <code>{jobRun.error_output}</code>
                            </pre>
                        </CardContent>
                    </Card>
                )}

                {jobRun.notes && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Notes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p>{jobRun.notes}</p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}