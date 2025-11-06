<?php

namespace App\Jobs\Workspace;

use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Models\UserTaskAttempt;
use App\Scripts\SetDockerComposeYamlScript;
use App\Services\ScriptEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SetDockerComposeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900; // 15 minutes
    public $tries = 1;

    public function __construct(
        public UserTaskAttempt $attempt,
        public Server $server,
        public string $username,
        public string $workspacePath,
        public string $dockerComposeYaml,
    ) {
        //
    }

    public function handle(ScriptEngine $engine): void
    {
        // Create job run record
        $jobRun = ScriptJobRun::create([
            'script_name' => 'Set Docker Compose YAML',
            'script_path' => 'scrips.set_docker_compose_yaml',
            'status' => 'running',
            'user_id' => $this->attempt->user_id,
            'server_id' => $this->server->id,
            'task_id' => $this->attempt->task_id,
            'attempt_id' => $this->attempt->id,
            'started_at' => now(),
            'metadata' => [
                'username' => $this->username,
                'workspace_path' => $this->workspacePath,
            ],
        ]);

        try {
            // Set server for script execution
            $engine->setServer($this->server);

            // Create the script
            $script = new SetDockerComposeYamlScript(
                $this->username,
                $this->workspacePath,
                $this->dockerComposeYaml
            );

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

            // Update attempt notes
            $this->attempt->notes = $this->appendToNotes(
                $this->attempt->notes,
                sprintf("[%s] Wrote docker-compose.yaml to %s", now()->toDateTimeString(), $this->workspacePath)
            );
            $this->attempt->save();

            // If script failed, throw exception to stop the chain
            if (!$result['successful']) {
                throw new \RuntimeException(
                    'Failed to set docker-compose.yaml: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error')
                );
            }

            Log::info('Docker compose YAML set successfully', [
                'attempt_id' => $this->attempt->id,
                'workspace_path' => $this->workspacePath,
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
                    sprintf("[%s] Failed to set docker-compose.yaml: %s", now()->toDateTimeString(), $e->getMessage())
                ),
            ]);

            Log::error('Set docker compose job failed', [
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

    protected function appendToNotes(?string $existing, string $message): string
    {
        $existing = $existing ? trim($existing) . "\n" : '';
        return $existing . $message;
    }
}

