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

        // Update progress: job started
        $this->server->addMeta(['current_step' => 'installing_traefik']);
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

            $this->server->update(['status' => 'provisioned', 'provisioned_at' => now()]);
            $this->server->appendNote("Traefik installed and setup");

            // Update progress: all jobs completed
            $this->server->addMeta(['current_step' => 'completed']);

        } catch (Throwable $e) {
            $this->server->update(['status' => 'failed']);
            $jobRun->update([
                'status' => 'failed',
                'error_output' => "Failed to install and setup traefik: " . $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            $this->server->appendNote("Failed to install and setup traefik: ".$e->getMessage());
            $this->server->addMeta(['current_step' => 'failed', 'failed_step' => 'installing_traefik']);

            throw $e; // Re-throw to stop the chain
        }
    }
}
