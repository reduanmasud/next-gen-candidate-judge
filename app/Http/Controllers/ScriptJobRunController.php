<?php

namespace App\Http\Controllers;

use App\Models\ScriptJobRun;
use App\Services\ScriptJobService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScriptJobRunController extends Controller
{
    public function __construct(
        protected ScriptJobService $jobService
    ) {}

    /**
     * Display a listing of job runs
     */
    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $userId = $request->query('user_id');

        $jobRuns = $this->jobService->getJobRuns($status, $userId);

        return Inertia::render('jobs/index', [
            'jobRuns' => $jobRuns,
            'filters' => [
                'status' => $status,
                'user_id' => $userId,
            ],
        ]);
    }

    /**
     * Display a specific job run
     */
    public function show(ScriptJobRun $jobRun): Response
    {
        $jobRun->load(['user', 'task', 'server', 'attempt']);

        return Inertia::render('jobs/show', [
            'jobRun' => $jobRun,
        ]);
    }

    /**
     * Get real-time status (for polling)
     */
    public function status(ScriptJobRun $jobRun)
    {
        return response()->json([
            'status' => $jobRun->status,
            'output' => $jobRun->output,
            'error_output' => $jobRun->error_output,
            'exit_code' => $jobRun->exit_code,
            'script_content' => $jobRun->script_content,
            'completed_at' => $jobRun->completed_at,
        ]);
    }
}