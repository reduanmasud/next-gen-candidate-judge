<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\NotesAccessor;
use App\Traits\HasMeta;

class Server extends Model
{
    use NotesAccessor;
    use HasMeta;

    protected $fillable = [
        'name',
        'ip_address',
        'ssh_username',
        'ssh_password',
        'status',
        'provisioned_at',
        'notes',
        'user_id',
        'metadata',
    ];

    protected $casts = [
        'provisioned_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

