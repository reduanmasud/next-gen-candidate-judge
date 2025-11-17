<?php


namespace App\Jobs\Scripts\Server;

use App\Contracts\TracksProgressInterface;
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

    public static function getStepMetadata(): array
    {
        return [
            'id' => 'finding_free_port',
            'label' => 'Finding Free Port',
            'description' => 'Finding free port for SSH',
            'icon' => 'port',
            'estimatedDuration' => 8,
        ];
    }

    public function getTrackableModel(): TracksProgressInterface
    {
        if(!isset($this->attempt)) {
            $this->attempt = UserTaskAttempt::find($this->attemptId);
        }
        return $this->attempt;
    }


    protected function execute(): void
    {
        $this->server = Server::find($this->serverId);
        $this->attempt = UserTaskAttempt::find($this->attemptId);

        $this->attempt->appendNote("Finding free port for SSH");



        if(!$this->server)
        {
            throw new \RuntimeException('Server not found');
        }

        $script = ScriptDescriptor::make('scripts.server.find_free_port', [
            "workspace_path" => $this->attempt->getMeta('workspace_path'),
        ], 'Find Free SSH Port '.$this->server->ip_address);

        [$this->jobRun, $result] = ScriptJobRun::createAndExecute(
            script: $script,
            engine: app(ScriptEngine::class),
            attempt: $this->attempt,
            server: $this->server,
            metadata: [
                'attempt_id' => $this->attempt->id,
                'server_id' => $this->server->id,
                'workspace_path' => $this->attempt->getMeta('workspace_path'),
            ]
        );

        if (!$result['successful']) {
            throw new \RuntimeException('Failed to find free port: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
        }

        $data = $this->extractJsonOutput($result['output'] ?? '');
        $this->attempt->appendNote("Found free port for SSH: ".$data['ssh_port']);

        $this->attempt->update([
            'container_port' => $data['ssh_port'],
        ]);
        $this->attempt->addMeta(['ssh_port' => $data['ssh_port']]);

    }

    protected function failed(Throwable $exception): void
    {
        $this->attempt->appendNote("Failed to find free port: ".$exception->getMessage());
        $this->attempt->update([
            'status' => AttemptTaskStatus::FAILED,
            'failed_at' => now(),
        ]);
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
