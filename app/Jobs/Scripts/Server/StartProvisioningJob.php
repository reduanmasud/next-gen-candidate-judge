<?php

namespace App\Jobs\Scripts\Server;

use App\Jobs\Scripts\BaseScriptJob;
use App\Models\Server;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use App\Traits\AppendServerNotes;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class StartProvisioningJob extends BaseScriptJob
{
    use AppendServerNotes;

    public function __construct(
        public Server $server,
    ) {
        parent::__construct();
    }

    public function handle(ScriptEngine $engine): void
    {
        $this->server->update(['status' => 'provisioning']);
        $this->appendServerNotes($this->server, sprintf("[%s] Server provisioning started", now()));

        $jobRun = $this->createScriptJobRun(
            script: ScriptDescriptor::make('scripts.server.start_server_provision', [], 'Start Provisioning '.$this->server->ip_address),
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
                throw new RuntimeException('Failed to start provisioning: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }
        } catch (Throwable $e) {
            $jobRun->update([
                'status' => 'failed',
                'error_output' => "Failed to start provisioning: " . $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            Log::error('Provisioning script failed', [
                'server_id' => $this->server->id,
                'job_run_id' => $jobRun->id,
                'exception' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to stop the chain
        }
    }
}
