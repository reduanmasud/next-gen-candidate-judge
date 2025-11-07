<?php

namespace App\Services\JudgeServices;

use App\Interfaces\SolutionCheckerInterface;
use App\Models\Task;
use App\Models\UserTaskAttempt;

class QuizJudgeService implements SolutionCheckerInterface
{

    public function check(Task $task, UserTaskAttempt $attempt): array|string
    {
            $quizJudge = $task->quizJudge;
            $quizQuestionAnswers = $quizJudge->quizQuestionAnswers;
            $correctAnswers = $quizQuestionAnswers->where('is_correct', true)->count();
            
            $userAnswers = $attempt->answer;
            $correctUserAnswers = $userAnswers->where('is_correct', true)->count();

            $score = ($correctUserAnswers / $correctAnswers) * $attempt->task->score;

            return [
                'correct_answers' => $correctAnswers,
                'user_answers' => $correctUserAnswers,
            ];
    }
}