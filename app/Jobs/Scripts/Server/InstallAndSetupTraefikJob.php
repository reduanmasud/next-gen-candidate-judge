<?php

namespace App\Jobs\Scripts\Server;

use App\Jobs\Scripts\BaseScriptJob;
use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use Throwable;

class InstallAndSetupTraefikJob extends BaseScriptJob
{

    public Server $server;

    public function __construct(
        public Int $serverId,
        public string $cloudflareApiToken,
        public string $cloudflareEmail,
        public string $cloudflareDomain,
    ) {
        parent::__construct();
        
    }
    public function handle(ScriptEngine $engine): void
    {
        $this->server = Server::find($this->serverId);
        $this->server->appendNote("Installing and setting up traefik");

        try {

            $script = ScriptDescriptor::make(
            template: 'scripts.server.install_and_setup_traefik', 
            data:[
                'cloudflareApiToken' => $this->cloudflareApiToken,
                'cloudflareEmail' => $this->cloudflareEmail,
                'cloudflareDomain' => $this->cloudflareDomain,
            ], 
            name:'Install and Setup Traefik '.$this->server->ip_address
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
                throw new \RuntimeException('Failed to install and setup traefik: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }

            $this->server->update(['status' => 'provisioned']);
            $this->server->appendNote("Traefik installed and setup");

        } catch (Throwable $e) {
            $this->server->update(['status' => 'failed']);
            $jobRun->update([
                'status' => 'failed',
                'error_output' => "Failed to install and setup traefik: " . $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            $this->server->appendNote("Failed to install and setup traefik: ".$e->getMessage());

            throw $e; // Re-throw to stop the chain
        }
    }
}
