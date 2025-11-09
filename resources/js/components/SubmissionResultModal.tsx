import React from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { CheckCircle2, XCircle, Lock } from 'lucide-react';

interface QuestionDetail {
    question_id: number;
    question: string;
    user_answer: string;
    correct_answer: string;
    is_correct: boolean;
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

interface SubmissionResultModalProps {
    open: boolean;
    onClose: () => void;
    result: SubmissionResult | null;
    locked: boolean;
    taskScore: number;
}

export default function SubmissionResultModal({
    open,
    onClose,
    result,
    locked,
    taskScore,
}: SubmissionResultModalProps) {
    if (!result) return null;

    const percentage = (result.score / taskScore) * 100;
    const penaltyPercentage = (result.attempt_number - 1) * 10;
    const lockingThreshold = taskScore * 0.2;

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        {!locked ? (
                            <>
                                <CheckCircle2 className="h-6 w-6 text-green-600" />
                                Submission Successful
                            </>
                        ) : (
                            <>
                                <XCircle className="h-6 w-6 text-red-600" />
                                Submission Complete
                            </>
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        Your answers have been evaluated
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Score Summary */}
                    <div className="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    Score
                                </p>
                                <p className="text-2xl font-bold">
                                    {result.score.toFixed(2)} / {taskScore}
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    ({percentage.toFixed(1)}%)
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    Correct Answers
                                </p>
                                <p className="text-2xl font-bold">
                                    {result.correct_count} / {result.total_count}
                                </p>
                            </div>
                        </div>

                        {penaltyPercentage > 0 && (
                            <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <p className="text-sm text-amber-600 dark:text-amber-400">
                                    Attempt #{result.attempt_number} - {penaltyPercentage}% penalty applied
                                </p>
                                <p className="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                    Max possible score: {result.max_score.toFixed(2)} points
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Lock Warning */}
                    {locked && (
                        <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4 rounded-lg">
                            <div className="flex items-start gap-3">
                                <Lock className="h-5 w-5 text-red-600 dark:text-red-400 mt-0.5" />
                                <div>
                                    <p className="font-semibold text-red-900 dark:text-red-100">
                                        Task Locked
                                    </p>
                                    <p className="text-sm text-red-800 dark:text-red-200 mt-1">
                                        The next attempt's maximum possible score would be{' '}
                                        <span className="font-semibold">{result.next_attempt_max_score.toFixed(2)} points</span>,
                                        which is below the 20% threshold ({lockingThreshold.toFixed(2)} points required).
                                        This task has been locked and you can no longer attempt it.
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Question Details */}
                    <div>
                        <h3 className="font-semibold mb-3">Answer Details</h3>
                        <div className="space-y-3">
                            {result.details.map((detail, index) => (
                                <div
                                    key={detail.question_id}
                                    className={`p-4 rounded-lg border ${
                                        detail.is_correct
                                            ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800'
                                            : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'
                                    }`}
                                >
                                    <div className="flex items-start gap-3">
                                        {detail.is_correct ? (
                                            <CheckCircle2 className="h-5 w-5 text-green-600 dark:text-green-400 mt-0.5 flex-shrink-0" />
                                        ) : (
                                            <XCircle className="h-5 w-5 text-red-600 dark:text-red-400 mt-0.5 flex-shrink-0" />
                                        )}
                                        <div className="flex-1 min-w-0">
                                            <p className="font-medium mb-2">
                                                Question {index + 1}
                                            </p>
                                            <p className="text-sm text-gray-700 dark:text-gray-300 mb-2">
                                                {detail.question}
                                            </p>
                                            <div className="space-y-1">
                                                <p className="text-sm">
                                                    <span className="font-medium">Your answer:</span>{' '}
                                                    <span className={detail.is_correct ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'}>
                                                        {detail.user_answer || '(no answer)'}
                                                    </span>
                                                </p>
                                                {!detail.is_correct && (
                                                    <p className="text-sm">
                                                        <span className="font-medium">Correct answer:</span>{' '}
                                                        <span className="text-green-700 dark:text-green-300">
                                                            {detail.correct_answer}
                                                        </span>
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex justify-end gap-3 pt-4 border-t">
                        <Button onClick={onClose}>
                            Close
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

