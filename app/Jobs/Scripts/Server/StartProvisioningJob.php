<?php

namespace App\Jobs\Scripts\Server;

use App\Contracts\TracksProgressInterface;
use App\Jobs\Scripts\BaseScriptJob;
use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use RuntimeException;
use Throwable;

class StartProvisioningJob extends BaseScriptJob
{

    public Server $server;

    public function __construct(
        public Int $serverId,
    ) {
        parent::__construct();
    }

    public static function getStepMetadata(): array
    {
        return [
            'id' => 'starting_provision',
            'label' => 'Starting Provision',
            'description' => 'Starting server provision',
            'icon' => 'server',
            'estimatedDuration' => 9,
        ];
    }

    public function getTrackableModel(): TracksProgressInterface
    {
        if(!isset($this->server)) {
            $this->server = Server::find($this->serverId);
        }
        return $this->server;
    }


    protected function execute(): void
    {
        $this->server = Server::find($this->serverId);
        $this->server->update(['status' => 'provisioning']);
        $this->server->appendNote("Server provisioning started");

        $script = ScriptDescriptor::make(
            template: 'scripts.server.start_server_provision',
            data:[],
            name:'Start Provisioning '.$this->server->ip_address
        );

        [$this->jobRun, $result]= ScriptJobRun::createAndExecute(
            script: $script,
            engine: app(ScriptEngine::class),
            server: $this->server,
            metadata: [
                'server_id' => $this->server->id,
            ]
        );

        if (!$result['successful']) {
            throw new RuntimeException('Failed to start provisioning: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
        }

    }

    protected function failed(Throwable $exception): void
    {
        $this->server->update(['status' => 'failed']);
        $this->jobRun->update([
            'status' => 'failed',
            'error_output' => "Failed to start provisioning: " . $exception->getMessage(),
            'failed_at' => now(),
            'completed_at' => now(),
        ]);
        $this->server->appendNote("Failed to start provisioning: ".$exception->getMessage());
    }
}
