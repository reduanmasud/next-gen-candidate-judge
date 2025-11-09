import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState, useEffect } from 'react';
import { SharedData } from '@/types';
import { Clock, Key, Terminal } from 'lucide-react';

type JudgeType = 'none' | 'AiJudge' | 'QuizJudge' | 'TextJudge' | 'AutoJudge' | null;

interface TaskResource {
    id: number;
    title: string;
    description: string;
    score: number;
    judge_type: JudgeType;
    timer: number | null;
    allowssh: boolean;
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

interface AiJudgeQuestion {
    id: number;
    question: string;
    prompt: string;
}

interface QuizJudgeQuestion {
    id: number;
    question: string;
    options: {
        id: number;
        choice: string;
    }[];
}

interface TextJudgeQuestion {
    id: number;
    question: string;
}

interface UserTaskWorkspaceProps {
    task: TaskResource;
    attempt: AttemptResource;
    workspace: WorkspaceResource;
    judgeData: AiJudgeQuestion[] | QuizJudgeQuestion[] | TextJudgeQuestion[] | null;
}

const statusLabels: Record<string, string> = {
    pending: 'Preparing',
    running: 'In Progress',
    completed: 'Completed',
    failed: 'Failed',
    terminated: 'Terminated',
};

export default function UserTaskWorkspace({ task, attempt, workspace, judgeData }: UserTaskWorkspaceProps) {
    const { auth } = usePage<SharedData>().props;
    const [isRestarting, setIsRestarting] = useState(false);
    const [timeRemaining, setTimeRemaining] = useState<number | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Judge submission states
    const [aiAnswers, setAiAnswers] = useState<Record<number, string>>({});
    const [quizAnswers, setQuizAnswers] = useState<Record<number, number>>({});
    const [textAnswers, setTextAnswers] = useState<Record<number, string>>({});

    const isAdmin = useMemo(() => {
        return auth.user?.roles?.includes('admin') ?? false;
    }, [auth.user]);

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

    // Timer logic
    useEffect(() => {
        if (!task.timer || !attempt.started_at) {
            return;
        }

        const startTime = new Date(attempt.started_at).getTime();
        const timerDuration = task.timer * 60 * 1000; // Convert minutes to milliseconds

        const updateTimer = () => {
            const now = Date.now();
            const elapsed = now - startTime;
            const remaining = Math.max(0, timerDuration - elapsed);
            setTimeRemaining(Math.floor(remaining / 1000)); // Convert to seconds

            if (remaining <= 0) {
                // Timer expired
                return;
            }
        };

        updateTimer();
        const interval = setInterval(updateTimer, 1000);

        return () => clearInterval(interval);
    }, [task.timer, attempt.started_at]);

    const formatTime = (seconds: number) => {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        if (hours > 0) {
            return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
        return `${minutes}:${secs.toString().padStart(2, '0')}`;
    };

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

    const handleSubmitEvaluation = () => {
        if (isSubmitting) {
            return;
        }

        setIsSubmitting(true);

        let submissionData: any = {};

        if (task.judge_type === 'AiJudge') {
            submissionData = { answers: aiAnswers };
        } else if (task.judge_type === 'QuizJudge') {
            submissionData = { answers: quizAnswers };
        } else if (task.judge_type === 'TextJudge') {
            submissionData = { answers: textAnswers };
        }

        // TODO: Implement actual submission endpoint
        router.post(
            `/my-tasks/attempts/${attempt.id}/submit`,
            submissionData,
            {
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    return (
        <AppLayout>
            <Head title={`${task.title} · Task`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-auto p-4">
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
                        {/* Header */}
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

                        {/* Two-column layout */}
                        <div className="grid flex-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,350px)]">
                            {/* Main content area (left) */}
                            <div className="flex flex-col gap-4">
                                {/* Instructions Section */}
                                <Card className="flex flex-col">
                                    <CardHeader>
                                        <CardTitle>Instructions</CardTitle>
                                        <CardDescription>
                                            Follow the guidance below to complete the task.
                                        </CardDescription>
                                    </CardHeader>
                                    <Separator />
                                    <CardContent className="flex-1 pt-6">
                                        <div className="whitespace-pre-wrap rounded-md bg-muted/40 p-4 text-sm leading-relaxed">
                                            {task.description}
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Evaluation Section */}
                                {task.judge_type && task.judge_type !== 'none' && (
                                    <Card className="flex flex-col">
                                        <CardHeader>
                                            <CardTitle>Evaluation</CardTitle>
                                            <CardDescription>
                                                Submit your solution for evaluation
                                            </CardDescription>
                                        </CardHeader>
                                        <Separator />
                                        <CardContent className="pt-6">
                                            {renderEvaluationSection()}
                                        </CardContent>
                                    </Card>
                                )}
                            </div>

                            {/* Sidebar (right) */}
                            <div className="flex flex-col gap-4">
                                {/* Timer Card */}
                                {task.timer && timeRemaining !== null && (
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                <Clock className="h-5 w-5" />
                                                Time Remaining
                                            </CardTitle>
                                        </CardHeader>
                                        <Separator />
                                        <CardContent className="pt-6">
                                            <div className="flex items-center justify-center">
                                                <div className="relative flex h-32 w-32 items-center justify-center">
                                                    <svg className="h-full w-full -rotate-90 transform">
                                                        <circle
                                                            cx="64"
                                                            cy="64"
                                                            r="56"
                                                            stroke="currentColor"
                                                            strokeWidth="8"
                                                            fill="none"
                                                            className="text-muted"
                                                        />
                                                        <circle
                                                            cx="64"
                                                            cy="64"
                                                            r="56"
                                                            stroke="currentColor"
                                                            strokeWidth="8"
                                                            fill="none"
                                                            strokeDasharray={`${2 * Math.PI * 56}`}
                                                            strokeDashoffset={`${
                                                                2 * Math.PI * 56 * (1 - timeRemaining / (task.timer * 60))
                                                            }`}
                                                            className="text-primary transition-all duration-1000"
                                                            strokeLinecap="round"
                                                        />
                                                    </svg>
                                                    <div className="absolute inset-0 flex items-center justify-center">
                                                        <span className="text-2xl font-bold">
                                                            {formatTime(timeRemaining)}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                )}

                                {/* SSH Credentials Card */}
                                {task.allowssh && (workspace.username || workspace.password) && (
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                <Key className="h-5 w-5" />
                                                SSH Credentials
                                            </CardTitle>
                                        </CardHeader>
                                        <Separator />
                                        <CardContent className="pt-6">
                                            <dl className="space-y-3 text-sm">
                                                {workspace.username && (
                                                    <div>
                                                        <dt className="text-muted-foreground">Username</dt>
                                                        <dd className="mt-1 font-mono">{workspace.username}</dd>
                                                    </div>
                                                )}
                                                {workspace.password && (
                                                    <div>
                                                        <dt className="text-muted-foreground">Password</dt>
                                                        <dd className="mt-1 font-mono">{workspace.password}</dd>
                                                    </div>
                                                )}
                                                {workspace.path && (
                                                    <div>
                                                        <dt className="text-muted-foreground">Workspace Path</dt>
                                                        <dd className="mt-1 truncate font-mono text-xs">
                                                            {workspace.path}
                                                        </dd>
                                                    </div>
                                                )}
                                            </dl>
                                        </CardContent>
                                    </Card>
                                )}

                                {/* Attempt Details Card */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <Terminal className="h-5 w-5" />
                                            Attempt Details
                                        </CardTitle>
                                    </CardHeader>
                                    <Separator />
                                    <CardContent className="pt-6">
                                        <dl className="space-y-3 text-sm">
                                            <div>
                                                <dt className="text-muted-foreground">Started</dt>
                                                <dd className="mt-1">{startedAtText}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">Container</dt>
                                                <dd className="mt-1">{attempt.container_name ?? 'Pending'}</dd>
                                            </div>
                                            {attempt.container_port && (
                                                <div>
                                                    <dt className="text-muted-foreground">Port</dt>
                                                    <dd className="mt-1">{attempt.container_port}</dd>
                                                </div>
                                            )}
                                        </dl>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>

                        {/* Notes Section - Only for Admin */}
                        {isAdmin && (
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
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );

    function renderEvaluationSection() {
        if (!task.judge_type) {
            return null;
        }

        // AutoJudge doesn't need judgeData
        if (task.judge_type === 'AutoJudge') {
            return (
                <div className="space-y-4">
                    <Button onClick={handleSubmitEvaluation} disabled={isSubmitting} className="w-full">
                        {isSubmitting ? (
                            <>
                                <Spinner className="mr-2 h-4 w-4" />
                                Submitting...
                            </>
                        ) : (
                            'I completed the task and Submit'
                        )}
                    </Button>
                </div>
            );
        }

        // For other judge types, judgeData is required
        if (!judgeData) {
            return null;
        }

        if (task.judge_type === 'AiJudge') {
            const aiQuestions = judgeData as AiJudgeQuestion[];
            return (
                <div className="space-y-6">
                    {aiQuestions.map((question) => (
                        <div key={question.id} className="space-y-2">
                            <Label htmlFor={`ai-${question.id}`} className="text-base font-medium">
                                {question.question}
                            </Label>
                            <Textarea
                                id={`ai-${question.id}`}
                                value={aiAnswers[question.id] || ''}
                                onChange={(e) =>
                                    setAiAnswers((prev) => ({ ...prev, [question.id]: e.target.value }))
                                }
                                placeholder="Enter your answer..."
                                rows={4}
                                className="resize-none"
                            />
                        </div>
                    ))}
                    <Button onClick={handleSubmitEvaluation} disabled={isSubmitting} className="w-full">
                        {isSubmitting ? (
                            <>
                                <Spinner className="mr-2 h-4 w-4" />
                                Submitting...
                            </>
                        ) : (
                            'Submit for AI Evaluation'
                        )}
                    </Button>
                </div>
            );
        }

        if (task.judge_type === 'QuizJudge') {
            const quizQuestions = judgeData as QuizJudgeQuestion[];
            return (
                <div className="space-y-6">
                    {quizQuestions.map((question) => (
                        <div key={question.id} className="space-y-3">
                            <Label className="text-base font-medium">{question.question}</Label>
                            <RadioGroup
                                value={quizAnswers[question.id]?.toString() || ''}
                                onValueChange={(value: string) =>
                                    setQuizAnswers((prev) => ({ ...prev, [question.id]: parseInt(value) }))
                                }
                            >
                                {question.options.map((option) => (
                                    <div key={option.id} className="flex items-center space-x-2">
                                        <RadioGroupItem value={option.id.toString()} id={`quiz-${option.id}`} />
                                        <Label htmlFor={`quiz-${option.id}`} className="font-normal cursor-pointer">
                                            {option.choice}
                                        </Label>
                                    </div>
                                ))}
                            </RadioGroup>
                        </div>
                    ))}
                    <Button onClick={handleSubmitEvaluation} disabled={isSubmitting} className="w-full">
                        {isSubmitting ? (
                            <>
                                <Spinner className="mr-2 h-4 w-4" />
                                Submitting...
                            </>
                        ) : (
                            'Submit Quiz'
                        )}
                    </Button>
                </div>
            );
        }

        if (task.judge_type === 'TextJudge') {
            const textQuestions = judgeData as TextJudgeQuestion[];
            return (
                <div className="space-y-6">
                    {textQuestions.map((question) => (
                        <div key={question.id} className="space-y-2">
                            <Label htmlFor={`text-${question.id}`} className="text-base font-medium">
                                {question.question}
                            </Label>
                            <Textarea
                                id={`text-${question.id}`}
                                value={textAnswers[question.id] || ''}
                                onChange={(e) =>
                                    setTextAnswers((prev) => ({ ...prev, [question.id]: e.target.value }))
                                }
                                placeholder="Enter your answer..."
                                rows={2}
                                className="resize-none"
                            />
                        </div>
                    ))}
                    <Button onClick={handleSubmitEvaluation} disabled={isSubmitting} className="w-full">
                        {isSubmitting ? (
                            <>
                                <Spinner className="mr-2 h-4 w-4" />
                                Submitting...
                            </>
                        ) : (
                            'Submit Answers'
                        )}
                    </Button>
                </div>
            );
        }

        return null;
    }
}
