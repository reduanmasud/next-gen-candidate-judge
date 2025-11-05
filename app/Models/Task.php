<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'title',
        'description',
        'docker_compose_yaml',
        'score',
        'is_active',
        'server_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }
    public function attempts(): HasMany
    {
        return $this->hasMany(UserTaskAttempt::class);
    }

    public function userAttempt(User $user): HasMany
    {
        return $this->attempts()->where('user_id', $user->id);
    }

}
