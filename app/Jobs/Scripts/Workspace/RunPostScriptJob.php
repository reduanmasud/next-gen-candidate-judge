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

class RunPostScriptJob extends BaseWorkspaceJob
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
            'id' => 'running_post_script',
            'label' => 'Running Post Script',
            'description' => 'Running post script',
            'icon' => 'code',
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
    protected function execute(): void
    {
        $this->attempt = UserTaskAttempt::find($this->attemptId);
        $this->server = Server::find($this->serverId);
        $script = ScriptDescriptor::make(
            'scripts.run_post_script',
            [
                'post_scripts' => $this->attempt->task->post_script || '',
                'workspace_path' => $this->attempt->getMeta('workspace_path'),
            ],
            'Run Post Script for ' . $this->attempt->user->name
        );
        $this->attempt->appendNote("Running post script");

        [$this->jobRun, $result] = ScriptJobRun::createAndExecute(
            script: $script,
            engine: app(ScriptEngine::class),
            attempt: $this->attempt,
            server: $this->server,
            metadata: [
                'username' => $this->attempt->getMeta('username'),
                'workspace_path' => $this->attempt->getMeta('workspace_path'),
            ]
        );

        $this->attempt->appendNote("Post script completed");
    }

    protected function failed(Throwable $exception): void
    {
        $this->attempt->appendNote("Failed to run post script: ".$exception->getMessage());
        $this->jobRun->update([
            'status' => 'failed',
            'error_output' => "Failed to run post script: " . $exception->getMessage(),
            'failed_at' => now(),
            'completed_at' => now(),
        ]);
        $this->attempt->update([
            'status' => AttemptTaskStatus::FAILED,
            'failed_at' => now(),
        ]);
    }
}
