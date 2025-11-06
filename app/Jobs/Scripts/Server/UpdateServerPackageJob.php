<?php

namespace App\Jobs\Scripts\Server;

use App\Jobs\Scripts\BaseScriptJob;
use App\Models\Server;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateServerPackageJob extends BaseScriptJob
{
    public function __construct(
        public Server $server,
    ) {
        parent::__construct();
    }
    public function handle(ScriptEngine $engine): void
    {
        $jobRun = $this->createScriptJobRun(
            script: ScriptDescriptor::make('scripts.server.update_server_packages', [], 'Update Server Packages '.$this->server->ip_address),
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
                throw new \RuntimeException('Failed to update server packages: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }
        } catch (Throwable $e) {
            $this->server->update(['status' => 'failed']);
            $jobRun->update([
                'status' => 'failed',
                'error_output' => "Failed to update server packages: " . $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            Log::error('Update server packages job failed', [
                'server_id' => $this->server->id,
                'job_run_id' => $jobRun->id,
                'exception' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to stop the chain
        }
    }
}
