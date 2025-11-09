<?php

namespace App\Contracts;

use App\Models\Task;
use App\Models\UserTaskAttempt;

interface JudgeInterface
{
    /**
     * Evaluate the user's submission for a task attempt.
     *
     * @param Task $task The task being evaluated
     * @param UserTaskAttempt $attempt The user's attempt
     * @param array $answers The user's submitted answers
     * @return array Returns evaluation result with keys:
     *               - 'score': float - The calculated score
     *               - 'max_score': float - The maximum possible score for this attempt
     *               - 'correct_count': int - Number of correct answers
     *               - 'total_count': int - Total number of questions
     *               - 'details': array - Detailed results per question
     *               - 'should_lock': bool - Whether the task should be locked (next attempt's max score < 20% threshold)
     *               - 'next_attempt_max_score': float - Maximum possible score for the next attempt
     *               - 'attempt_number': int - Current attempt number
     */
    public function evaluate(Task $task, UserTaskAttempt $attempt, array $answers): array;
}

