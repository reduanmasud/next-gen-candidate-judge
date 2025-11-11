<?php

namespace App\Models;

use App\Enums\TaskUserLockStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskUserLock extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'reason',
        'status',
    ];

    protected $casts = [
        'status' => TaskUserLockStatus::class,
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

