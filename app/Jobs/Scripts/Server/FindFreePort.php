<?php


namespace App\Jobs\Scripts\Server;

use App\Enums\AttemptTaskStatus;
use App\Jobs\Scripts\BaseScriptJob;
use App\Models\ScriptJobRun;
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

        // Update progress: job started
        $this->attempt->addMeta(['current_step' => 'finding_free_port']);

        $this->attempt->appendNote("Finding free port for SSH");

        try {

            if(!$this->server)
            {
                throw new \RuntimeException('Server not found');
            }

            $script = ScriptDescriptor::make('scripts.server.find_free_port', [
                "workspace_path" => $this->attempt->getMeta('workspace_path'),
            ], 'Find Free SSH Port '.$this->server->ip_address);



            [$jobRun, $result] = ScriptJobRun::createAndExecute(
                script: $script,
                engine: $engine,
                attempt: $this->attempt,
                server: $this->server,
                metadata: [
                    'attempt_id' => $this->attempt->id,
                    'server_id' => $this->server->id,
                    'workspace_path' => $this->attempt->getMeta('workspace_path'),
                ]
            );



            $data = $this->extractJsonOutput($result['output'] ?? '');
            $this->attempt->appendNote("Found free port for SSH: ".$data['ssh_port']);


            $this->attempt->update([
                'container_port' => $data['ssh_port'],
            ]);
            $this->attempt->addMeta(['ssh_port' => $data['ssh_port']]);

            // Update progress: job completed
            $this->attempt->addMeta(['current_step' => 'finding_free_port_completed']);

        } catch (Throwable $e) {
            $this->attempt->update([
                'status' => AttemptTaskStatus::FAILED,
                'failed_at' => now(),
            ]);
            $this->attempt->addMeta(['current_step' => 'failed', 'failed_step' => 'finding_free_port']);
            $this->attempt->appendNote("Failed to find free port: ".$e->getMessage());

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
