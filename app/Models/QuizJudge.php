<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizJudge extends Model
{
    protected $fillable = [
        'task_id',
        'questions'
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function quizQuestionAnswers(): HasMany
    {
        return $this->hasMany(QuizQuestionAnswer::class);
    }
}
