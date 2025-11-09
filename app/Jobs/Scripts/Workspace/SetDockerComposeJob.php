<?php

namespace App\Jobs\Scripts\Workspace;

use App\Models\ScriptJobRun;
use App\Models\UserTaskAttempt;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use Throwable;

class SetDockerComposeJob extends BaseWorkspaceJob
{

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

        try {

            $script = ScriptDescriptor::make(
                'scripts.set_docker_compose_yaml',
                [
                    'pre_scripts' => $this->attempt->task->pre_script || '',
                    'post_scripts' => $this->attempt->task->post_script || '',
                    'workspace_path' => $this->attempt->getMeta('workspace_path'),
                    'docker_compose_yaml' => $this->dockerComposeYaml,
                    'ssh_port' => $this->attempt->getMeta('ssh_port'),
                ],
                'Set Docker Compose Yaml Script for user '. $this->attempt->user->name,
            );

            $this->attempt->appendNote("Setting docker-compose.yaml for workspace");

        

            [$jobRun, $result] = ScriptJobRun::createAndExecute(
                script: $script,
                engine: $engine,
                attempt: $this->attempt,
                server: $this->attempt->task->server,
                metadata: [
                    'username' => $this->attempt->user->name,
                    'workspace_path' => $this->attempt->getMeta('workspace_path'),
                    'full_domain' => $this->attempt->getMeta('domain'),
                    'attempt_id' => $this->attempt->id,
                    'ssh_port' => $this->attempt->getMeta('ssh_port'),
                ]
            );

            $this->attempt->appendNote("Set docker-compose.yaml for workspace");

        } catch (Throwable $e) {


            $this->attempt->update([
                'status' => 'failed',
                'failed_at' => now(),
            ]);
            $this->attempt->appendNote("Failed to set docker-compose.yaml: ".$e->getMessage());

            throw $e; // Re-throw to stop the chain
        }
    }

    // Uses BaseWorkspaceJob::failed() and HandlesScriptExecution::appendToNotes()
}

