<?php

namespace App\Jobs\Scripts\Workspace;

use App\Contracts\TracksProgressInterface;
use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Models\UserTaskAttempt;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use Throwable;

class DeleteWorkspaceJob extends BaseWorkspaceJob
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
            'id' => 'deleting_workspace',
            'label' => 'Deleting Workspace',
            'description' => 'Deleting workspace and user',
            'icon' => 'trash',
            'estimatedDuration' => 5,
        ];
    }
    public function getTrackableModel(): TracksProgressInterface
    {
        if(!isset($this->attempt)) {
            $this->attempt = UserTaskAttempt::find($this->attemptId);
        }
        return $this->attempt;
    }

    protected function failed(Throwable $exception): void
    {
        $this->attempt->appendNote("Failed to delete workspace: ".$exception->getMessage());
        $this->jobRun->update([
            'status' => 'failed',
            'error_output' => "Failed to delete workspace: " . $exception->getMessage(),
            'failed_at' => now(),
            'completed_at' => now(),
        ]);
        $this->attempt->update([
            'status' => 'failed',
            'failed_at' => now(),
        ]);
    }

    protected function execute(): void
    {
        $this->attempt = UserTaskAttempt::find($this->attemptId);
        $this->server = Server::find($this->serverId);

        $script = ScriptDescriptor::make(
            'scripts.delete_workspace',
            [
                'username' => "user_".$this->attempt->id,
                'container_name' => $this->attempt->container_name,
                'allowssh' => $this->attempt->task->allowssh,
                'workspacePath' => $this->attempt->getMeta('workspace_path'),
            ],
            'Delete Workspace Script for ' . $this->attempt->user->name
        );

        $this->attempt->appendNote("Deleting workspace for user: ".$this->attempt->getMeta('username'));

        [$this->jobRun, $result] = ScriptJobRun::createAndExecute(
            script: $script,
            engine: app(ScriptEngine::class),
            attempt: $this->attempt,
            server: $this->server,
            metadata: [
                'username' => $this->attempt->getMeta('username'),
                'container_name' => $this->attempt->getMeta('container_name'),
            ]
        );

        $this->attempt->appendNote("Deleted workspace for user: ".$this->attempt->getMeta('username'));
    }
}

