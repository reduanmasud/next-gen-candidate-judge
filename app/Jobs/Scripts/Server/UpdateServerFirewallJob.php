<?php


namespace App\Jobs\Scripts\Server;

use App\Jobs\Scripts\BaseScriptJob;
use App\Models\Server;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use Throwable;

class UpdateServerFirewallJob extends BaseScriptJob
{
    public function __construct(
        public Server $server,
    ) {
        parent::__construct();
    }
    public function handle(ScriptEngine $engine): void
    {
        $jobRun = $this->createScriptJobRun(
            script: ScriptDescriptor::make('scripts.server.update_server_firewall', [], 'Update Server Firewall '.$this->server->ip_address),
            server: $this->server,
            metadata: ['server_id' => $this->server->id]
        );

        try {
            $result = $this->executeScriptAndRecord(
                engine:$engine, 
                jobRun:$jobRun, 
                server:$this->server
            );

            if (!$result['successful']) {
                throw new \RuntimeException('Failed to find free port: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }

            $jobRun->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

        } catch (Throwable $e) {
            $jobRun->update([
                'status' => 'failed',
                'error_output' => "Failed to find free port: " . $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            throw $e; // Re-throw to stop the chain
        }
    }

}
