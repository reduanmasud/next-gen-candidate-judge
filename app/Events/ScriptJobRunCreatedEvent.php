<?php

namespace App\Events;

use App\Models\ScriptJobRun;
use App\Repositories\ScriptJobRunRepository;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use App\Http\Resources\ScriptJobRunResource;
use Illuminate\Queue\SerializesModels;

class ScriptJobRunCreatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ScriptJobRun $jobRun;
    /**
     * Create a new event instance.
     */
    public function __construct(ScriptJobRun $jobRun)
    {
        $this->jobRun = app(ScriptJobRunRepository::class)->getSingleJobRunWithRelations($jobRun);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('job-runs-updated'),
        ];
    }


    public function broadcastWith(): array
    {
        return [
            'jobRun' => (new ScriptJobRunResource($this->jobRun))->resolve(),
        ];
    }
}
