<?php

namespace App\Jobs\Scripts\Workspace;

use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Models\UserTaskAttempt;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use App\Jobs\Scripts\Concerns\HandlesScriptExecution;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class StartDockerComposeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HandlesScriptExecution;

    public $timeout = 900; // 15 minutes
    public $tries = 1;

    public function __construct(
        public UserTaskAttempt $attempt,
        public Server $server,
        public string $workspacePath,
    ) {
        //
    }

    public function handle(ScriptEngine $engine): void
    {
        $script = ScriptDescriptor::make(
            'scripts.start_docker_compose',
            [
                'workspacePath' => $this->workspacePath,
            ],
            'Start Docker Compose Script'
        );

        $jobRun = $this->createScriptJobRun($script, $this->attempt, $this->server, [
            'workspace_path' => $this->workspacePath,
        ]);

        try {
            $result = $this->executeScriptAndRecord($script, $engine, $jobRun, $this->attempt, $this->server);

            // Parse docker compose output to get container info
            $containers = $this->parseDockerComposeOutput($result['output'] ?? '');
            $primaryContainer = $containers[0] ?? null;
            $publishedPort = $primaryContainer ? $this->extractPublishedPort($primaryContainer) : null;

            // Update attempt with container information
            $this->attempt->update([
                'status' => 'running',
                'container_id' => Arr::get($primaryContainer, 'ID'),
                'container_name' => Arr::get($primaryContainer, 'Name'),
                'container_port' => $publishedPort,
                'started_at' => now(),
            ]);

            $this->appendAttemptNotes(
                $this->attempt,
                sprintf(
                    "[%s] Started docker compose. Container: %s, Port: %s",
                    now()->toDateTimeString(),
                    Arr::get($primaryContainer, 'Name', 'N/A'),
                    $publishedPort ?? 'N/A'
                )
            );

            if (!$result['successful']) {
                throw new \RuntimeException('Failed to start docker compose: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }

            Log::info('Docker compose started successfully', [
                'attempt_id' => $this->attempt->id,
                'workspace_path' => $this->workspacePath,
                'container_id' => Arr::get($primaryContainer, 'ID'),
                'job_run_id' => $jobRun->id,
            ]);

        } catch (Throwable $e) {
            $jobRun->update([
                'status' => 'failed',
                'error_output' => $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            $this->attempt->update([
                'status' => 'failed',
                'completed_at' => now(),
                'notes' => $this->appendToNotes(
                    $this->attempt->notes,
                    sprintf("[%s] Failed to start docker compose: %s", now()->toDateTimeString(), $e->getMessage())
                ),
            ]);

            Log::error('Start docker compose job failed', [
                'attempt_id' => $this->attempt->id,
                'workspace_path' => $this->workspacePath,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to stop the chain
        }
    }


    protected function parseDockerComposeOutput(string $output): array
    {
        if (!preg_match('/__DOCKER_PS_START__(.*?)__DOCKER_PS_END__/s', $output, $matches)) {
            return [];
        }

        $lines = preg_split('/\r?\n/', trim($matches[1]));
        $containers = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $containers[] = $decoded;
            }
        }

        return $containers;
    }

    protected function extractPublishedPort(array $container): ?int
    {
        $publishers = Arr::get($container, 'Publishers');

        if (!is_array($publishers)) {
            return null;
        }

        foreach ($publishers as $publisher) {
            if (isset($publisher['PublishedPort'])) {
                return (int) $publisher['PublishedPort'];
            }
        }

        return null;
    }

        // Uses BaseWorkspaceJob::failed() and HandlesScriptExecution::appendToNotes()
}

