<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestionAnswer extends Model
{
    protected $fillable = [
        'quiz_judge_id',
        'choice',
        'is_correct',
    ];

    public function quizJudge(): BelongsTo
    {
        return $this->belongsTo(QuizJudge::class);
    }

    public function task(): BelongsTo
    {
        return $this->quizJudge->task();
    }
}
