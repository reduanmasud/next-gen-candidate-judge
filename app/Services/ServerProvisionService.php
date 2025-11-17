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
use Illuminate\Support\Facades\Bus;

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
        $cloudflareApiToken = env('CLOUDFLARE_API_TOKEN');

        if (empty($cloudflareApiToken)) {
            throw new \RuntimeException('Cloudflare API token is not configured. Please set CLOUDFLARE_API_TOKEN in your .env file.');
        }


        $jobs = [];

        $server->appendNote("Preparing server provisioning");
        $jobs[] = new StartProvisioningJob($server->id);
        $jobs[] = new UpdateServerPackageJob($server->id);
        $jobs[] = new InstallNecesseryPackagesJob($server->id);
        $jobs[] = new InstallDockerJob($server->id);
        $jobs[] = new UpdateServerFirewallJob($server->id);
        $jobs[] = new InstallAndSetupTraefikJob($server->id, $cloudflareApiToken, $this->cloudflareEmail, $this->cloudflareDomain);

        $server->initializeWorkflowFromJobs(
            jobs: $jobs,
            workflowType: 'server_provisioning',
            workflowName: 'Server Provisioning'
        );

        // Dispatch job chain
        $server->appendNote("Dispatching job chain");
        Bus::chain($jobs)->onQueue('default')->dispatch();

        $server->appendNote("Server provisioning job chain dispatched.");
    }
}

