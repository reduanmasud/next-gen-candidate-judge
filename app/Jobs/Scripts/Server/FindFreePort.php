<?php


namespace App\Jobs\Scripts\Server;

use App\Jobs\Scripts\BaseScriptJob;
use App\Models\Server;
use App\Models\UserTaskAttempt;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use Throwable;

class FindFreePort extends BaseScriptJob
{
    public Server $server;
    public UserTaskAttempt $attempt;

    public function __construct(
        public Int $serverId,
        public Int $attemptId,
    ) {
        parent::__construct();
    }
    public function handle(ScriptEngine $engine): void
    {

        $this->server = Server::find($this->serverId);
        $this->attempt = UserTaskAttempt::find($this->attemptId);

        // TODO: Need to add error handling

        $jobRun = $this->createScriptJobRun(
            script: ScriptDescriptor::make('scripts.server.find_free_port', [
                "workspacePath" => $this->attempt->getMeta('workspace_path'),
            ], 'Find Free SSH Port '.$this->server->ip_address),
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

            $port = $this->extractPortFromOutput($result['output'] ?? '');

            $this->attempt->update([
                'container_port' => $port,
            ]);
            $this->attempt->addMeta(['ssh_port' => $port]);
            $this->attempt->save();

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

    private function extractPortFromOutput(string $output): int
    {
        $port = null;
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            if (strpos($line, '__PORT_START__') !== false) {
                $port = (int) str_replace('__PORT_END__', '', $line);
                break;
            }
        }

        if ($port === null) {
            throw new \RuntimeException('Failed to extract port from output');
        }

        return $port;
    }
}
