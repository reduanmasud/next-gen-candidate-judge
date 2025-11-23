import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Clock,
    Trophy,
    CheckCircle2,
    XCircle,
    Calendar,
    FileText,
} from 'lucide-react';

interface Submission {
    id: number;
    score: string | null;
    notes: string | null;
    answers: any;
    submitted_at: string;
}

interface Attempt {
    id: number;
    status: string;
    display_status: string;
    score: number;
    started_at: string;
    completed_at: string | null;
    duration_seconds: number | null;
    submission_count: number;
    submissions: Submission[];
}

interface TaskAttemptsProps {
    user: {
        id: number;
        name: string;
    };
    task: {
        id: number;
        title: string;
        score: number;
    };
    attempts: Attempt[];
}

export default function TaskAttempts({ user, task, attempts }: TaskAttemptsProps) {
    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleString();
    };

    const formatDuration = (seconds: number | null) => {
        if (!seconds) return 'N/A';

        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        if (hours > 0) {
            return `${hours}h ${minutes}m ${secs}s`;
        } else if (minutes > 0) {
            return `${minutes}m ${secs}s`;
        } else {
            return `${secs}s`;
        }
    };

    const getStatusBadge = (displayStatus: string) => {
        const statusConfig: Record<string, { label: string; className: string }> = {
            completed: {
                label: 'Completed',
                className: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            },
            timeout: {
                label: 'Timeout',
                className: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
            },
            failed: {
                label: 'Failed',
                className: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            },
            terminated: {
                label: 'Terminated',
                className: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
            },
            running: {
                label: 'Running',
                className: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            },
            in_progress: {
                label: 'In Progress',
                className: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            },
            pending: {
                label: 'Pending',
                className: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
            },
        };

        const config = statusConfig[displayStatus] || {
            label: displayStatus.charAt(0).toUpperCase() + displayStatus.slice(1),
            className: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
        };

        return (
            <span className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${config.className}`}>
                {config.label}
            </span>
        );
    };

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Users',
            href: '/users',
        },
        {
            title: user.name,
            href: `/users/${user.id}`,
        },
        {
            title: 'Task Attempts',
            href: '#',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${user.name} - ${task.title} Attempts`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/users/${user.id}`}>
                                <ArrowLeft className="mr-1 h-4 w-4" />
                                Back to User
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-semibold">{task.title}</h1>
                            <p className="text-sm text-muted-foreground">
                                Attempts by {user.name}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Attempts List */}
                {attempts.length === 0 ? (
                    <Card>
                        <CardContent className="py-8">
                            <p className="text-center text-sm text-muted-foreground">
                                No attempts found for this task.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {attempts.map((attempt, attemptIndex) => (
                            <Card key={attempt.id}>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="text-lg">
                                            Attempt #{attempts.length - attemptIndex}
                                        </CardTitle>
                                        {getStatusBadge(attempt.display_status)}
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {/* Attempt Summary */}
                                    <div className="grid gap-4 md:grid-cols-4 mb-6">
                                        <div className="flex items-center gap-3">
                                            <Trophy className="h-5 w-5 text-muted-foreground" />
                                            <div>
                                                <p className="text-sm font-medium">Score</p>
                                                <p className="text-lg font-bold">
                                                    {attempt.score} / {task.score}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Clock className="h-5 w-5 text-muted-foreground" />
                                            <div>
                                                <p className="text-sm font-medium">Duration</p>
                                                <p className="text-sm text-muted-foreground">
                                                    {formatDuration(attempt.duration_seconds)}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Calendar className="h-5 w-5 text-muted-foreground" />
                                            <div>
                                                <p className="text-sm font-medium">Started</p>
                                                <p className="text-sm text-muted-foreground">
                                                    {formatDate(attempt.started_at)}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <FileText className="h-5 w-5 text-muted-foreground" />
                                            <div>
                                                <p className="text-sm font-medium">Submissions</p>
                                                <p className="text-sm text-muted-foreground">
                                                    {attempt.submission_count} total
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Submission Timeline */}
                                    {attempt.submissions.length > 0 && (
                                        <div>
                                            <h4 className="text-sm font-semibold mb-3">Submission Timeline</h4>
                                            <div className="space-y-3">
                                                {attempt.submissions.map((submission, subIndex) => (
                                                    <div
                                                        key={submission.id}
                                                        className="flex items-start gap-4 rounded-lg border p-3 bg-muted/30"
                                                    >
                                                        <div className="flex-shrink-0 w-8 h-8 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-sm font-medium">
                                                            {subIndex + 1}
                                                        </div>
                                                        <div className="flex-1">
                                                            <div className="flex items-center justify-between mb-1">
                                                                <p className="text-sm font-medium">
                                                                    Submission #{subIndex + 1}
                                                                </p>
                                                                <p className="text-xs text-muted-foreground">
                                                                    {formatDate(submission.submitted_at)}
                                                                </p>
                                                            </div>
                                                            {submission.score && (
                                                                <div className="flex items-center gap-2 mb-1">
                                                                    <Badge variant="outline">
                                                                        Score: {submission.score}
                                                                    </Badge>
                                                                </div>
                                                            )}
                                                            {submission.notes && (
                                                                <p className="text-sm text-muted-foreground mt-2">
                                                                    {submission.notes}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {attempt.completed_at && (
                                        <div className="mt-4 pt-4 border-t">
                                            <p className="text-sm text-muted-foreground">
                                                Completed: {formatDate(attempt.completed_at)}
                                            </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

