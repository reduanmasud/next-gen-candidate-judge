// resources/js/pages/jobs/index.tsx

import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Clock, User, Server as ServerIcon, CheckCircle2, XCircle, Loader2, RefreshCw, Eye } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useEcho } from '@laravel/echo-react';

interface JobRun {
    id: number;
    script_name: string;
    status: 'pending' | 'running' | 'completed' | 'failed';
    user?: { id: number; name: string };
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


type JobStatusEvent = {
    jobRunId: number;
    status: 'pending' | 'running' | 'completed' | 'failed';
};

type JobCreatedEvent = {
    jobRun: JobRun;
};

export default function JobsIndex({ jobRuns, filters }: Props) {

    const [jobs, setJobs] = useState(jobRuns);
    const [rerunningJobId, setRerunningJobId] = useState<number | null>(null);

    // useEcho<JobStatusEvent>('private-job-runs-updated','script-job-run-status-updated', (event) => {
    //     console.log('Job status updated:', event);

    //     setJobs(prevJob => {
    //         return prevJob.map(job => {
    //             if (job.id === event.jobRunId) {
    //                 return { ...job, status: event.status };
    //             }
    //             return job;
    //         });
    //     });
    // });



    useEcho<JobStatusEvent>('job-runs-updated','ScriptJobRunStatusUpdatedEvent', (event) => {
        console.log('Job status updated:', event);

        setJobs(prevJob => {
            return {
            ...prevJob,
            data: prevJob.data.map(job => {
                    if (job.id === event.jobRunId) {
                        return { ...job, status: event.status };
                    }
                    return job;
                })
            }
        });
    });

    useEcho<JobCreatedEvent>('job-runs-updated','ScriptJobRunCreatedEvent', (event) => {
        console.log('New job created:', event);

        setJobs(prevJob => {
            return {
                ...prevJob,
                data: [event.jobRun, ...prevJob.data]
            };
        });
    });

    const handleFilterChange = (status: string) => {
        router.get('/jobs', { status: status === 'all' ? undefined : status }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleRerun = (jobId: number) => {
        setRerunningJobId(jobId);
        router.post(`/jobs/${jobId}/rerun`, {}, {
            // preserveScroll: true,
            onFinish: () => setRerunningJobId(null),
        });
    };

    const getStatusIcon = (status: string) => {
        const icons = {
            pending: <Clock className="h-5 w-5 text-yellow-500" />,
            running: <Loader2 className="h-5 w-5 text-blue-500 animate-spin" />,
            completed: <CheckCircle2 className="h-5 w-5 text-green-500" />,
            failed: <XCircle className="h-5 w-5 text-red-500" />,
        };
        return icons[status as keyof typeof icons] || icons.pending;
    };

    const getStatusBadge = (status: string) => {
        const config: Record<string, { variant: 'secondary' | 'default' | 'destructive' | 'outline', className?: string }> = {
            pending: { variant: 'secondary' },
            running: { variant: 'default' },
            completed: { variant: 'default', className: 'bg-green-600 text-white hover:bg-green-700' },
            failed: { variant: 'destructive' },
        };
        const statusConfig = config[status] || config.pending;
        return (
            <Badge variant={statusConfig.variant} className={statusConfig.className}>
                {status}
            </Badge>
        );
    };

    const formatTime = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
        return date.toLocaleDateString();
    };

    return (
        <AppLayout>
            <Head title="Job Runs" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold">Script Job Runs</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Monitor and manage script execution history
                        </p>
                    </div>

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
                        <CardTitle>Activity Feed</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {jobs.data.length === 0 ? (
                            <div className="text-center py-12 text-muted-foreground">
                                <Clock className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                <p>No job runs found</p>
                            </div>
                        ) : (
                            <div className="space-y-1">
                                {jobs.data.map((job, index) => (
                                    <div
                                        key={job.id}
                                        className="group relative flex gap-4 pb-6"
                                    >
                                        {/* Timeline line */}
                                        {index !== jobs.data.length - 1 && (
                                            <div className="absolute left-[10px] top-8 bottom-0 w-[2px] bg-border" />
                                        )}

                                        {/* Status icon */}
                                        <div className="relative z-10 flex-shrink-0 mt-1">
                                            {getStatusIcon(job.status)}
                                        </div>

                                        {/* Content */}
                                        <div className="flex-1 min-w-0 pt-0.5">
                                            <div className="flex items-start justify-between gap-4">
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center gap-2 flex-wrap">
                                                        <Link
                                                            href={`/jobs/${job.id}`}
                                                            className="font-semibold text-foreground hover:underline"
                                                        >
                                                            {job.script_name}
                                                        </Link>
                                                        {getStatusBadge(job.status)}
                                                        <span className="text-xs text-muted-foreground">
                                                            {formatTime(job.created_at)}
                                                        </span>
                                                    </div>

                                                    <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                                                        {job.user && (
                                                            <span className="flex items-center gap-1">
                                                                <User className="h-3 w-3" />
                                                                {job.user.name}
                                                            </span>
                                                        )}
                                                        {job.server && (
                                                            <span className="flex items-center gap-1">
                                                                <ServerIcon className="h-3 w-3" />
                                                                {job.server.name}
                                                            </span>
                                                        )}
                                                        {job.task && (
                                                            <span className="flex items-center gap-1">
                                                                <Clock className="h-3 w-3" />
                                                                Task: {job.task.title}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>

                                                {/* Actions */}
                                                <div className="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link href={`/jobs/${job.id}`}>
                                                            <Eye className="h-4 w-4 mr-1" />
                                                            View
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleRerun(job.id)}
                                                        disabled={
                                                            job.status === 'running' ||
                                                            job.status === 'pending' ||
                                                            rerunningJobId === job.id
                                                        }
                                                        title="Re-run this job"
                                                    >
                                                        <RefreshCw className={`h-4 w-4 ${rerunningJobId === job.id ? 'animate-spin' : ''}`} />
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}