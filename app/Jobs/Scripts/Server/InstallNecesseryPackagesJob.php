<?php

namespace App\Jobs\Scripts\Server;

use App\Jobs\Scripts\BaseScriptJob;
use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use Throwable;

class InstallNecesseryPackagesJob extends BaseScriptJob
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

        // Update progress: job started
        $this->server->addMeta(['current_step' => 'installing_packages']);
        $this->server->appendNote("Installing necessery packages");

        try {

            $script = ScriptDescriptor::make(
                template: 'scripts.server.install_necessery_packages',
                data:[],
                name:'Install Necessery Packages '.$this->server->ip_address
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
                throw new \RuntimeException('Failed to install necessery packages: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }

            $this->server->appendNote("Necessery packages installed");

            // Update progress: job completed
            $this->server->addMeta(['current_step' => 'installing_packages_completed']);
        } catch (Throwable $e) {
            $this->server->update(['status' => 'failed']);
            $jobRun->update([
                'status' => 'failed',
                'error_output' => "Failed to install necessery packages: " . $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            $this->server->appendNote("Failed to install necessery packages: ".$e->getMessage());
            $this->server->addMeta(['current_step' => 'failed', 'failed_step' => 'installing_packages']);

            throw $e; // Re-throw to stop the chain
        }
    }
}
