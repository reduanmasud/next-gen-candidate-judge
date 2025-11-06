<?php

namespace App\Jobs\Scripts\Server;

use App\Jobs\Scripts\BaseScriptJob;
use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use Illuminate\Support\Facades\Log;
use Throwable;

class SetupTraefikForServerJob extends BaseScriptJob
{
    public function __construct(
        public Server $server,
        public string $cloudflareApiToken,
    ) {
        //
    }

    public function handle(ScriptEngine $engine): void
    {
        $script = ScriptDescriptor::make(
            'scripts.setup_traefik_for_server',
            [
                'cloudflareApiToken' => $this->cloudflareApiToken,
            ],
            'Setup Traefik for Server'
        );

        $jobRun = $this->createScriptJobRun($script, null, $this->server, [
            'server_id' => $this->server->id,
        ]);

        try {
            $result = $this->executeScriptAndRecord($script, $engine, $jobRun, null, $this->server);

            if (!$result['successful']) {
                throw new \RuntimeException('Failed to setup Traefik: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }

            Log::info('Traefik setup successfully', [
                'server_id' => $this->server->id,
                'job_run_id' => $jobRun->id,
            ]);

            // Update server status
            $server->status = 'provisioned';
            $server->save();
        } catch (Throwable $e) {
            $jobRun->update([
                'status' => 'failed',
                'error_output' => "Failed to setup Traefik: " . $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            Log::error('Setup Traefik for server job failed', [
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to stop the chain
        }
    }

    // Uses BaseScriptJob::failed() for error handling
}

