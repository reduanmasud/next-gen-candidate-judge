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
    protected string $cloudflareEmail;
    protected string $cloudflareDomain;
    public function __construct(
  
    ) {
        $this->cloudflareEmail = env('CLOUDFLARE_EMAIL');
        $this->cloudflareDomain = env('CLOUDFLARE_DOMAIN');
    }

    public function provision(Server $server): void
    {
        $server->status = 'provisioning';
        $server->save();

        $cloudflareApiToken = env('CLOUDFLARE_API_TOKEN');

        if (empty($cloudflareApiToken)) {
            throw new \RuntimeException('Cloudflare API token is not configured. Please set CLOUDFLARE_API_TOKEN in your .env file.');
        }


        $job = [];

        $server->appendNote("Provisioning server");
        $job[] = new StartProvisioningJob($server->id);
        $job[] = new UpdateServerPackageJob($server->id);
        $job[] = new InstallNecesseryPackagesJob($server->id);
        $job[] = new InstallDockerJob($server->id);
        $job[] = new UpdateServerFirewallJob($server->id);
        $job[] = new InstallAndSetupTraefikJob($server->id, $cloudflareApiToken, $this->cloudflareEmail, $this->cloudflareDomain);
        // Dispatch job chain
        Bus::chain($job)->onQueue('default')->dispatch();

        $server->appendNote("Server provisioning job chain dispatched");
        $server->appendNote("Cloudflare API token: ********");




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

