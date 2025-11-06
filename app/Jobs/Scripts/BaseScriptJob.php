<?php

namespace App\Jobs\Scripts;

use App\Models\Server;
use App\Models\ScriptJobRun;
use App\Models\User;
use App\Models\UserTaskAttempt;
use App\Scripts\Script;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use App\Services\ScriptWrapper;
use App\Traits\AppendsNotes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base class for all script-related jobs.`
 */
abstract class BaseScriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use AppendsNotes;

    public $timeout = 900; // 15 minutes
    public $tries = 1;

    protected ScriptWrapper $wrapper;
    protected string $script;
    public ?User $authUser;

    public function __construct()
    {
        $this->authUser = Auth::user();
        $this->wrapper = new ScriptWrapper();
    }

    /**
     * Handle job failures.
     */
    public function failed(Throwable $exception): void
    {
        try {
            $jobRun = $this->findAssociatedScriptJobRun();

            if ($jobRun) {
                $jobRun->update([
                    'status' => 'failed',
                    'error_output' => $this->appendToErrorOutput(
                        $jobRun->error_output,
                        sprintf("[%s] Job failed: %s", now()->toDateTimeString(), $exception->getMessage())
                    ),
                    'failed_at' => now(),
                    'completed_at' => $jobRun->completed_at ?? now(),
                ]);
            }
        } catch (Throwable $e) {
            Log::error('Error updating failed script job run', [
                'job_class' => static::class,
                'exception' => $e->getMessage(),
            ]);
        }

        Log::error('Script job failed', [
            'job_class' => static::class,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    protected function findAssociatedScriptJobRun(): ?ScriptJobRun
    {
        if (property_exists($this, 'jobRun') && $this->jobRun instanceof ScriptJobRun) {
            return $this->jobRun;
        }

        if (property_exists($this, 'server') && $this->server) {
            return ScriptJobRun::where('server_id', $this->server->id)
                ->where('status', 'running')
                ->latest('started_at')
                ->first();
        }

        if (property_exists($this, 'attempt') && $this->attempt) {
            return ScriptJobRun::where('attempt_id', $this->attempt->id)
                ->where('status', 'running')
                ->latest('started_at')
                ->first();
        }

        return null;
    }

    protected function appendToErrorOutput(?string $existing, string $message): string
    {
        return $existing ? trim($existing) . "\n" . $message : $message;
    }

    protected function createScriptJobRun(
        Script|ScriptDescriptor $script,
        ?UserTaskAttempt $attempt = null,
        ?Server $server = null,
        array $metadata = []
    ): ScriptJobRun {
        $name = $script instanceof ScriptDescriptor ? $script->name : $script->name();
        $template = $script instanceof ScriptDescriptor ? $script->template : $script->template();

        $this->script = $this->getWrapper()->wrap(view($template, $script instanceof ScriptDescriptor ? $script->data : [])->render());

        return ScriptJobRun::create([
            'script_name' => $name,
            'script_path' => $template,
            'status' => 'running',
            'user_id' => $this->authUser?->id,
            'server_id' => $server?->id,
            'task_id' => $attempt?->task_id,
            'attempt_id' => $attempt?->id,
            'started_at' => now(),
            'metadata' => $metadata,
            'script_content' => $this->script,
        ]);
    }

    protected function executeScriptAndRecord(
        ScriptEngine $engine,
        ScriptJobRun $jobRun,
        ?Server $server = null
    ): array {
        if ($server) {
            $engine->setServer($server);
        }

        $result = $engine->executeViaStdin($jobRun->script_content);

        $jobRun->update([
            'script_content' => $result['script'] ?? $jobRun->script_content,
            'output' => $result['output'] ?? '',
            'error_output' => $result['error_output'] ?? '',
            'exit_code' => $result['exit_code'] ?? 0,
            'status' => $result['successful'] ? 'completed' : 'failed',
            'completed_at' => now(),
        ]);

        return $result;
    }

    protected function getWrapper(): ScriptWrapper
    {
        if (!isset($this->wrapper)) {
            $this->wrapper = new ScriptWrapper();
        }
        return $this->wrapper;
    }

}
