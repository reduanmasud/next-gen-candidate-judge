<?php

namespace App\Jobs\Workspace;

use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Models\UserTaskAttempt;
use App\Scripts\CreateUserScript;
use App\Services\ScriptEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900; // 15 minutes
    public $tries = 1;

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
        // Create job run record
        $jobRun = ScriptJobRun::create([
            'script_name' => 'Create User',
            'script_path' => 'scrips.create_user',
            'status' => 'running',
            'user_id' => $this->attempt->user_id,
            'server_id' => $this->server->id,
            'task_id' => $this->attempt->task_id,
            'attempt_id' => $this->attempt->id,
            'started_at' => now(),
            'metadata' => [
                'username' => $this->username,
            ],
        ]);

        try {
            // Set server for script execution
            $engine->setServer($this->server);

            // Create the script
            $script = new CreateUserScript($this->username, $this->password);

            // Store script content
            $jobRun->update([
                'script_content' => view($script->template(), $script->data())->render(),
            ]);

            // Execute the script
            $result = $engine->executeViaStdin($script);

            // Update job run with results
            $jobRun->update([
                'script_content' => $result['script'] ?? '',
                'output' => $result['output'] ?? '',
                'error_output' => $result['error_output'] ?? '',
                'exit_code' => $result['exit_code'] ?? 0,
                'status' => $result['successful'] ? 'completed' : 'failed',
                'completed_at' => now(),
            ]);

            // Update attempt notes
            $this->attempt->notes = $this->appendToNotes(
                $this->attempt->notes,
                sprintf("[%s] Created user: %s", now()->toDateTimeString(), $this->username)
            );
            $this->attempt->save();

            // If script failed, throw exception to stop the chain
            if (!$result['successful']) {
                throw new \RuntimeException(
                    'Failed to create user: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error')
                );
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

    public function failed(Throwable $exception): void
    {
        $this->attempt->update([
            'status' => 'failed',
            'completed_at' => now(),
            'notes' => $this->appendToNotes(
                $this->attempt->notes,
                sprintf("[%s] Job failed: %s", now()->toDateTimeString(), $exception->getMessage())
            ),
        ]);
    }

    protected function appendToNotes(?string $existing, string $message): string
    {
        $existing = $existing ? trim($existing) . "\n" : '';
        return $existing . $message;
    }
}

