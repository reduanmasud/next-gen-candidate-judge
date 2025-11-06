<?php

namespace App\Jobs\Scripts;

use App\Jobs\Scripts\Concerns\HandlesScriptExecution;
use App\Models\ScriptJobRun;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base class for all script-related jobs.
 *
 * Provides common traits, default timeout/tries values, and a shared failed() 
 * implementation to reduce duplication across script jobs.
 */
abstract class BaseScriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HandlesScriptExecution;

    /**
     * The number of seconds the job can run before timing out.
     * Child classes may override this value.
     *
     * @var int
     */
    public $timeout = 900; // 15 minutes

    /**
     * The number of times the job may be attempted.
     * Child classes may override this value.
     *
     * @var int
     */
    public $tries = 1;


    /**
     * The user to associate with the job run.
     * Child classes may set this value in their constructor.
     *
     * @var User|null
     */
    public ?User $authUser = null;

    public function __construct()
    {
        $this->authUser = auth()->user();
    }

    /**
     * Default failed handler for script jobs.
     * 
     * Attempts to find and update the associated ScriptJobRun record if it exists,
     * and logs the error. Child classes can override this method for custom behavior.
     *
     * @param Throwable $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        try {
            // Try to find the most recent ScriptJobRun associated with this job
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
            // Swallow - we don't want failed() to throw and break the queue infrastructure
            Log::error('Error while running failed() handler on script job', [
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

    /**
     * Attempt to find the ScriptJobRun associated with this job.
     * 
     * Child classes can override this method to provide custom logic
     * for finding the associated ScriptJobRun.
     *
     * @return ScriptJobRun|null
     */
    protected function findAssociatedScriptJobRun(): ?ScriptJobRun
    {
        // Check if the job has a direct reference to a ScriptJobRun
        if (property_exists($this, 'jobRun') && $this->jobRun instanceof ScriptJobRun) {
            return $this->jobRun;
        }

        // Check if the job has a server property and find the most recent running job
        if (property_exists($this, 'server') && $this->server) {
            return ScriptJobRun::where('server_id', $this->server->id)
                ->where('status', 'running')
                ->latest('started_at')
                ->first();
        }

        // Check if the job has an attempt property and find via attempt_id
        if (property_exists($this, 'attempt') && $this->attempt) {
            return ScriptJobRun::where('attempt_id', $this->attempt->id)
                ->where('status', 'running')
                ->latest('started_at')
                ->first();
        }

        return null;
    }

    /**
     * Append a message to existing error output.
     *
     * @param string|null $existing
     * @param string $message
     * @return string
     */
    protected function appendToErrorOutput(?string $existing, string $message): string
    {
        if (!$existing) {
            return $message;
        }

        return trim($existing) . "\n" . $message;
    }
}

