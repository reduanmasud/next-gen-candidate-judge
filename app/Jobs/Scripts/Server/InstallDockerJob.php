<?php

namespace App\Jobs\Scripts\Server;

use App\Contracts\TracksProgressInterface;
use App\Jobs\Scripts\BaseScriptJob;
use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use Throwable;

class InstallDockerJob extends BaseScriptJob
{
    public Server $server;

    public function __construct(
        public Int $serverId,
    ) {
        parent::__construct();
    }

    public static function getStepMetadata(): array
    {
        return [
            'id' => 'installing_docker',
            'label' => 'Installing Docker',
            'description' => 'Installing docker',
            'icon' => 'docker',
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
        $this->server->appendNote("Installing docker");

        $script = ScriptDescriptor::make(
            template: 'scripts.server.install_docker',
            data:[],
            name:'Install Docker '.$this->server->ip_address
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
            throw new \RuntimeException('Failed to install docker: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
        }

        $this->server->appendNote("Docker installed");
    }

    protected function failed(Throwable $exception): void
    {
        $this->server->update(['status' => 'failed']);
        $this->jobRun->update([
            'status' => 'failed',
            'error_output' => "Failed to install docker: " . $exception->getMessage(),
            'failed_at' => now(),
            'completed_at' => now(),
        ]);
        $this->server->appendNote("Failed to install docker: ".$exception->getMessage());
    }
}
