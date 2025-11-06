<?php

namespace App\Jobs\Scripts\Server;

use App\Jobs\Scripts\BaseScriptJob;
use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProvisionServerJob extends BaseScriptJob
{
    public function __construct(
        public Server $server,
        public string $sshUsername,
        public string $sshPassword,
    ) {
        //
    }

    public function handle(ScriptEngine $engine): void
    {
        $script = ScriptDescriptor::make(
            'scripts.provision_server',
            [
                'ipAddress' => $this->server->ip_address,
                'sshUser' => $this->sshUsername,
                'sshPassword' => $this->sshPassword,
            ],
            'Provision Server Script for ' . $this->server->ip_address
        );

        $jobRun = $this->createScriptJobRun($script, null, $this->server, [
            'server_id' => $this->server->id,
        ]);

        try {
            $result = $this->executeScriptAndRecord($script, $engine, $jobRun, null, $this->server);

            if (!$result['successful']) {
                throw new \RuntimeException('Failed to provision server: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }

            Log::info('Server provisioned successfully', [
                'server_id' => $this->server->id,
                'job_run_id' => $jobRun->id,
            ]);
        } catch (Throwable $e) {
            $jobRun->update([
                'status' => 'failed',
                'error_output' => "Failed to provision server: " . $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            Log::error('Provision server job failed', [
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to stop the chain
        }
    }

    // Uses BaseScriptJob::failed() for error handling
}

