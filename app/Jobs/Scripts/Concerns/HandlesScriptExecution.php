<?php

namespace App\Jobs\Scripts\Concerns;

use App\Models\ScriptJobRun;
use App\Models\UserTaskAttempt;
use App\Models\Server;
use App\Scripts\Script;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use App\Traits\AppendsNotes;

trait HandlesScriptExecution
{
    use AppendsNotes;
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

        return ScriptJobRun::create([
            'script_name' => $name,
            'script_path' => $path,
            'status' => 'running',
            'user_id' => $attempt?->user_id ?? auth()->id(),
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
     * Execute a script via the provided ScriptEngine and update the job run record.
     * Returns the raw engine result array.
     */
    protected function executeScriptAndRecord(Script|ScriptDescriptor $script, ScriptEngine $engine, ScriptJobRun $jobRun, ?UserTaskAttempt $attempt = null, ?Server $server = null): array
    {
        if ($server) {
            $engine->setServer($server);
        }

        // Render script content depending on descriptor vs object
        if ($script instanceof ScriptDescriptor) {
            $rendered = view($script->template, $script->data)->render();
        } else {
            $rendered = view($script->template(), $script->data())->render();
        }

        // Store rendered script content before executing
        $jobRun->update([
            'script_content' => $rendered,
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
