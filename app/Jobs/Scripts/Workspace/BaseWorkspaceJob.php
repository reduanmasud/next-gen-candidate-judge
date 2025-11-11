<?php

namespace App\Jobs\Scripts\Workspace;

use App\Jobs\Scripts\BaseScriptJob;
use App\Models\UserTaskAttempt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base class for workspace-related jobs.
 *
 * Extends BaseScriptJob and adds workspace-specific failed() handling
 * that updates the associated UserTaskAttempt record.
 */
abstract class BaseWorkspaceJob extends BaseScriptJob
{


    function __construct()
    {
        parent::__construct();
    }


}
