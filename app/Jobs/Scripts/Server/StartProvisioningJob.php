<?php

namespace App\Jobs\Scripts\Server;

use App\Jobs\Scripts\BaseScriptJob;
use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use App\Traits\AppendServerNotes;
use Illuminate\Support\Facades\Log;
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

    public function handle(ScriptEngine $engine): void
    {

        $this->server = Server::find($this->serverId);

        // Update server status
        $this->server->update(['status' => 'provisioning']);
        $this->server->appendNote("Server provisioning started");
        try {

            $script = ScriptDescriptor::make(
                template: 'scripts.server.start_server_provision', 
                data:[], 
                name:'Start Provisioning '.$this->server->ip_address
            );

            [$jobRun, $result]= ScriptJobRun::createAndExecute(
                script: $script,
                engine: $engine,
                server: $this->server,
                metadata: [
                    'server_id' => $this->server->id,
                ]
            );

            if (!$result['successful']) {
                throw new RuntimeException('Failed to start provisioning: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }


        } catch (Throwable $e) {
            $jobRun->update([
                'status' => 'failed',
                'error_output' => "Failed to start provisioning: " . $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            $this->server->appendNote("Failed to start provisioning: ".$e->getMessage());
            $this->server->update(['status' => 'failed']);

            throw $e; // Re-throw to stop the chain
        }
    }
}
