<?php

namespace App\Services\JudgeServices;

use App\Contracts\JudgeInterface;
use App\Interfaces\SolutionCheckerInterface;
use App\Models\Task;
use App\Models\UserTaskAttempt;

class QuizJudgeService implements JudgeInterface
{

    public function evaluate(Task $task, UserTaskAttempt $attempt, array $answers): array
    {
   
            $task->load('quizJudges.quizQuestionAnswers');
            $quizJudges = $task->quizJudges;

            if ($quizJudges->isEmpty()) {
                throw new \Exception('No quiz judge questions found for this task');
            }

            $totalQuestions = $quizJudges->count();
            $correctAnswers = 0;
            $correctUserAnswers = 0;
            $details = [];

            foreach ($quizJudges as $quizJudge) {
                $questionId = $quizJudge->id;
                $correctAnswer = $quizJudge->quizQuestionAnswers->where('is_correct', true)->first();
                $userAnswer = isset($answers[$questionId]) ? (int) $answers[$questionId] : null;

                if ($correctAnswer && $userAnswer === $correctAnswer->id) {
                    $correctUserAnswers++;
                }

                $details[] = [
                    'question_id' => $questionId,
                    'question' => json_decode($quizJudge->questions, true),
                    'user_answer' => $userAnswer,
                    // 'correct_answer' => $correctAnswer ? $correctAnswer->choice : null,
                    'is_correct' => $correctAnswer && $userAnswer === $correctAnswer->id,
                ];
            }   

            return [
                'score' => ($correctUserAnswers / $totalQuestions) * $attempt->task->score,
                'max_score' => $attempt->task->score,
                'correct_count' => $correctUserAnswers,
                'total_count' => $totalQuestions,
                'details' => $details,
                'should_lock' => $this->shouldLock($task, $attempt, 0.2),
                'next_attempt_max_score' => $attempt->task->score,
                'attempt_number' => $attempt->attempt_number,
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

    /**
     * Determine if the task should be locked based on the attempt number.
     *
     * @param Task $task
     * @param UserTaskAttempt $attempt
     * @return bool
     */
    public function shouldLock(Task $task, UserTaskAttempt $attempt, Int $threshold): bool
    {
        $attemptNumber = $this->getAttemptNumber($task, $attempt);
        $nextAttemptNumber = $attemptNumber + 1;
        $nextAttemptMaxScore = $this->calculateMaxScore($task->score, $nextAttemptNumber);
        $lockingThreshold = $task->score * $threshold;

        return $nextAttemptMaxScore < $lockingThreshold;
    }   
}


