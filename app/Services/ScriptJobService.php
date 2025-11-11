<?php

namespace App\Services;

use App\Models\ScriptJobRun;


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
