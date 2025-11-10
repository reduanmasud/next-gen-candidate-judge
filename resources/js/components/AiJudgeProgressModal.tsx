import { useEffect, useState } from 'react';
import { CheckCircle2, XCircle, Loader2, MessageSquare } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';

interface QuestionDetail {
    question_id: number;
    question: string;
    user_answer: string;
    is_correct: boolean;
    score: number;
    feedback: string;
}

interface SubmissionResult {
    score: number;
    max_score: number;
    correct_count: number;
    total_count: number;
    details: QuestionDetail[];
    should_lock: boolean;
    next_attempt_max_score: number;
    attempt_number: number;
}

interface AiJudgeProgressModalProps {
    open: boolean;
    result: SubmissionResult | null;
    taskScore: number;
}

type QuestionStatus = 'pending' | 'evaluating' | 'completed';

export default function AiJudgeProgressModal({ open, result, taskScore }: AiJudgeProgressModalProps) {
    const [currentQuestionIndex, setCurrentQuestionIndex] = useState<number>(-1);
    const [questionStatuses, setQuestionStatuses] = useState<Map<number, QuestionStatus>>(new Map());
    const [showFinalScore, setShowFinalScore] = useState(false);

    useEffect(() => {
        if (!open || !result) {
            // Reset state when modal closes
            setCurrentQuestionIndex(-1);
            setQuestionStatuses(new Map());
            setShowFinalScore(false);
            return;
        }

        // Start the evaluation sequence
        let currentIndex = 0;
        const totalQuestions = result.details.length;

        const evaluateNextQuestion = () => {
            if (currentIndex >= totalQuestions) {
                // All questions evaluated, show final score after a brief delay
                setTimeout(() => {
                    setShowFinalScore(true);
                }, 500);
                return;
            }

            // Set current question to evaluating
            setCurrentQuestionIndex(currentIndex);
            setQuestionStatuses(prev => {
                const newMap = new Map(prev);
                newMap.set(currentIndex, 'evaluating');
                return newMap;
            });

            // After 800ms (slightly longer for AI evaluation feel), mark as completed and move to next
            setTimeout(() => {
                setQuestionStatuses(prev => {
                    const newMap = new Map(prev);
                    newMap.set(currentIndex, 'completed');
                    return newMap;
                });

                currentIndex++;
                evaluateNextQuestion();
            }, 800);
        };

        // Start evaluation
        evaluateNextQuestion();
    }, [open, result]);

    const handleReturnToTasks = () => {
        router.visit('/my-tasks');
    };

    if (!open || !result) return null;

    const percentage = (result.score / taskScore) * 100;

    return (
        <>
            {/* White overlay backdrop */}
            <div className="fixed inset-0 z-50 bg-white/95 dark:bg-gray-950/95 flex items-center justify-center p-4">
                <div className="w-full max-w-2xl flex flex-col gap-6 rounded-lg border bg-card px-8 py-8 shadow-lg max-h-[90vh] overflow-y-auto">
                    {!showFinalScore ? (
                        <>
                            {/* Header */}
                            <div className="text-center">
                                <p className="text-xl font-bold mb-2">
                                    AI is Evaluating Your Answers
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Please wait while our AI analyzes your responses...
                                </p>
                            </div>

                            {/* Progress List */}
                            <div className="space-y-4">
                                {result.details.map((detail, index) => {
                                    const status = questionStatuses.get(index) || 'pending';
                                    const isVisible = index <= currentQuestionIndex;

                                    if (!isVisible) return null;

                                    return (
                                        <div
                                            key={detail.question_id}
                                            className={`transition-all duration-500 ease-out ${
                                                status === 'evaluating' ? 'animate-in fade-in slide-in-from-bottom-2' : 'opacity-100 translate-y-0'
                                            }`}
                                        >
                                            <div className="flex items-start gap-3">
                                                <div className="relative h-5 w-5 flex-shrink-0 mt-0.5">
                                                    {status === 'evaluating' ? (
                                                        <Loader2 className="absolute inset-0 h-5 w-5 animate-spin text-primary transition-opacity duration-300" />
                                                    ) : detail.is_correct ? (
                                                        <CheckCircle2 className="absolute inset-0 h-5 w-5 text-green-500 animate-in fade-in duration-300" />
                                                    ) : (
                                                        <XCircle className="absolute inset-0 h-5 w-5 text-amber-500 animate-in fade-in duration-300" />
                                                    )}
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <span
                                                        className={`text-sm block transition-colors duration-300 ${
                                                            status === 'evaluating' ? 'font-medium text-foreground' : 'text-muted-foreground'
                                                        }`}
                                                    >
                                                        Question {index + 1}: {detail.question.length > 60 ? detail.question.substring(0, 60) + '...' : detail.question}
                                                    </span>
                                                    {status === 'completed' && (
                                                        <div className="mt-2 text-xs text-muted-foreground animate-in fade-in duration-300">
                                                            Score: {(detail.score * 100).toFixed(0)}%
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </>
                    ) : (
                        <>
                            {/* Final Score Display */}
                            <div className="text-center">
                                <div className="mb-4">
                                    {percentage >= 70 ? (
                                        <CheckCircle2 className="h-16 w-16 text-green-500 mx-auto animate-in zoom-in duration-500" />
                                    ) : (
                                        <XCircle className="h-16 w-16 text-amber-500 mx-auto animate-in zoom-in duration-500" />
                                    )}
                                </div>
                                <p className="text-2xl font-bold mb-2">
                                    AI Evaluation Complete!
                                </p>
                                <p className="text-sm text-muted-foreground mb-6">
                                    Here's your detailed feedback
                                </p>
                            </div>

                            {/* Score Summary */}
                            <div className="bg-gray-50 dark:bg-gray-800 p-6 rounded-lg space-y-4">
                                <div className="text-center">
                                    <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                        Your Score
                                    </p>
                                    <p className="text-4xl font-bold text-primary mb-1">
                                        {result.score.toFixed(2)}
                                    </p>
                                    <p className="text-lg text-gray-600 dark:text-gray-400">
                                        out of {taskScore} points
                                    </p>
                                    <p className="text-sm text-gray-500 dark:text-gray-500 mt-2">
                                        ({percentage.toFixed(1)}%)
                                    </p>
                                </div>

                                <div className="pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <div className="flex justify-center items-center gap-2">
                                        <CheckCircle2 className="h-5 w-5 text-green-500" />
                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                            <span className="font-semibold text-foreground">{result.correct_count}</span> out of{' '}
                                            <span className="font-semibold text-foreground">{result.total_count}</span> questions passed
                                        </p>
                                    </div>
                                </div>

                                {result.attempt_number > 1 && (
                                    <div className="pt-4 border-t border-gray-200 dark:border-gray-700">
                                        <p className="text-xs text-center text-amber-600 dark:text-amber-400">
                                            Attempt #{result.attempt_number} - {((result.attempt_number - 1) * 10)}% penalty applied
                                        </p>
                                    </div>
                                )}
                            </div>

                            {/* AI Feedback Section */}
                            <div className="space-y-4">
                                <div className="flex items-center gap-2 text-sm font-semibold">
                                    <MessageSquare className="h-4 w-4" />
                                    <span>AI Feedback</span>
                                </div>
                                
                                <div className="space-y-4 max-h-96 overflow-y-auto">
                                    {result.details.map((detail, index) => (
                                        <div 
                                            key={detail.question_id}
                                            className="border rounded-lg p-4 space-y-3 bg-white dark:bg-gray-900"
                                        >
                                            <div className="flex items-start gap-2">
                                                <div className="flex-shrink-0 mt-0.5">
                                                    {detail.is_correct ? (
                                                        <CheckCircle2 className="h-4 w-4 text-green-500" />
                                                    ) : (
                                                        <XCircle className="h-4 w-4 text-amber-500" />
                                                    )}
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-sm font-medium mb-1">
                                                        Question {index + 1}: {detail.question}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground mb-2">
                                                        Score: {(detail.score * 100).toFixed(0)}%
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <div className="pl-6">
                                                <p className="text-xs font-medium text-muted-foreground mb-1">
                                                    Your Answer:
                                                </p>
                                                <p className="text-sm bg-gray-50 dark:bg-gray-800 p-2 rounded border">
                                                    {detail.user_answer || '(No answer provided)'}
                                                </p>
                                            </div>

                                            <div className="pl-6">
                                                <p className="text-xs font-medium text-muted-foreground mb-1">
                                                    AI Feedback:
                                                </p>
                                                <p className="text-sm text-gray-700 dark:text-gray-300 bg-blue-50 dark:bg-blue-950/30 p-3 rounded border border-blue-200 dark:border-blue-900">
                                                    {detail.feedback}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Return Button */}
                            <Button 
                                onClick={handleReturnToTasks} 
                                className="w-full"
                                size="lg"
                            >
                                Return to Tasks
                            </Button>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

