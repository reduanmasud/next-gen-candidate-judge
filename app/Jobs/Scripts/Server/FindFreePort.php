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

            $data = $this->extractJsonOutput($result['output'] ?? '');

            $this->attempt->update([
                'container_port' => $data['ssh_port'],
            ]);
            $this->attempt->addMeta(['ssh_port' => $data['ssh_port']]);
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

    private function extractJsonOutput(string $output): array
    {
        if (preg_match('/__OUTPUT_JSON__([\s\S]*?)__OUTPUT_JSON_END__/m', $output, $matches)) {
            $json = trim($matches[1]);
            $data = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException("Invalid JSON output: " . json_last_error_msg());
            }

            return $data;
        }

        throw new \RuntimeException("Failed to find JSON output in script:\n" . $output);
    }


}
