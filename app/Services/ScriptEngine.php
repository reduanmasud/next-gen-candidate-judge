<?php

namespace App\Services;

use App\Models\Server;
use App\Scripts\Script;
use App\Scripts\ScriptDescriptor;
use Symfony\Component\Process\Process;

class ScriptEngine
{
    private $key = 'your-secret-key';

    private $timeout = 1200;

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
    /**
     * Execute a script. Accepts either an App\Scripts\Script instance or a ScriptDescriptor.
     */
    public function execute(Script|ScriptDescriptor $script): array
    {

        if ($script instanceof ScriptDescriptor) {
            $rendered = view($script->template, $script->data)->render();
        } else {
            $rendered = view($script->template(), $script->data())->render();
        }

        $wrappedScript = $this->wrapper->wrap($rendered);

        $tmpFile = tempnam(sys_get_temp_dir(), 'script_').'.sh';
        file_put_contents($tmpFile, $wrappedScript);
        chmod($tmpFile, 0777);

        try {

            $process = new Process(['bash', $tmpFile]);
            $process->setTimeout($this->timeout);
            $process->run();

        } finally {
            @unlink($tmpFile);
        }

        return [
            'script' => $wrappedScript,
            'output' => $process->getOutput(),
            'error_output' => $process->getErrorOutput(),
            'exit_code' => $process->getExitCode(),
            'successful' => $process->isSuccessful(),
        ];
    }

    /**
     * Execute a script by streaming it to bash via STDIN. Useful for SSH/here-doc heavy scripts.
     */
    /**
     * Execute a script by streaming it to bash via STDIN. Accepts Script or ScriptDescriptor.
     */
    public function executeViaStdin(Script|ScriptDescriptor $script, int $timeoutSeconds = 900): array
    {
        if (! $this->server) {
            throw new \RuntimeException('Server is not set. Call setServer() before executing remote scripts.');
        }

        if ($script instanceof ScriptDescriptor) {
            $rendered = view($script->template, $script->data)->render();
        } else {
            $rendered = view($script->template(), $script->data())->render();
        }

        $wrappedScript = $this->wrapper->wrap($rendered);

        $file_random_name = bin2hex(random_bytes(16));

        // Need generate script file and encrypt it
        $scriptFile = sys_get_temp_dir().'/'.$file_random_name.'.sh';
        file_put_contents($scriptFile, $wrappedScript);
        chmod($scriptFile, 0777);

        $encryptedScriptFile = sys_get_temp_dir().'/'.$file_random_name.'.enc';
        $this->encryptFile($scriptFile, $encryptedScriptFile);

        $this->scpUpload($this->server->ip_address, $this->server->ssh_username, $this->server->ssh_password, $encryptedScriptFile, '/tmp/'.$file_random_name.'.enc');
        @unlink($scriptFile);
        @unlink($encryptedScriptFile);

        $cmd = <<<BASH
        cd /tmp &&\
        openssl enc -d -aes-256-cbc -pbkdf2 -iter 100 -in {$file_random_name}.enc -out {$file_random_name}.sh -k {$this->key} &&\
        chmod +x {$file_random_name}.sh &&\
        ./{$file_random_name}.sh &&\
        rm -f {$file_random_name}.sh &&\
        rm -f {$file_random_name}.enc
        BASH;

        return [
            'script' => $wrappedScript,
            ...$this->sshRun($this->server->ip_address, $this->server->ssh_username, $this->server->ssh_password, $cmd),
        ];
    }

    private function encryptFile(string $inputFile, string $outputFile): void
    {
        $cmd = "openssl enc -aes-256-cbc -pbkdf2 -iter 100 -in $inputFile -out $outputFile -k $this->key";
        $process = Process::fromShellCommandline($cmd);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Encryption failed: '.$process->getErrorOutput());
        }

    }

    public function sshRun(string $host, string $user, string $password, string $remoteCommand): array
    {
        $cmd = sprintf(
            'sshpass -p %s ssh -o StrictHostKeyChecking=no %s@%s %s',
            escapeshellarg($password),
            escapeshellarg($user),
            escapeshellarg($host),
            escapeshellarg($remoteCommand)
        );

        $process = Process::fromShellCommandline($cmd);
        $process->setTimeout($this->timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('SSH command failed: '.$process->getErrorOutput());
        }

        return [
            'output' => $process->getOutput(),
            'error_output' => $process->getErrorOutput(),
            'exit_code' => $process->getExitCode(),
            'successful' => $process->isSuccessful(),
        ];
    }

    private function scpUpload(string $host, string $user, string $password, string $localFile, string $remoteFile): array
    {
        $cmd = sprintf(
            'sshpass -p %s scp -o StrictHostKeyChecking=no %s %s@%s:%s',
            escapeshellarg($password),
            escapeshellarg($localFile),
            escapeshellarg($user),
            escapeshellarg($host),
            escapeshellarg($remoteFile)
        );

        $process = Process::fromShellCommandline($cmd);
        $process->setTimeout($this->timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('SCP upload failed: '.$process->getErrorOutput());
        }

        return [
            'output' => $process->getOutput(),
            'error_output' => $process->getErrorOutput(),
            'exit_code' => $process->getExitCode(),
            'successful' => $process->isSuccessful(),
        ];

    }
}
