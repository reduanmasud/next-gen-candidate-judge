<?php

namespace App\Jobs\Scripts\Workspace;

use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Models\UserTaskAttempt;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use App\Jobs\Scripts\Concerns\HandlesScriptExecution;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateUserJob extends BaseWorkspaceJob
{
    

    public function __construct(
        public UserTaskAttempt $attempt,
        public Server $server,
        public string $username,
        public string $password,
    ) {
        //
    }

    public function handle(ScriptEngine $engine): void
    {
        $script = ScriptDescriptor::make(
            'scrips.create_user',
            [
                'username' => $this->username,
                'password' => $this->password,
            ],
            'Create User Script for ' . $this->username
        );

        $jobRun = $this->createScriptJobRun($script, $this->attempt, $this->server, [
            'username' => $this->username,
        ]);

        try {
            $result = $this->executeScriptAndRecord($script, $engine, $jobRun, $this->attempt, $this->server);

            $this->appendAttemptNotes(
                $this->attempt,
                sprintf("[%s] Created user: %s", now()->toDateTimeString(), $this->username)
            );

            if (!$result['successful']) {
                throw new \RuntimeException('Failed to create user: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }

            Log::info('User created successfully', [
                'attempt_id' => $this->attempt->id,
                'username' => $this->username,
                'job_run_id' => $jobRun->id,
            ]);

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

            Log::error('Create user job failed', [
                'attempt_id' => $this->attempt->id,
                'username' => $this->username,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to stop the chain
        }
    }

    // Uses BaseWorkspaceJob::failed() and HandlesScriptExecution::appendToNotes()

}

