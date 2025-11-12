import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    User as UserIcon,
    Mail,
    Phone,
    Calendar,
    Trophy,
    Target,
    Lock,
    CheckCircle2,
    XCircle,
    Edit,
    ArrowLeft,
} from 'lucide-react';

interface TaskAttempt {
    id: number;
    task_id: number;
    status: string;
    score: number;
    started_at: string;
    completed_at: string | null;
    task: {
        id: number;
        title: string;
        score: number;
    };
}

interface TaskLock {
    id: number;
    task_id: number;
    reason: string | null;
    status: string | null;
    created_at: string;
    task: {
        id: number;
        title: string;
    };
}

interface UserShowProps {
    user: {
        id: number;
        name: string;
        email: string;
        phone: string;
        email_verified_at: string | null;
        created_at: string;
        is_admin: boolean;
        role?: string;
        attempts: TaskAttempt[];
        task_locks: TaskLock[];
    };
    stats: {
        total_score: number;
        total_attempts: number;
        correct_answers: number;
        total_answers: number;
        locked_tasks_count: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: '/users',
    },
    {
        title: 'User Details',
        href: '#',
    },
];

export default function UserShow({ user, stats }: UserShowProps) {
    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleString();
    };

    const getStatusBadge = (status: string) => {
        const statusConfig: Record<string, { label: string; className: string }> = {
            completed: {
                label: 'Completed',
                className: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            },
            in_progress: {
                label: 'In Progress',
                className: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            },
            failed: {
                label: 'Failed',
                className: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            },
        };

        const config = statusConfig[status] || {
            label: status,
            className: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
        };

        return (
            <span className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${config.className}`}>
                {config.label}
            </span>
        );
    };

    const correctPercentage = stats.total_answers > 0
        ? Math.round((stats.correct_answers / stats.total_answers) * 100)
        : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`User: ${user.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/users">
                                <ArrowLeft className="mr-1 h-4 w-4" />
                                Back
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-semibold">{user.name}</h1>
                            <p className="text-sm text-muted-foreground">
                                User details and statistics
                            </p>
                        </div>
                    </div>
                    <Button asChild>
                        <Link href={`/users/${user.id}/edit`}>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit User
                        </Link>
                    </Button>
                </div>

                {/* User Information Card */}
                <Card>
                    <CardHeader>
                        <CardTitle>User Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="flex items-center gap-3">
                                <UserIcon className="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">Name</p>
                                    <p className="text-sm text-muted-foreground">{user.name}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Mail className="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">Email</p>
                                    <p className="text-sm text-muted-foreground">{user.email}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Phone className="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">Phone</p>
                                    <p className="text-sm text-muted-foreground">{user.phone}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Calendar className="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">Joined</p>
                                    <p className="text-sm text-muted-foreground">
                                        {formatDate(user.created_at)}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <div className="h-5 w-5 flex items-center justify-center">
                                    <Badge variant={user.role === 'admin' ? 'default' : 'secondary'}>
                                        {user.role
                                            ? user.role.charAt(0).toUpperCase() + user.role.slice(1)
                                            : 'User'}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Statistics Cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Score</CardTitle>
                            <Trophy className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_score}</div>
                            <p className="text-xs text-muted-foreground">
                                Points earned
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Attempts</CardTitle>
                            <Target className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_attempts}</div>
                            <p className="text-xs text-muted-foreground">
                                Tasks attempted
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Success Rate</CardTitle>
                            <CheckCircle2 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{correctPercentage}%</div>
                            <p className="text-xs text-muted-foreground">
                                {stats.correct_answers} / {stats.total_answers} correct
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Locked Tasks</CardTitle>
                            <Lock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.locked_tasks_count}</div>
                            <p className="text-xs text-muted-foreground">
                                Tasks restricted
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Attempt Timeline */}
                <Card>
                    <CardHeader>
                        <CardTitle>Attempt Timeline</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {user.attempts.length === 0 ? (
                            <p className="text-center text-sm text-muted-foreground py-8">
                                No attempts yet.
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {user.attempts.map((attempt) => (
                                    <div
                                        key={attempt.id}
                                        className="flex items-start gap-4 rounded-lg border p-4"
                                    >
                                        <div className="flex-1">
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <Link
                                                        href={`/tasks/${attempt.task_id}`}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {attempt.task.title}
                                                    </Link>
                                                    <p className="text-sm text-muted-foreground">
                                                        Started: {formatDate(attempt.started_at)}
                                                    </p>
                                                    {attempt.completed_at && (
                                                        <p className="text-sm text-muted-foreground">
                                                            Completed: {formatDate(attempt.completed_at)}
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    <div className="text-right">
                                                        <p className="text-lg font-bold">
                                                            {attempt.score} / {attempt.task.score}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            Score
                                                        </p>
                                                    </div>
                                                    {getStatusBadge(attempt.status)}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Locked by Penalty */}
                {user.task_locks.filter((lock) => lock.status === 'penalty').length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <XCircle className="h-5 w-5 text-destructive" />
                                Locked by Penalty
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {user.task_locks
                                    .filter((lock) => lock.status === 'penalty')
                                    .map((lock) => (
                                        <div
                                            key={lock.id}
                                            className="flex items-center justify-between rounded-lg border border-destructive/50 bg-destructive/5 p-3"
                                        >
                                            <div>
                                                <Link
                                                    href={`/tasks/${lock.task_id}`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {lock.task.title}
                                                </Link>
                                                {lock.reason && (
                                                    <p className="text-sm text-muted-foreground">
                                                        {lock.reason}
                                                    </p>
                                                )}
                                                <p className="text-xs text-muted-foreground">
                                                    Locked: {formatDate(lock.created_at)}
                                                </p>
                                            </div>
                                            <Badge variant="destructive">
                                                <Lock className="mr-1 h-3 w-3" />
                                                Locked
                                            </Badge>
                                        </div>
                                    ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Locked by Completion */}
                {user.task_locks.filter((lock) => lock.status === 'completed').length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CheckCircle2 className="h-5 w-5 text-green-600" />
                                Locked by Completion
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {user.task_locks
                                    .filter((lock) => lock.status === 'completed')
                                    .map((lock) => (
                                        <div
                                            key={lock.id}
                                            className="flex items-center justify-between rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-900/50 dark:bg-green-950/20"
                                        >
                                            <div>
                                                <Link
                                                    href={`/tasks/${lock.task_id}`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {lock.task.title}
                                                </Link>
                                                {lock.reason && (
                                                    <p className="text-sm text-muted-foreground">
                                                        {lock.reason}
                                                    </p>
                                                )}
                                                <p className="text-xs text-muted-foreground">
                                                    Completed: {formatDate(lock.created_at)}
                                                </p>
                                            </div>
                                            <Badge className="bg-green-600 hover:bg-green-700">
                                                <CheckCircle2 className="mr-1 h-3 w-3" />
                                                Completed
                                            </Badge>
                                        </div>
                                    ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}

