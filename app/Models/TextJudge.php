<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TextJudge extends Model
{
    protected $fillable = [
        'task_id',
        'questions',
        'answers',
    ];


    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
