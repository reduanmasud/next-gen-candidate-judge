<?php

namespace App\Services;

use App\Jobs\Scripts\Server\ProvisionServerJob;
use App\Jobs\Scripts\Server\SetupTraefikForServerJob;
use App\Models\Server;
use RuntimeException;
use App\Traits\AppendsNotes;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

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

        $cloudflareApiToken = env('CLOUDFLARE_API_TOKEN');

        // Dispatch job chain
        Bus::chain([
            new ProvisionServerJob($server, $sshUsername, $sshPassword),
            new SetupTraefikForServerJob($server, $cloudflareApiToken),
        ])->onQueue('default')->dispatch();

        $server->notes = $this->appendToNotes(
            $server->notes,
            sprintf("[%s] Server provisioning job chain dispatched", now()->toDateTimeString())
        );
        
        

        $server->notes = $this->appendToNotes(
            $server->notes,
            sprintf("[%s] Server provisioning job chain dispatched", now()->toDateTimeString())
        );

        Log::info('Server provisioning job chain dispatched', [
            'server_id' => $server->id,
            'ip_address' => $server->ip_address,
        ]);



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

