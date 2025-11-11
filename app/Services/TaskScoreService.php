<?php

namespace App\Services;


class TaskScoreService
{

    public function calculatePenaltyAdjustedScore(int $baseScore, int $attemptCount): float
    {
        // First viewing (0 attempts): show full points
        if ($attemptCount === 0) {
            return $baseScore;
        }

        // After each incorrect attempt, reduce by 10%
        $penaltyPercentage = ($attemptCount - 1) * 10;
        $maxPercentage = max(0, 100 - $penaltyPercentage);

        return ($baseScore * $maxPercentage) / 100;
    }


    public function calculateMaxScore(int $baseScore, int $attemptNumber): int
    {
        return $baseScore * max(1, (3 - $attemptNumber));
    }



    public function isCompletedSuccessfully($task, $latestAttempt, $attemptNumber): bool
    {
        if (!$latestAttempt) {
            return false;
        }

        $maxScore = $this->calculateMaxScore($task->score, $attemptNumber);

        return $latestAttempt->score >= $maxScore;
    }

}