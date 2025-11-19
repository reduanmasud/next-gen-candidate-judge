<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use App\Enums\ScriptJobStatus;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkspaceStatusUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $attemptId,
        public string|ScriptJobStatus $status,
        public ?string $currentStep = null,
        public ?array $metadata = null,
    ) {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('workspace-updates.' . $this->attemptId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'WorkspaceStatusUpdatedEvent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'attemptId' => $this->attemptId,
            'status' => $this->status instanceof ScriptJobStatus ? $this->status->value : $this->status,
            'currentStep' => $this->currentStep,
            'metadata' => $this->metadata,
        ];
    }
}
