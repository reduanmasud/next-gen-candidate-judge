<?php

namespace App\Jobs\Scripts\Workspace;

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

    public function handle(ScriptEngine $engine): void
    {
        $this->attempt = UserTaskAttempt::find($this->attemptId);
        $this->server = Server::find($this->serverId);

        try {
            // Update progress: job started
            $this->attempt->addMeta(['current_step' => 'setting_ssh_access']);

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



            [$jobRun, $result] = ScriptJobRun::createAndExecute(
                script: $script,
                engine: $engine,
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

        } catch (Throwable $e) {

            $this->attempt->update([
                'status' => AttemptTaskStatus::FAILED,
                'failed_at' => now(),
            ]);

            $this->attempt->addMeta(['current_step' => 'failed', 'failed_step' => 'setting_ssh_access']);
            $this->attempt->appendNote("Failed to set SSH access to container: ".$e->getMessage());

            throw $e; // Re-throw to stop the chain
        }
    }
}

