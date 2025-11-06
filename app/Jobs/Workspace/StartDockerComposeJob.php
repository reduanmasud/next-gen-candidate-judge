<?php

namespace App\Jobs\Workspace;

use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Models\UserTaskAttempt;
use App\Scripts\StartDockerComposeScript;
use App\Services\ScriptEngine;
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
        // Create job run record
        $jobRun = ScriptJobRun::create([
            'script_name' => 'Start Docker Compose',
            'script_path' => 'scrips.start_docker_compose',
            'status' => 'running',
            'user_id' => $this->attempt->user_id,
            'server_id' => $this->server->id,
            'task_id' => $this->attempt->task_id,
            'attempt_id' => $this->attempt->id,
            'started_at' => now(),
            'metadata' => [
                'workspace_path' => $this->workspacePath,
            ],
        ]);

        try {
            // Set server for script execution
            $engine->setServer($this->server);

            // Create the script
            $script = new StartDockerComposeScript($this->workspacePath);

            // Store script content
            $jobRun->update([
                'script_content' => view($script->template(), $script->data())->render(),
            ]);

            // Execute the script
            $result = $engine->executeViaStdin($script);

            // Update job run with results
            $jobRun->update([
                'output' => $result['output'] ?? '',
                'error_output' => $result['error_output'] ?? '',
                'exit_code' => $result['exit_code'] ?? 0,
                'status' => $result['successful'] ? 'completed' : 'failed',
                'completed_at' => now(),
            ]);

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

            // Update attempt notes
            $this->attempt->notes = $this->appendToNotes(
                $this->attempt->notes,
                sprintf(
                    "[%s] Started docker compose. Container: %s, Port: %s",
                    now()->toDateTimeString(),
                    Arr::get($primaryContainer, 'Name', 'N/A'),
                    $publishedPort ?? 'N/A'
                )
            );
            $this->attempt->save();

            // If script failed, throw exception to stop the chain
            if (!$result['successful']) {
                throw new \RuntimeException(
                    'Failed to start docker compose: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error')
                );
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

    public function failed(Throwable $exception): void
    {
        $this->attempt->update([
            'status' => 'failed',
            'completed_at' => now(),
            'notes' => $this->appendToNotes(
                $this->attempt->notes,
                sprintf("[%s] Job failed: %s", now()->toDateTimeString(), $exception->getMessage())
            ),
        ]);
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

    protected function appendToNotes(?string $existing, string $message): string
    {
        $existing = $existing ? trim($existing) . "\n" : '';
        return $existing . $message;
    }
}

