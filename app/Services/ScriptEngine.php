<?php

namespace App\Services;

use App\Models\Server;
use App\Scripts\Script;
use Symfony\Component\Process\Process;

class ScriptEngine
{

    private $key = 'your-secret-key';

    public function __construct(
        protected ScriptWrapper $wrapper,
        protected ?Server $server = null
    ) {

        //
    }

    public function setServer(?Server $server): void
    {
        $this->server = $server;
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
        if (!$this->server) {
            throw new \RuntimeException('Server is not set. Call setServer() before executing remote scripts.');
        }

        $script = view($script->template(), $script->data())->render();
        $wrappedScript = $this->wrapper->wrap($script);

        $file_random_name = bin2hex(random_bytes(16));

        // Need generate script file and encrypt it
        $scriptFile = sys_get_temp_dir() . '/' . $file_random_name . '.sh';
        file_put_contents($scriptFile, $wrappedScript);
        chmod($scriptFile, 0777);
        $encryptedScriptFile = sys_get_temp_dir() . '/' . $file_random_name . '.enc';
        $this->encryptFile($scriptFile, $encryptedScriptFile);

        $this->scpUpload($this->server->ip_address, $this->server->ssh_username, $this->server->ssh_password, $encryptedScriptFile, '/tmp/' . $file_random_name . '.enc');

        $this->sshRun($this->server->ip_address, $this->server->ssh_username, $this->server->ssh_password, 'cd /tmp && openssl enc -d -aes-256-cbc -in ' . $file_random_name . '.enc -out ' . $file_random_name . '.sh -k'.$this->key.' && chmod +x ' . $file_random_name . '.sh && ./' . $file_random_name . '.sh && rm -f ' . $file_random_name . '.sh && rm -f ' . $file_random_name . '.enc');

        @unlink($scriptFile);
        @unlink($encryptedScriptFile);

        return [
            'output' => $process->getOutput(),
            'error_output' => $process->getErrorOutput(),
            'exit_code' => $process->getExitCode(),
            'successful' => $process->isSuccessful(),
        ];
    }

    private function encryptFile(string $inputFile, string $outputFile): void
    {
        $cmd = "openssl enc -aes-256-cbc -in $inputFile -out $outputFile -k $this->key";
        $process = Process::fromShellCommandline($cmd);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("Encryption failed: " . $process->getErrorOutput());
        }
        return;
    }
    

    function sshRun(string $host, string $user, string $password, string $remoteCommand): string
    {
        $cmd = sprintf(
            "sshpass -p %s ssh -o StrictHostKeyChecking=no %s@%s %s",
            escapeshellarg($password),
            escapeshellarg($user),
            escapeshellarg($host),
            escapeshellarg($remoteCommand)
        );

        $process = Process::fromShellCommandline($cmd);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("SSH command failed: " . $process->getErrorOutput());
        }

        return [
            'output' => $process->getOutput(),
            'error_output' => $process->getErrorOutput(),
            'exit_code' => $process->getExitCode(),
            'successful' => $process->isSuccessful(),
        ];
    }

    private function scpUpload(string $host, string $user, string $password, string $localFile, string $remoteFile):string
    {        

        // $test = new Process(['whoami']);
        // $test->run();

        // dd($test->getOutput());
        $cmd = sprintf(
            "sshpass -p %s scp -o StrictHostKeyChecking=no %s %s@%s:%s",
            escapeshellarg($password),
            escapeshellarg($localFile),
            escapeshellarg($user),
            escapeshellarg($host),
            escapeshellarg($remoteFile)
        );

        $process = Process::fromShellCommandline($cmd);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("SCP upload failed: " . $process->getErrorOutput());
        }

        return [
            'output' => $process->getOutput(),
            'error_output' => $process->getErrorOutput(),
            'exit_code' => $process->getExitCode(),
            'successful' => $process->isSuccessful(),
        ];
        
    }
}
