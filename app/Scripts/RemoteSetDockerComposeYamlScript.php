<?php

namespace App\Scripts;

class RemoteSetDockerComposeYamlScript extends Script
{
    public function __construct(
        protected string $ipAddress,
        protected string $sshUser,
        protected string $sshPassword,
        protected string $workspacePath,
        protected string $dockerComposeYaml,
    ) {
        //
    }

    public function name(): string
    {
        return 'Remote Set Docker Compose YAML for ' . $this->ipAddress;
    }

    public function template(): string
    {
        return 'scrips.remote_set_docker_compose_yaml';
    }

    public function data(): array
    {
        return [
            'ipAddress' => $this->ipAddress,
            'sshUser' => $this->sshUser,
            'sshPassword' => $this->sshPassword,
            'workspacePath' => $this->workspacePath,
            'dockerComposeYaml' => $this->dockerComposeYaml,
        ];
    }
}


