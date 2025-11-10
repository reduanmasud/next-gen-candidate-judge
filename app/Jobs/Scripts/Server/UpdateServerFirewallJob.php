<?php


namespace App\Jobs\Scripts\Server;

use App\Jobs\Scripts\BaseScriptJob;
use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use Throwable;

class UpdateServerFirewallJob extends BaseScriptJob
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
        $this->server->addMeta(['current_step' => 'updating_firewall']);
        $this->server->appendNote("Updating server firewall");

        try {

            $script = ScriptDescriptor::make(
                template: 'scripts.server.update_server_firewall',
                data:[],
                name:'Update Server Firewall '.$this->server->ip_address
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
                throw new \RuntimeException('Failed to update firewall: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }

            $this->server->appendNote("Server firewall updated");

            // Update progress: job completed
            $this->server->addMeta(['current_step' => 'updating_firewall_completed']);

        } catch (Throwable $e) {
            $this->server->update(['status' => 'failed']);
            $jobRun->update([
                'status' => 'failed',
                'error_output' => "Failed to update firewall: " . $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            $this->server->appendNote("Failed to update firewall: ".$e->getMessage());
            $this->server->addMeta(['current_step' => 'failed', 'failed_step' => 'updating_firewall']);

            throw $e; // Re-throw to stop the chain
        }
    }

}
