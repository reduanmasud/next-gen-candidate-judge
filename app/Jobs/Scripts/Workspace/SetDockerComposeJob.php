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
use Illuminate\Support\Facades\Log;
use Throwable;

class SetDockerComposeJob extends BaseWorkspaceJob
{

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
        $script = ScriptDescriptor::make(
            'scripts.set_docker_compose_yaml',
            [
                'workspacePath' => $this->workspacePath,
                'dockerComposeYaml' => $this->dockerComposeYaml,
            ],
            'Set Docker Compose Yaml Script for user'. $this->username
        );

        $jobRun = $this->createScriptJobRun($script, $this->attempt, $this->server, [
            'username' => $this->username,
            'workspace_path' => $this->workspacePath,
        ]);

        try {
            $result = $this->executeScriptAndRecord($script, $engine, $jobRun, $this->attempt, $this->server);

            $this->appendAttemptNotes(
                $this->attempt,
                sprintf("[%s] Wrote docker-compose.yaml to %s", now()->toDateTimeString(), $this->workspacePath)
            );

            if (!$result['successful']) {
                throw new \RuntimeException('Failed to set docker-compose.yaml: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
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

    // Uses BaseWorkspaceJob::failed() and HandlesScriptExecution::appendToNotes()
}

