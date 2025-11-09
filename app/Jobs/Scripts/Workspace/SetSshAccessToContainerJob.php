<?php

namespace App\Jobs\Scripts\Workspace;

use App\Models\Server;
use App\Models\UserTaskAttempt;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use App\Traits\AppendAttemptNotes;
use Throwable;

class SetSshAccessToContainerJob extends BaseWorkspaceJob
{
    use AppendAttemptNotes;
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

        $this->attempt->refresh();

        $script = ScriptDescriptor::make(
            'scripts.set_ssh_access_to_container',
            [
                'container_name' => $this->attempt->container_name,
                'domain' => $this->attempt->getMeta('workspace_domain'),
                'workspacePath' => $this->attempt->getMeta('workspace_path'),
            ],
            'Set SSH Access to Container Script for ' . $this->attempt->user->name
        );  

        $jobRun = $this->createScriptJobRun($script, $this->attempt, $this->server, [
            'container_name' => $this->attempt->container_name,
            'workspace_domain' => $this->attempt->getMeta('workspace_domain'),
            'workspace_path' => $this->attempt->getMeta('workspace_path'),
        ]);

        try {
            $result = $this->executeScriptAndRecord(
                engine: $engine, 
                jobRun: $jobRun, 
                server: $this->server
            );

            $this->appendAttemptNotes(
                $this->attempt,
                sprintf("[%s] Set SSH access to container: %s", now()->toDateTimeString(), $this->attempt->getMeta('container_name'))
            );

            if (!$result['successful']) {
                throw new \RuntimeException('Failed to set SSH access to container: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }
        } catch (Throwable $e) {
            $jobRun->update([
                'status' => 'failed',
                'error_output' => $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            $this->appendAttemptNotes(
                $this->attempt,
                sprintf("[%s] Failed to set SSH access to container: %s", now()->toDateTimeString(), $e->getMessage())
            );

            throw $e; // Re-throw to stop the chain
        }
    }
}

