<?php

namespace App\Jobs;

use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Services\ScriptEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RerunScriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900; // 15 minutes
    public $tries = 1;

    public function __construct(
        public int $originalJobRunId,
        public int $newJobRunId
    ) {
        //
    }

    public function handle(ScriptEngine $engine): void
    {
        $originalJobRun = ScriptJobRun::find($this->originalJobRunId);
        $newJobRun = ScriptJobRun::find($this->newJobRunId);

        if (!$originalJobRun || !$newJobRun) {
            Log::error('RerunScriptJob: Job run not found', [
                'original_id' => $this->originalJobRunId,
                'new_id' => $this->newJobRunId,
            ]);
            return;
        }

        // Load server if exists
        $server = $originalJobRun->server_id ? Server::find($originalJobRun->server_id) : null;

        try {
            // Execute the script
            if ($server) {
                $engine->setServer($server);
            }

            $newJobRun->update([
                'status' => 'running',
                'started_at' => now(),
            ]);

            $result = $engine->executeViaStdin($newJobRun->script_content);

            if (!$result['successful']) {
                throw new \RuntimeException('Failed to execute script: ' . ($result['error_output'] ?? $result['output'] ?? 'Unknown error'));
            }

            $newJobRun->update([
                'status' => 'completed',
                'output' => $result['output'] ?? '',
                'error_output' => $result['error_output'] ?? '',
                'exit_code' => $result['exit_code'] ?? 0,
                'completed_at' => now(),
            ]);

        } catch (Throwable $e) {
            Log::error('RerunScriptJob failed', [
                'job_run_id' => $this->newJobRunId,
                'error' => $e->getMessage(),
            ]);

            $newJobRun->update([
                'status' => 'failed',
                'error_output' => $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $newJobRun = ScriptJobRun::find($this->newJobRunId);
        
        if ($newJobRun) {
            $newJobRun->update([
                'status' => 'failed',
                'error_output' => $exception->getMessage(),
                'failed_at' => now(),
                'completed_at' => now(),
            ]);
        }
    }
}

