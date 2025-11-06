// resources/js/pages/jobs/index.tsx

import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface JobRun {
    id: number;
    script_name: string;
    status: 'pending' | 'running' | 'completed' | 'failed';
    user: { id: number; name: string };
    task?: { id: number; title: string };
    server?: { id: number; name: string };
    created_at: string;
    started_at: string | null;
    completed_at: string | null;
}

interface Props {
    jobRuns: {
        data: JobRun[];
        links: any[];
        meta: any;
    };
    filters: {
        status?: string;
        user_id?: number;
    };
}

export default function JobsIndex({ jobRuns, filters }: Props) {
    const handleFilterChange = (status: string) => {
        router.get('/jobs', { status: status === 'all' ? undefined : status }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const getStatusBadge = (status: string) => {
        const variants = {
            pending: 'secondary',
            running: 'default',
            completed: 'success',
            failed: 'destructive',
        };
        return <Badge variant={variants[status]}>{status}</Badge>;
    };

    return (
        <AppLayout>
            <Head title="Job Runs" />
            
            <div className="space-y-6">
                <div className="flex justify-between items-center">
                    <h1 className="text-3xl font-bold">Script Job Runs</h1>
                    
                    <Select value={filters.status || 'all'} onValueChange={handleFilterChange}>
                        <SelectTrigger className="w-[180px]">
                            <SelectValue placeholder="Filter by status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Jobs</SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
                            <SelectItem value="running">Running</SelectItem>
                            <SelectItem value="completed">Completed</SelectItem>
                            <SelectItem value="failed">Failed</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Job History</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {jobRuns.data.map((job) => (
                                <Link
                                    key={job.id}
                                    href={`/jobs/${job.id}`}
                                    className="block p-4 border rounded-lg hover:bg-accent transition"
                                >
                                    <div className="flex justify-between items-start">
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <h3 className="font-semibold">{job.script_name}</h3>
                                                {getStatusBadge(job.status)}
                                            </div>
                                            <p className="text-sm text-muted-foreground">
                                                Triggered by {job.user.name}
                                            </p>
                                            {job.task && (
                                                <p className="text-sm text-muted-foreground">
                                                    Task: {job.task.title}
                                                </p>
                                            )}
                                            {job.server && (
                                                <p className="text-sm text-muted-foreground">
                                                    Server: {job.server.name}
                                                </p>
                                            )}
                                        </div>
                                        <div className="text-right text-sm text-muted-foreground">
                                            <p>Created: {new Date(job.created_at).toLocaleString()}</p>
                                            {job.completed_at && (
                                                <p>Completed: {new Date(job.completed_at).toLocaleString()}</p>
                                            )}
                                        </div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}