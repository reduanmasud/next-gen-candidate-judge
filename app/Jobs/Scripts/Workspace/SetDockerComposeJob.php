<?php

namespace App\Jobs\Scripts\Workspace;

use App\Models\Server;
use App\Models\UserTaskAttempt;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use App\Traits\AppendAttemptNotes;
use Throwable;

class SetDockerComposeJob extends BaseWorkspaceJob
{
    use AppendAttemptNotes;
    public UserTaskAttempt $attempt;

    public function __construct(
        public Int $attemptId,
        public string $dockerComposeYaml,
    ) {
        parent::__construct();
    }

    public function handle(ScriptEngine $engine): void
    {
        $this->attempt = UserTaskAttempt::find($this->attemptId);

        $script = ScriptDescriptor::make(
            'scripts.set_docker_compose_yaml',
            [
                'preScripts' => $this->attempt->task->pre_script || '',
                'postScripts' => $this->attempt->task->post_script || '',
                'workspacePath' => $this->attempt->getMeta('workspace_path'),
                'dockerComposeYaml' => $this->dockerComposeYaml,
            ],
            'Set Docker Compose Yaml Script for user '. $this->attempt->user->name,
        );

        $jobRun = $this->createScriptJobRun($script, $this->attempt, $this->attempt->task->server, [
            'username' => $this->attempt->user->name,
            'workspace_path' => $this->attempt->getMeta('workspace_path'),
            'workspace_domain' => $this->attempt->getMeta('workspace_domain'),
            'attempt_id' => $this->attempt->id,
            'domain' => $this->attempt->getMeta('domain'),
            'ssh_port' => $this->attempt->getMeta('ssh_port'),
            'access_user' => $this->attempt->getMeta('access_user'),
            'access_password' => $this->attempt->getMeta('access_password'), 
        ]);

        try {
            $result = $this->executeScriptAndRecord(
                engine: $engine, 
                jobRun: $jobRun, 
                server: $this->attempt->task->server
            );

            $this->appendAttemptNotes(
                $this->attempt,
                sprintf("[%s] Wrote docker-compose.yaml to %s", now()->toDateTimeString(), $this->attempt->getMeta('workspace_path'))
            );

            if (!$result['successful']) {
                throw new \RuntimeException('Failed to set docker-compose.yaml: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }

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


            throw $e; // Re-throw to stop the chain
        }
    }

    // Uses BaseWorkspaceJob::failed() and HandlesScriptExecution::appendToNotes()
}

