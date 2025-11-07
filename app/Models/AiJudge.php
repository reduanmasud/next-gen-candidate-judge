<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiJudge extends Model
{
    protected $fillable = [
        'task_id',
        'prompt',
        'question',
        'answer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

}
