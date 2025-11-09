<?php

namespace App\Services;

use App\Jobs\Scripts\Server\InstallAndSetupTraefikJob;
use App\Jobs\Scripts\Server\InstallDockerJob;
use App\Jobs\Scripts\Server\InstallNecesseryPackagesJob;
use App\Jobs\Scripts\Server\StartProvisioningJob;
use App\Jobs\Scripts\Server\UpdateServerFirewallJob;
use App\Jobs\Scripts\Server\UpdateServerPackageJob;
use App\Models\Server;
use RuntimeException;
use App\Traits\AppendsNotes;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class ServerProvisionService
{
    use AppendsNotes;
    public function __construct(
  
    ) {
        //
    }

    public function provision(Server $server): void
    {
        $server->status = 'provisioning';
        $server->save();

        $cloudflareApiToken = env('CLOUDFLARE_API_TOKEN');

        // Dispatch job chain
        Bus::chain([
            new StartProvisioningJob($server),
            new UpdateServerPackageJob($server),
            new InstallNecesseryPackagesJob($server),
            new InstallDockerJob($server),
            new UpdateServerFirewallJob($server),
            new InstallAndSetupTraefikJob($server, $cloudflareApiToken),

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

