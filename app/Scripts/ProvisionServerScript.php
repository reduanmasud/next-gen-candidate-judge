<?php

namespace App\Scripts;

class ProvisionServerScript extends Script
{
    public function __construct(
        protected string $ipAddress,
        protected string $sshUser,
        protected string $sshPassword,
    ) {
        //
    }

    public function name(): string
    {
        return 'Provision Server Script for ' . $this->ipAddress;
    }

    public function template(): string
    {
        return 'scrips.provision_server';
    }

    public function data(): array
    {
        return [
            'ipAddress' => $this->ipAddress,
            'sshUser' => $this->sshUser,
            'sshPassword' => $this->sshPassword,
        ];
    }
}

