<?php

namespace App\Jobs\Scripts\Concerns;

use App\Models\ScriptJobRun;
use App\Models\UserTaskAttempt;
use App\Models\Server;
use App\Scripts\Script;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use App\Traits\AppendsNotes;
use Illuminate\Support\Facades\Log;
use App\Services\ScriptWrapper;

trait HandlesScriptExecution
{
    use AppendsNotes;
    protected ScriptWrapper $wrapper;
    /**
     * Create a ScriptJobRun record with standard fields.
     */
    protected function createScriptJobRun(Script|ScriptDescriptor $script, ?UserTaskAttempt $attempt = null, ?Server $server = null, array $metadata = []): ScriptJobRun
    {
        // Support both Script objects and the new ScriptDescriptor
        if ($script instanceof ScriptDescriptor) {
            $name = $script->name;
            $path = $script->template;
        } else {
            $name = $script->name();
            $path = $script->template();
        }
        $this->wrapper = new ScriptWrapper();
        $this->script = $this->wrapper->wrap(view($script->template, $script->data)->render());

        Log::info('Creating script job run', [
            'script_name' => $name,
            'script_path' => $path,
            'user_id' => $this->authUser->id,
            'server_id' => $server?->id,
            'task_id' => $attempt?->task_id,
            'attempt_id' => $attempt?->id,
        ]);

        return ScriptJobRun::create([
            'script_name' => $name,
            'script_path' => $path,
            'status' => 'running',
            'user_id' => $this->authUser->id,
            'server_id' => $server?->id,
            'task_id' => $attempt?->task_id,
            'attempt_id' => $attempt?->id,
            'started_at' => now(),
            'metadata' => $metadata,
        ]);
    }
    
    /**
     * Append a timestamped message to a UserTaskAttempt's notes field.
     */
    protected function appendAttemptNotes(UserTaskAttempt $attempt, string $message): void
    {
        $attempt->update([
            'notes' => $this->appendToNotes($attempt->notes, $message),
        ]);
    }

    /**
     * Append a timestamped message to a Server's notes field.
     */
    protected function appendServerNotes(Server $server, string $message): void
    {
        $server->update([
            'notes' => $this->appendToNotes($server->notes, $message),
        ]);
    }

    /**
     * Execute a script via the provided ScriptEngine and update the job run record.
     * Returns the raw engine result array.
     */
    protected function executeScriptAndRecord(String $script, ScriptEngine $engine, ScriptJobRun $jobRun, ?UserTaskAttempt $attempt = null, ?Server $server = null): array
    {
        if ($server) {
            $engine->setServer($server);
        }

        // Store rendered script content before executing
        $jobRun->update([
            'script_content' => $script,
        ]);

        $result = $engine->executeViaStdin($script);

        $jobRun->update([
            'script_content' => $result['script'] ?? $jobRun->script_content ?? '',
            'output' => $result['output'] ?? '',
            'error_output' => $result['error_output'] ?? '',
            'exit_code' => $result['exit_code'] ?? 0,
            'status' => $result['successful'] ? 'completed' : 'failed',
            'completed_at' => now(),
        ]);

        return $result;
    }

}
