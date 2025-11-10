<?php

namespace App\Services\JudgeServices;

use App\Contracts\JudgeInterface;
use App\Models\Task;
use App\Models\UserTaskAttempt;

class QuizJudgeService implements JudgeInterface
{
    /**
     * Evaluate the user's quiz answers against the correct answers.
     *
     * @param Task $task
     * @param UserTaskAttempt $attempt
     * @param array $answers Format: ['question_id' => 'answer_id', ...]
     * @return array
     */
    public function evaluate(Task $task, UserTaskAttempt $attempt, array $answers): array
    {
        // Load quiz judges with their questions and answers
        $task->load('quizJudges.quizQuestionAnswers');
        $quizJudges = $task->quizJudges;

        if ($quizJudges->isEmpty()) {
            throw new \Exception('No quiz judge questions found for this task');
        }

        $totalQuestions = $quizJudges->count();
        $correctCount = 0;
        $details = [];

        // Evaluate each question
        foreach ($quizJudges as $quizJudge) {
            $questionId = $quizJudge->id;
            $correctAnswer = $quizJudge->quizQuestionAnswers->where('is_correct', true)->first();
            $userAnswer = isset($answers[$questionId]) ? (int) $answers[$questionId] : null;

            // Check if user's answer matches the correct answer
            $isCorrect = $correctAnswer && $userAnswer === $correctAnswer->id;

            if ($isCorrect) {
                $correctCount++;
            }

            // Do NOT include correct_answer in the details sent to frontend
            // Users should only see if they were correct or not, not the actual answer
            $details[] = [
                'question_id' => $questionId,
                'question' => json_decode($quizJudge->questions, true),
                'user_answer' => $userAnswer,
                'is_correct' => $isCorrect,
            ];
        }

        // Calculate the score with attempt penalty
        $attemptNumber = $this->getAttemptNumber($task, $attempt);
        $maxPossibleScore = $this->calculateMaxScore($task->score, $attemptNumber);

        // Calculate actual score based on correct answers
        $scorePerQuestion = $maxPossibleScore / $totalQuestions;
        $actualScore = $correctCount * $scorePerQuestion;

        // Calculate what the max score would be for the NEXT attempt
        $nextAttemptNumber = $attemptNumber + 1;
        $nextAttemptMaxScore = $this->calculateMaxScore($task->score, $nextAttemptNumber);

        // Task should be locked if the next attempt's max possible score is below 20% threshold
        $lockingThreshold = $task->score * 0.2;
        $shouldLock = $nextAttemptMaxScore < $lockingThreshold;

        return [
            'score' => round($actualScore, 2),
            'max_score' => round($maxPossibleScore, 2),
            'correct_count' => $correctCount,
            'total_count' => $totalQuestions,
            'details' => $details,
            'should_lock' => $shouldLock,
            'next_attempt_max_score' => round($nextAttemptMaxScore, 2),
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


