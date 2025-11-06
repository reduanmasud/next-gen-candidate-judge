<?php

namespace App\Jobs\Scripts\Server;

use App\Jobs\Scripts\BaseScriptJob;
use App\Models\Server;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use Illuminate\Support\Facades\Log;
use Throwable;

class InstallDockerJob extends BaseScriptJob
{
    public function __construct(
        public Server $server,
    ) {
        parent::__construct();
    }
    public function handle(ScriptEngine $engine): void
    {
        $jobRun = $this->createScriptJobRun(
            script: ScriptDescriptor::make('scripts.server.install_docker', [], 'Install Docker '.$this->server->ip_address),
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
                throw new \RuntimeException('Failed to install docker: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }
        } catch (Throwable $e) {
            $this->server->update(['status' => 'failed']);
            $jobRun->update([
                'status' => 'failed',
                'error_output' => "Failed to install docker: " . $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            Log::error('Install docker job failed', [
                'server_id' => $this->server->id,
                'job_run_id' => $jobRun->id,
                'exception' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to stop the chain
        }
    }
}
