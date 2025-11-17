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

    public static function getStepMetadata(): array
    {
        return [
            'id' => 'creating_user',
            'label' => 'Creating User',
            'description' => 'Setting up workspace user account',
            'icon' => 'user-plus',
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
            'scripts.create_user',
            [
                'username' => $this->attempt->getMeta('username'),
                'password' => $this->attempt->getMeta('password'),
                'workspace_path' => $this->attempt->getMeta('workspace_path'),
            ],
            'Create User Script for ' . $this->attempt->user->name
        );

        $this->attempt->appendNote("Creating user: ".$this->attempt->username);
        $this->attempt->update(
            [
                'status' => AttemptTaskStatus::PREPARING,
                'started_at' => now(),
            ]
        );

        [$this->jobRun, $result] = ScriptJobRun::createAndExecute(
            script: $script,
            engine: app(ScriptEngine::class),
            attempt: $this->attempt,
            server: $this->server,
            metadata: [
                'username' => $this->attempt->getMeta('username'),
                'workspace_path' => $this->attempt->getMeta('workspace_path'),
                'password' => $this->attempt->getMeta('password'),
            ]
        );

        $this->attempt->appendNote("Created user: ".$this->attempt->username);
    }

    protected function failed(Throwable $exception): void
    {
        $this->attempt->appendNote("Failed to create user: ".$exception->getMessage());
        $this->jobRun->update([
            'status' => 'failed',
            'error_output' => "Failed to create user: " . $exception->getMessage(),
            'failed_at' => now(),
            'completed_at' => now(),
        ]);
        $this->attempt->update([
            'status' => AttemptTaskStatus::FAILED,
            'failed_at' => now(),
        ]);

    }

}

