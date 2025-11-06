<?php

namespace App\Jobs\Scripts\Server;

use App\Jobs\Scripts\BaseScriptJob;
use App\Models\Server;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use Illuminate\Support\Facades\Log;
use Throwable;

class InstallAndSetupTraefikJob extends BaseScriptJob
{

    protected $cloudflareEmail = "reduanmasudcse@gmail.com";
    protected $cloudflareDomain = "wpqa.online";


    public function __construct(
        public Server $server,
        public string $cloudflareApiToken,
    ) {
        parent::__construct();
        $this->cloudflareApiToken = $cloudflareApiToken;
        
    }
    public function handle(ScriptEngine $engine): void
    {
        $jobRun = $this->createScriptJobRun(
            script: ScriptDescriptor::make('scripts.server.install_and_setup_traefik', [
                'cloudflareApiToken' => $this->cloudflareApiToken,
                'cloudflareEmail' => $this->cloudflareEmail,
                'cloudflareDomain' => $this->cloudflareDomain,
            ], 'Install and Setup Traefik '.$this->server->ip_address),
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
                throw new \RuntimeException('Failed to install and setup traefik: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }

            $this->server->update(['status' => 'provisioned']);

        } catch (Throwable $e) {
            $this->server->update(['status' => 'failed']);
            $jobRun->update([
                'status' => 'failed',
                'error_output' => "Failed to install and setup traefik: " . $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            Log::error('Install and setup traefik job failed', [
                'server_id' => $this->server->id,
                'job_run_id' => $jobRun->id,
                'exception' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to stop the chain
        }
    }
}
