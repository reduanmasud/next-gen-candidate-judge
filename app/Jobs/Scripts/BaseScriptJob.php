<?php

namespace App\Jobs\Scripts;

use App\Models\User;
use App\Services\ScriptWrapper;
use App\Traits\AppendsNotes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

/**
 * Base class for all script-related jobs.`
 */
abstract class BaseScriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use AppendsNotes;

    public $timeout = 900; // 15 minutes
    public $tries = 1;

    protected ScriptWrapper $wrapper;
    protected string $script;
    public ?User $authUser;

    public function __construct()
    {
        $this->authUser = Auth::user();
        $this->wrapper = new ScriptWrapper();
    }


    protected function getWrapper(): ScriptWrapper
    {
        if (!isset($this->wrapper)) {
            $this->wrapper = new ScriptWrapper();
        }
        return $this->wrapper;
    }

}
