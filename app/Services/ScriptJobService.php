<?php

namespace App\Services;

use App\Jobs\ExecuteScriptJob;
use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Models\Task;
use App\Scripts\Script;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptWrapper;

class ScriptJobService
{

    public function runScript(Script|ScriptDescriptor $script, ?Task $task = null, ?Server $server = null, ?array $metadata = null, ?string $notes = null): ScriptJobRun
    {
        $wrapper = new ScriptWrapper();
        // Create a minimal job run first, then update with script-specific values so we support both
        // Script and ScriptDescriptor without calling methods that may not exist on the descriptor.
        $jobRun = ScriptJobRun::create([
            'user_id' => auth()->id(),
            'server_id' => $server?->id,
            'task_id' => $task?->id,
            'status' => 'pending',
        ]);

        // Support both old Script objects and new ScriptDescriptor
        if ($script instanceof ScriptDescriptor) {
            $name = $script->name;
            $path = $script->template;
        } else {
            $name = $script->name();
            $path = $script->template();
        }

        // update previously created jobRun with the correct values (ensure compatibility)
        $jobRun->update([
            'script_name' => $name,
            'script_path' => $path,
        ]);

        ExecuteScriptJob::dispatch($jobRun, $script, $task, $server);

        return $jobRun;
    }

    /**
     * Get job runs with filters
     */
    public function getJobRuns(
        ?string $status = null,
        ?int $userId = null,
        int $perPage = 15
    ) {
        $query = ScriptJobRun::with(['user', 'task', 'server'])
            ->latest('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->paginate($perPage);
    }

}
