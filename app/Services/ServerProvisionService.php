<?php

namespace App\Services;

use App\Models\Server;
use App\Scripts\ScriptDescriptor;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use App\Traits\AppendsNotes;

class ServerProvisionService
{
    use AppendsNotes;
    public function __construct(
        protected ScriptEngine $engine,
    ) {
        //
    }

    public function provision(Server $server, string $sshUsername, string $sshPassword): void
    {
        $server->status = 'provisioning';
        $server->save();

        try {
            $script = ScriptDescriptor::make('scrips.provision_server', [
                'ipAddress' => $server->ip_address,
                'sshUser' => $sshUsername,
                'sshPassword' => $sshPassword,
            ], 'Provision Server Script for ' . $server->ip_address);

            $result = $this->engine->executeViaStdin($script);

            $this->ensureSuccessful($result, 'provision server');

            $server->status = 'provisioned';
            $server->provisioned_at = now();
            $server->notes = $this->appendToNotes(
                $server->notes,
                sprintf(
                    "[%s] Provisioning completed.\nEXIT CODE: %s\nSTDOUT:\n%s\nSTDERR:\n%s\n",
                    now()->toDateTimeString(),
                    (string)($result['exit_code'] ?? '0'),
                    trim((string)($result['output'] ?? '')),
                    trim((string)($result['error_output'] ?? '')),
                )
            );
            $server->save();

            Log::info('Server provisioned successfully', [
                'server_id' => $server->id,
                'ip_address' => $server->ip_address,
            ]);
        } catch (Throwable $exception) {
            // Try to capture last process output if available
            $errorSummary = $exception->getMessage();
            $server->status = 'failed';
            $server->notes = $this->appendToNotes(
                $server->notes,
                sprintf("[%s] Provisioning failed: %s\n", now()->toDateTimeString(), $errorSummary)
            );
            $server->save();

            Log::error('Server provisioning failed', [
                'server_id' => $server->id,
                'ip_address' => $server->ip_address,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    protected function ensureSuccessful(array $result, string $context): void
    {
        if ($result['successful'] ?? false) {
            return;
        }

        $message = trim(($result['error_output'] ?? '') . ' ' . ($result['output'] ?? ''));
        $message = $message !== '' ? $message : 'Unknown error';

        throw new RuntimeException(sprintf('Failed to %s: %s', $context, $message));
    }

    // appendToNotes is provided by AppendsNotes trait
}

