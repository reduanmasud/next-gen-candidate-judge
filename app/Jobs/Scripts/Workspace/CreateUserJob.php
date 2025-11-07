<?php

namespace App\Jobs\Scripts\Workspace;


use App\Models\Server;
use App\Models\UserTaskAttempt;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use App\Traits\AppendAttemptNotes;
use Throwable;

class CreateUserJob extends BaseWorkspaceJob
{
    
    use AppendAttemptNotes;
    public function __construct(
        public UserTaskAttempt $attempt,
        public Server $server,
        public string $username,
        public string $password,
        public string $workspacePath,
    ) {
        parent::__construct();
    }

    public function handle(ScriptEngine $engine): void
    {
        $script = ScriptDescriptor::make(
            'scripts.create_user',
            [
                'username' => $this->username,
                'password' => $this->password,
                'workspacePath' => $this->workspacePath,
            ],
            'Create User Script for ' . $this->username
        );

        $jobRun = $this->createScriptJobRun($script, $this->attempt, $this->server, [
            'username' => $this->username,
        ]);

        try {
            $result = $this->executeScriptAndRecord(
                engine: $engine, 
                jobRun: $jobRun, 
                server: $this->server
            );

            $this->appendAttemptNotes(
                $this->attempt,
                sprintf("[%s] Created user: %s", now()->toDateTimeString(), $this->username)
            );

            if (!$result['successful']) {
                throw new \RuntimeException('Failed to create user: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }


        } catch (Throwable $e) {
            $jobRun->update([
                'status' => 'failed',
                'error_output' => $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            $this->attempt->update([
                'status' => 'failed',
                'completed_at' => now(),
                'notes' => $this->appendToNotes(
                    $this->attempt->notes,
                    sprintf("[%s] Failed to create user: %s", now()->toDateTimeString(), $e->getMessage())
                ),
            ]);

            throw $e; // Re-throw to stop the chain
        }
    }

    // Uses BaseWorkspaceJob::failed() and HandlesScriptExecution::appendToNotes()

}

