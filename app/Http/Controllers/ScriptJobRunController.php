<?php

namespace App\Http\Controllers;

use App\Http\Resources\ScriptJobRunResource;
use App\Jobs\RerunScriptJob;
use App\Models\ScriptJobRun;
use App\Repositories\ScriptJobRunRepository;
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

        $jobRuns = app(ScriptJobRunRepository::class)->getAllJobRunPaginited(100);

        $resolvedJobs = ScriptJobRunResource::collection($jobRuns);
        return Inertia::render('jobs/index', [
            'jobRuns' => $resolvedJobs,
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

    /**
     * Re-run a job by creating a duplicate and dispatching it to the queue
     */
    public function rerun(Request $request, ScriptJobRun $jobRun)
    {
        // Create a new job run with the same parameters
        $newJobRun = ScriptJobRun::create([
            'script_name' => $jobRun->script_name . ' (Re-run)',
            'script_path' => $jobRun->script_path,
            'script_content' => $jobRun->script_content,
            'status' => 'pending',
            'user_id' => $request->user()->id,
            'server_id' => $jobRun->server_id,
            'task_id' => $jobRun->task_id,
            'attempt_id' => $jobRun->attempt_id,
            'metadata' => $jobRun->metadata,
        ]);

        // Dispatch the job to the queue
        RerunScriptJob::dispatch($jobRun->id, $newJobRun->id);

        return redirect()->back()->with('success', 'Job queued for re-run');
    }
}