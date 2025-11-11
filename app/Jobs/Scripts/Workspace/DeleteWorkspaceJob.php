<?php

namespace App\Jobs\Scripts\Workspace;

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
    public function handle(ScriptEngine $engine): void
    {

        $this->attempt = UserTaskAttempt::find($this->attemptId);
        $this->server = Server::find($this->serverId);
        try {
            $this->attempt->addMeta(['current_step' => 'deleting_workspace']);
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
            [$jobRun, $result] = ScriptJobRun::createAndExecute(
                script: $script,
                engine: $engine,
                attempt: $this->attempt,
                server: $this->server,
                metadata: [
                    'username' => $this->attempt->getMeta('username'),
                    'container_name' => $this->attempt->getMeta('container_name'),
                ]
            );

            $this->attempt->appendNote("Deleted workspace for user: ".$this->attempt->getMeta('username'));
            $this->attempt->addMeta(['current_step' => 'completed']);
        }
        catch (Throwable $e) {
            $this->attempt->update([
                'status' => 'failed',
                'error_output' => $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);
            $this->attempt->addMeta(['current_step' => 'failed', 'failed_step' => 'deleting_workspace']);
            $this->attempt->appendNote("Failed to delete workspace: ".$e->getMessage());

            throw $e; // Re-throw to stop the chain
        }
    }
}

