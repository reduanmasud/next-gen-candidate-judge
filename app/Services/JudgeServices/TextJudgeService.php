<?php

namespace App\Services\JudgeServices;

use App\Contracts\JudgeInterface;
use App\Models\Task;
use App\Models\UserTaskAttempt;

class TextJudgeService implements JudgeInterface
{
    /**
     * Evaluate the user's text answers against the correct answers.
     *
     * @param Task $task
     * @param UserTaskAttempt $attempt
     * @param array $answers Format: ['question_id' => 'user_answer', ...]
     * @return array
     */
    public function evaluate(Task $task, UserTaskAttempt $attempt, array $answers): array
    {
        // Load text judges with their questions and answers
        $task->load('textJudges');
        $textJudges = $task->textJudges;

        if ($textJudges->isEmpty()) {
            throw new \Exception('No text judge questions found for this task');
        }

        $totalQuestions = $textJudges->count();
        $correctCount = 0;
        $details = [];

        // Evaluate each question
        foreach ($textJudges as $judge) {
            $questionId = $judge->id;
            $correctAnswer = trim($judge->answers);
            $userAnswer = isset($answers[$questionId]) ? trim($answers[$questionId]) : '';

            // Exact string matching (case-sensitive)
            $isCorrect = $userAnswer === $correctAnswer;

            if ($isCorrect) {
                $correctCount++;
            }

            $details[] = [
                'question_id' => $questionId,
                'question' => $judge->questions,
                'user_answer' => $userAnswer,
                'correct_answer' => $correctAnswer,
                'is_correct' => $isCorrect,
            ];
        }

        // Calculate the score with attempt penalty
        $attemptNumber = $this->getAttemptNumber($task, $attempt);
        $maxPossibleScore = $this->calculateMaxScore($task->score, $attemptNumber);

        // Calculate actual score based on correct answers
        $scorePerQuestion = $maxPossibleScore / $totalQuestions;
        $actualScore = $correctCount * $scorePerQuestion;

        // Check if passed (>= 80% of task's total score)
        $passingThreshold = $task->score * 0.8;
        $passed = $actualScore >= $passingThreshold;

        return [
            'score' => round($actualScore, 2),
            'max_score' => round($maxPossibleScore, 2),
            'correct_count' => $correctCount,
            'total_count' => $totalQuestions,
            'details' => $details,
            'passed' => $passed,
            'attempt_number' => $attemptNumber,
        ];
    }

    /**
     * Get the attempt number for this user and task.
     *
     * @param Task $task
     * @param UserTaskAttempt $attempt
     * @return int
     */
    protected function getAttemptNumber(Task $task, UserTaskAttempt $attempt): int
    {
        // Count all previous attempts by this user for this task (including current)
        return UserTaskAttempt::where('user_id', $attempt->user_id)
            ->where('task_id', $task->id)
            ->where('id', '<=', $attempt->id)
            ->count();
    }

    /**
     * Calculate maximum possible score based on attempt number.
     * Each attempt reduces the max score by 10%.
     *
     * @param int $taskScore
     * @param int $attemptNumber
     * @return float
     */
    protected function calculateMaxScore(int $taskScore, int $attemptNumber): float
    {
        // Attempt 1: 100%, Attempt 2: 90%, Attempt 3: 80%, etc.
        $penaltyPercentage = ($attemptNumber - 1) * 10;
        $maxPercentage = max(0, 100 - $penaltyPercentage);

        return ($taskScore * $maxPercentage) / 100;
    }
}