<?php

namespace App\Services;

use App\Scripts\Script;
use Symfony\Component\Process\Process;

class ScriptEngine
{
    public function __construct(
        protected ScriptWrapper $wrapper,
    ) {
        //
    }

    /**
     * Execute a script and return the output.
     */
    public function execute(Script $script): array
    {

        $script = view($script->template(), $script->data())->render();

        $wrappedScript = $this->wrapper->wrap($script);

        $tmpFile = tempnam(sys_get_temp_dir(), 'script_') . '.sh';
        file_put_contents($tmpFile, $wrappedScript);
        chmod($tmpFile, 0777);

        try {

            $process = new Process(['bash', $tmpFile]);
            $process->setTimeout(300);
            $process->run();

        } finally {
            @unlink($tmpFile);
        }

        return [
            'output' => $process->getOutput(),
            'error_output' => $process->getErrorOutput(),
            'exit_code' => $process->getExitCode(),
            'successful' => $process->isSuccessful(),
        ];
    }

    /**
     * Execute a script by streaming it to bash via STDIN. Useful for SSH/here-doc heavy scripts.
     */
    public function executeViaStdin(Script $script, int $timeoutSeconds = 900): array
    {
        $script = view($script->template(), $script->data())->render();
        $wrappedScript = $this->wrapper->wrap($script);

        $process = Process::fromShellCommandline('bash -s');
        $process->setTimeout($timeoutSeconds);
        $process->setInput($wrappedScript);
        $process->run();

        return [
            'output' => $process->getOutput(),
            'error_output' => $process->getErrorOutput(),
            'exit_code' => $process->getExitCode(),
            'successful' => $process->isSuccessful(),
        ];
    }
}
