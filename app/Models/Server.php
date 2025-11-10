<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\NotesAccessor;

class Server extends Model
{
    use NotesAccessor;

    protected $fillable = [
        'name',
        'ip_address',
        'ssh_username',
        'ssh_password',
        'status',
        'provisioned_at',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'provisioned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

