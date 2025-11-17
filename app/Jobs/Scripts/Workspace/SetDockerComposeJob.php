<?php

namespace App\Jobs\Scripts\Workspace;

use App\Contracts\TracksProgressInterface;
use App\Enums\AttemptTaskStatus;
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

    public static function getStepMetadata(): array
    {
        return [
            'id' => 'setting_docker_compose',
            'label' => 'Setting Docker Compose Configuration',
            'description' => 'Setting docker-compose.yaml for workspace',
            'icon' => 'code',
            'estimatedDuration' => 9,
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
        $this->attempt = UserTaskAttempt::find($this->attemptId);

        $script = ScriptDescriptor::make(
            'scripts.set_docker_compose_yaml',
            [
                'pre_scripts' => $this->attempt->task->pre_script || '',
                'post_scripts' => $this->attempt->task->post_script || '',
                'workspace_path' => $this->attempt->getMeta('workspace_path'),
                'docker_compose_yaml' => $this->dockerComposeYaml,
                'ssh_port' => $this->attempt->getMeta('ssh_port'),
            ],
            'Set Docker Compose Yaml Script for user '. $this->attempt->user->name
        );

        $this->attempt->appendNote("Setting docker-compose.yaml for workspace");

        [$this->jobRun, $result] = ScriptJobRun::createAndExecute(
            script: $script,
            engine: app(ScriptEngine::class),
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

        // Update progress: job completed
        $this->attempt->addMeta(['current_step' => 'setting_docker_compose_completed']);

    }

    protected function failed(Throwable $exception): void
    {
        $this->attempt->appendNote("Failed to set docker-compose.yaml: ".$exception->getMessage());
        $this->jobRun->update([
            'status' => 'failed',
            'error_output' => "Failed to set docker-compose.yaml: " . $exception->getMessage(),
            'failed_at' => now(),
            'completed_at' => now(),
        ]);
        $this->attempt->update([
            'status' => AttemptTaskStatus::FAILED,
            'failed_at' => now(),
        ]);
    }
}

