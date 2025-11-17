<?php

namespace App\Jobs\Scripts\Server;

use App\Contracts\TracksProgressInterface;
use App\Jobs\Scripts\BaseScriptJob;
use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use Throwable;

class UpdateServerPackageJob extends BaseScriptJob
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
            'id' => 'updating_packages',
            'label' => 'Updating Packages',
            'description' => 'Updating server packages',
            'icon' => 'package',
            'estimatedDuration' => 20,
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
        $this->server->appendNote("Updating server packages");

        $script = ScriptDescriptor::make(
            template: 'scripts.server.update_server_packages',
            data:[],
            name:'Update Server Packages '.$this->server->ip_address
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
            throw new \RuntimeException('Failed to update server packages: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
        }

        $this->server->appendNote("Server packages updated");
    }


    protected function failed(Throwable $exception): void
    {
        $this->server->update(['status' => 'failed']);
        $this->jobRun->update([
            'status' => 'failed',
            'error_output' => "Failed to update server packages: " . $exception->getMessage(),
            'failed_at' => now(),
            'completed_at' => now(),
        ]);
        $this->server->appendNote("Failed to update server packages: ".$exception->getMessage());
    }
}
