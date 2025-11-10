<?php

namespace App\Jobs\Scripts\Server;

use App\Jobs\Scripts\BaseScriptJob;
use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use Throwable;

class InstallDockerJob extends BaseScriptJob
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
        $this->server->appendNote("Installing docker");

        try {

            $script = ScriptDescriptor::make(
                template: 'scripts.server.install_docker', 
                data:[], 
                name:'Install Docker '.$this->server->ip_address
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
                throw new \RuntimeException('Failed to install docker: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }

            $this->server->appendNote("Docker installed");
        } catch (Throwable $e) {
            $this->server->update(['status' => 'failed']);
            $jobRun->update([
                'status' => 'failed',
                'error_output' => "Failed to install docker: " . $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            $this->server->appendNote("Failed to install docker: ".$e->getMessage());

            throw $e; // Re-throw to stop the chain
        }
    }
}
