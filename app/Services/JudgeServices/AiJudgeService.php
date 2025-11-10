<?php

namespace App\Services\JudgeServices;

use App\Contracts\JudgeInterface;
use App\Models\Task;
use App\Models\UserTaskAttempt;
use App\Services\AI\OpenAIService;
use Illuminate\Support\Facades\Log;

class AiJudgeService implements JudgeInterface
{
    protected OpenAIService $openAIService;

    public function __construct(?OpenAIService $openAIService = null)
    {
        $this->openAIService = $openAIService ?? new OpenAIService();
    }

    /**
     * Evaluate the user's answers using AI against the expected answers.
     *
     * @param Task $task
     * @param UserTaskAttempt $attempt
     * @param array $answers Format: ['question_id' => 'user_answer', ...]
     * @return array
     */
    public function evaluate(Task $task, UserTaskAttempt $attempt, array $answers): array
    {
        // Load AI judges with their questions and answers
        $task->load('aiJudges');
        $aiJudges = $task->aiJudges;

        if ($aiJudges->isEmpty()) {
            throw new \Exception('No AI judge questions found for this task');
        }

        $totalQuestions = $aiJudges->count();
        $correctCount = 0;
        $totalScore = 0;
        $details = [];

        // Evaluate each question using AI
        foreach ($aiJudges as $judge) {
            $questionId = $judge->id;
            $question = $judge->question;
            $expectedAnswer = $judge->answer;
            $prompt = $judge->prompt;
            $userAnswer = isset($answers[$questionId]) ? trim($answers[$questionId]) : '';

            try {
                // Use AI to evaluate the answer
                $aiEvaluation = $this->openAIService->evaluateAnswer(
                    $prompt,
                    $question,
                    $expectedAnswer,
                    $userAnswer
                );

                $isCorrect = $aiEvaluation['is_correct'];
                $score = $aiEvaluation['score']; // Score between 0 and 1
                $feedback = $aiEvaluation['feedback'];

                if ($isCorrect) {
                    $correctCount++;
                }

                $totalScore += $score;

                // Store details for this question
                $details[] = [
                    'question_id' => $questionId,
                    'question' => $question,
                    'user_answer' => $userAnswer,
                    'is_correct' => $isCorrect,
                    'score' => $score,
                    'feedback' => $feedback,
                ];

                Log::info('AI Judge Evaluation', [
                    'question_id' => $questionId,
                    'is_correct' => $isCorrect,
                    'score' => $score,
                ]);

            } catch (\Exception $e) {
                Log::error('AI Judge Evaluation Failed', [
                    'question_id' => $questionId,
                    'error' => $e->getMessage(),
                ]);

                // If AI evaluation fails, mark as incorrect with 0 score
                $details[] = [
                    'question_id' => $questionId,
                    'question' => $question,
                    'user_answer' => $userAnswer,
                    'is_correct' => false,
                    'score' => 0,
                    'feedback' => 'AI evaluation failed: ' . $e->getMessage(),
                ];
            }
        }

        // Calculate the score with attempt penalty
        $attemptNumber = $this->getAttemptNumber($task, $attempt);
        $maxPossibleScore = $this->calculateMaxScore($task->score, $attemptNumber);

        // Calculate actual score based on AI evaluations
        // Use the average score from AI (totalScore / totalQuestions) to determine the final score
        $averageScore = $totalScore / $totalQuestions;
        $actualScore = $maxPossibleScore * $averageScore;

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