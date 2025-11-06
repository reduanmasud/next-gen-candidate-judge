<?php

namespace App\Services;

use App\Jobs\ExecuteScriptJob;
use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Models\Task;
use App\Scripts\Script;
use App\Services\ScriptWrapper;

class ScriptJobService
{

    public function runScript(Script $script, ?Task $task = null, ?Server $server = null, ?array $metadata = null, ?string $notes = null): ScriptJobRun
    {
        $wrapper = new ScriptWrapper();
        $jobRun = ScriptJobRun::create([
            'script_name' => $script->name(),
            'script_path' => $script->template(),
            'user_id' => auth()->id(),
            'server_id' => $server?->id,
            'task_id' => $task?->id,
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
