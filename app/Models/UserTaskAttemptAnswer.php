<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTaskAttemptAnswer extends Model
{
    protected $fillable = [
        'user_task_attempt_id',
        'answers',
        'score',
        'notes',
    ];

    protected $casts = [
        'answers' => 'array',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(UserTaskAttempt::class);
    }
}
