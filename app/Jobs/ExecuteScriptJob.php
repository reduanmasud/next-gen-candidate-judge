<?php

namespace App\Jobs; 



use App\Models\ScriptJobRun;
use App\Models\Task;
use App\Models\Server;
use App\Scripts\Script;
use App\Scripts\ScriptDescriptor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\ScriptEngine;
use App\Services\ScriptWrapper;

class ExecuteScriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;
    public $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ScriptJobRun $jobRun,
        public Script|ScriptDescriptor $script,
        public ?Task $task = null,
        public ?Server $server = null,
    ) {
        //
    }

    public function handle(ScriptEngine $engin): void
    {
        Log::info('Executing script', [
            'script' => $this->script instanceof Script ? $this->script->name() : $this->script->name,
            'task' => $this->task?->id,
        ]);

        $this->jobRun->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {

            if($this->server)
            {
                $engin->setServer($this->server);
            }

            $result = $engin->executeViaStdin($this->script);

            $this->jobRun->update([
                'script_content' => $result['script'],
                'output' => $result['output'],
                'error_output' => $result['error_output'],
                'exit_code' => $result['exit_code'],
                'status' => $result['successful'] ? 'completed' : 'failed',
                'completed_at' => now(),
            ]);

            Log::info('Script completed', [
                'script' => $this->script instanceof Script ? $this->script->name() : $this->script->name,
                'task' => $this->task?->id,
                'exit_code' => $result['exit_code'],
            ]);


        } catch (\Throwable $e) {
            $this->jobRun->update([
                'status' => 'failed',
                'failed_at' => now(),
            ]);

            Log::error('Script failed', [
                'script' => $this->script instanceof Script ? $this->script->name() : $this->script->name,
                'task' => $this->task?->id,
                'exception' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to fail the job properly
        }

    }
}
