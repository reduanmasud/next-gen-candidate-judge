<?php

namespace App\Jobs\Scripts\Server;

use App\Jobs\Scripts\BaseScriptJob;
use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use Throwable;

class UpdateServerPackageJob extends BaseScriptJob
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

        $this->server->appendNote("Updating server packages");

        try {

            $script = ScriptDescriptor::make(
                template: 'scripts.server.update_server_packages', 
                data:[], 
                name:'Update Server Packages '.$this->server->ip_address
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
                throw new \RuntimeException('Failed to update server packages: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }

            $this->server->appendNote("Server packages updated");
        } catch (Throwable $e) {
            $this->server->update(['status' => 'failed']);
            $jobRun->update([
                'status' => 'failed',
                'error_output' => "Failed to update server packages: " . $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            $this->server->appendNote("Failed to update server packages: ".$e->getMessage());

            throw $e; // Re-throw to stop the chain
        }
    }
}
