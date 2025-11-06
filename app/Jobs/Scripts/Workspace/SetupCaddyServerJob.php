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

class SetupCaddyServerJob extends BaseWorkspaceJob
{
    use HandlesScriptExecution;
    public $timeout = 900; // 15 minutes
    public $tries = 1;
    public function __construct(
        public UserTaskAttempt $attempt,
        public Server $server,
        public string $username,
    ) {
        //
    }
    public function handle(ScriptEngine $engine): void
    {
        $script = ScriptDescriptor::make(
            'scripts.setup_caddy_server_for_user',
            [
                'username' => $this->username,
            ],
            'Setup Caddy Server for User'
        );

        $jobRun = $this->createScriptJobRun($script, $this->attempt, $this->server, [
            'username' => $this->username,
        ]);

        try {
            $result = $this->executeScriptAndRecord($script, $engine, $jobRun, $this->attempt, $this->server);

            $this->appendAttemptNotes(
                $this->attempt,
                sprintf("[%s] Setup Caddy server for user: %s", now()->toDateTimeString(), $this->username)
            );

            if (!$result['successful']) {
                throw new \RuntimeException('Failed to setup Caddy server: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }

            Log::info('Caddy server setup successfully', [
                'attempt_id' => $this->attempt->id,
                'username' => $this->username,
                'job_run_id' => $jobRun->id,
            ]);
        } catch (Throwable $e) {
            $jobRun->update([
                'status' => 'failed',
                'error_output' =>"Failed to setup Caddy server: " . $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            $this->attempt->update([
                'status' => 'failed',
                'completed_at' => now(),
                'notes' => $this->appendToNotes(
                    $this->attempt->notes,
                    sprintf("[%s] Failed to setup Caddy server: %s", now()->toDateTimeString(), $e->getMessage())
                ),
            ]);

            Log::error('Setup Caddy server job failed', [
                'attempt_id' => $this->attempt->id,
                'username' => $this->username,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to stop the chain
        }
    }

    // Uses BaseWorkspaceJob::failed() and HandlesScriptExecution::appendToNotes()
}

