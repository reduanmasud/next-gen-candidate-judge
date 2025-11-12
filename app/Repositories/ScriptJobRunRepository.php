<?php

namespace App\Repositories;

use App\Models\ScriptJobRun;
use App\Models\User;

class ScriptJobRunRepository
{
    public function getAllJobRunPaginited(int $perPage = 30)
    {
        return ScriptJobRun::with(['user', 'task', 'server'])
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function getSingleJobRunWithRelations(ScriptJobRun $jobRun)
    {
        return $jobRun->load(['user', 'task', 'server']);
    }
}
