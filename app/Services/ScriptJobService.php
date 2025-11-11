<?php

namespace App\Services;

use App\Jobs\ExecuteScriptJob;
use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Models\Task;
use App\Scripts\Script;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptWrapper;
use Illuminate\Support\Facades\Auth;

class ScriptJobService
{


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
