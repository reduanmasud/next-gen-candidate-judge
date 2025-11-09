<?php

namespace App\Jobs\Scripts\Workspace;

use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Models\UserTaskAttempt;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use Throwable;

class CreateUserJob extends BaseWorkspaceJob
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
            // Update progress: job started
            $this->attempt->addMeta(['current_step' => 'creating_user']);

            $script = ScriptDescriptor::make(
                'scripts.create_user',
                [
                    'username' => $this->attempt->getMeta('username'),
                    'password' => $this->attempt->getMeta('password'),
                    'workspace_path' => $this->attempt->getMeta('workspace_path'),
                ],
                'Create User Script for ' . $this->attempt->user->name
            );

            $this->attempt->appendNote("Creating user: ".$this->attempt->username);


            [$jobRun, $result] = ScriptJobRun::createAndExecute(
                script: $script,
                engine: $engine,
                attempt: $this->attempt,
                server: $this->server,
                metadata: [
                    'username' => $this->attempt->getMeta('username'),
                    'workspace_path' => $this->attempt->getMeta('workspace_path'),
                    'password' => $this->attempt->getMeta('password'),
                ]
            );

            $this->attempt->appendNote("Created user: ".$this->attempt->username);

            // Update progress: job completed
            $this->attempt->addMeta(['current_step' => 'creating_user_completed']);

        } catch (Throwable $e) {

            $this->attempt->update([
                'status' => 'failed',
                'failed_at' => now(),
            ]);
            $this->attempt->addMeta(['current_step' => 'failed', 'failed_step' => 'creating_user']);
            $this->attempt->appendNote("Failed to create user: ".$e->getMessage());

            throw $e; // Re-throw to stop the chain
        }
    }

    // Uses BaseWorkspaceJob::failed() and HandlesScriptExecution::appendToNotes()

}

