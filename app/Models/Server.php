<?php

namespace App\Models;

use App\Contracts\TracksProgressInterface;
use App\Events\ServerCreatedEvent;
use App\Events\ServerProvisioningStatusUpdatedEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\NotesAccessor;
use App\Traits\HasMeta;
use App\Traits\TracksProgress;

class Server extends Model implements TracksProgressInterface
{
    use NotesAccessor;
    use HasMeta;
    use TracksProgress;

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

    public function getWorkflowType(): string
    {
        return 'server_provisioning';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::created(function ($server) {
            broadcast(new ServerCreatedEvent($server));
        });

        static::updated(function ($server) {
            if ($server->wasChanged('status') || $server->wasChanged('metadata')) {
                $metadata = $server->getAllMeta();
                broadcast(new ServerProvisioningStatusUpdatedEvent(
                    serverId: $server->id,
                    status: $server->status,
                    currentStep: $metadata['current_step'] ?? null,
                    metadata: $metadata
                ));
            }
        });
    }
}

