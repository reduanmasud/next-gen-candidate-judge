<?php

namespace App\Jobs\Scripts\Server;

use App\Contracts\TracksProgressInterface;
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

    public static function getStepMetadata(): array
    {
        return [
            'id' => 'installing_traefik',
            'label' => 'Installing Traefik',
            'description' => 'Installing and setting up traefik',
            'icon' => 'traefik',
            'estimatedDuration' => 5,
        ];
    }

    public function getTrackableModel(): TracksProgressInterface
    {
        if(!isset($this->server)) {
            $this->server = Server::find($this->serverId);
        }
        return $this->server;
    }


    protected function execute(): void
    {
        $this->server = Server::find($this->serverId);
        $this->server->appendNote("Installing and setting up traefik");

        $script = ScriptDescriptor::make(
            template: 'scripts.server.install_and_setup_traefik',
            data:[
                'cloudflareApiToken' => $this->cloudflareApiToken,
                'cloudflareEmail' => $this->cloudflareEmail,
                'cloudflareDomain' => $this->cloudflareDomain,
            ],
            name:'Install and Setup Traefik '.$this->server->ip_address
        );

        [$this->jobRun, $result]= ScriptJobRun::createAndExecute(
            script: $script,
            engine: app(ScriptEngine::class),
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


    }


    protected function failed(Throwable $exception): void
    {
        $this->server->update(['status' => 'failed']);
        $this->jobRun->update([
                    'status' => 'failed',
                    'error_output' => "Failed to install and setup traefik: " . $exception->getMessage(),
                    'failed_at' => now(),
                    'completed_at' => now(),
                ]);
        $this->server->appendNote("Failed to install and setup traefik: ".$exception->getMessage());
    }
}
