<?php

namespace App\Jobs\Scripts\Workspace;

use App\Models\Server;
use App\Models\UserTaskAttempt;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use App\Traits\AppendAttemptNotes;
use Throwable;

class DeleteWorkspaceJob extends BaseWorkspaceJob
{
    use AppendAttemptNotes;
    public function __construct(
        public UserTaskAttempt $attempt,
        public Server $server,
    ) {
        parent::__construct();
    }
    public function handle(ScriptEngine $engine): void
    {
        $script = ScriptDescriptor::make(
            'scripts.delete_workspace',
            [
                'username' => "user_".$this->attempt->id,
                'container_name' => $this->attempt->container_name,
                'allowssh' => $this->attempt->task->allowssh,
            ],
            'Delete Workspace Script for ' . $this->attempt->user->name
        );
        $jobRun = $this->createScriptJobRun($script, $this->attempt, $this->server, [
            'username' => $this->attempt->getMeta('username'),
            'containerName' => $this->attempt->getMeta('container_name'),
        ]);
        try {
            $result = $this->executeScriptAndRecord(
                engine: $engine, 
                jobRun: $jobRun, 
                server: $this->server
            );
            if (!$result['successful']) {
                throw new \RuntimeException('Failed to delete workspace: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }
            $this->appendAttemptNotes(
                $this->attempt,
                sprintf("[%s] Deleted workspace for user: %s", now()->toDateTimeString(), $this->attempt->getMeta('username'))
            );
        }
        catch (Throwable $e) {
            $jobRun->update([
                'status' => 'failed',
                'error_output' => $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);
            $this->appendAttemptNotes(
                $this->attempt,
                sprintf("[%s] Failed to delete workspace: %s", now()->toDateTimeString(), $e->getMessage())
            );

            throw $e; // Re-throw to stop the chain
        }
    }
}

