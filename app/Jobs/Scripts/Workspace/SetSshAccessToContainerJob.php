<?php

namespace App\Jobs\Scripts\Workspace;

use App\Contracts\TracksProgressInterface;
use App\Enums\AttemptTaskStatus;
use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Models\UserTaskAttempt;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use Throwable;

class SetSshAccessToContainerJob extends BaseWorkspaceJob
{
    public UserTaskAttempt $attempt;
    public Server $server;

    public function __construct(
        public Int $attemptId,
        public Int $serverId,
    ) {
        parent::__construct();
    }
    public static function getStepMetadata(): array
    {
        return [
            'id' => 'setting_ssh_access',
            'label' => 'Setting SSH Access',
            'description' => 'Setting SSH access to container',
            'icon' => 'key',
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
        $this->server = Server::find($this->serverId);

        $script = ScriptDescriptor::make(
            'scripts.set_ssh_access_to_container',
            [
                'username' => $this->attempt->getMeta('username'),
                'container_name' => $this->attempt->container_name,
                'full_domain' => $this->attempt->getMeta('domain'),
                'workspace_path' => $this->attempt->getMeta('workspace_path'),
            ],
            'Set SSH Access to Container Script for ' . $this->attempt->user->name
        );

        $this->attempt->appendNote("Setting SSH access to container");

        [$this->jobRun, $result] = ScriptJobRun::createAndExecute(
            script: $script,
            engine: app(ScriptEngine::class),
            attempt: $this->attempt,
            server: $this->server,
            metadata: [
                'username' => $this->attempt->getMeta('username'),
                'container_name' => $this->attempt->container_name,
                'full_domain' => $this->attempt->getMeta('domain'),
                'workspace_path' => $this->attempt->getMeta('workspace_path'),
            ]
        );

        $this->attempt->appendNote("Set SSH access to container: ".$this->attempt->container_name);

        // Update progress: job completed
        $this->attempt->addMeta(['current_step' => 'setting_ssh_access_completed']);

    }

    protected function failed(Throwable $exception): void
    {
        $this->attempt->appendNote("Failed to set SSH access to container: ".$exception->getMessage());
        $this->jobRun->update([
            'status' => 'failed',
            'error_output' => "Failed to set SSH access to container: " . $exception->getMessage(),
            'failed_at' => now(),
            'completed_at' => now(),
        ]);
        $this->attempt->update([
            'status' => AttemptTaskStatus::FAILED,
            'failed_at' => now(),
        ]);
    }
}

