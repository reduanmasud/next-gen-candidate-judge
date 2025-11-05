<?php

namespace App\Scripts;

class RemoteStartDockerComposeScript extends Script
{
    public function __construct(
        protected string $ipAddress,
        protected string $sshUser,
        protected string $sshPassword,
        protected string $workspacePath,
        protected string $projectName,
    ) {
        //
    }

    public function name(): string
    {
        return 'Remote Start Docker Compose for ' . $this->projectName;
    }

    public function template(): string
    {
        return 'scrips.remote_start_docker_compose';
    }

    public function data(): array
    {
        return [
            'ipAddress' => $this->ipAddress,
            'sshUser' => $this->sshUser,
            'sshPassword' => $this->sshPassword,
            'workspacePath' => $this->workspacePath,
            'projectName' => $this->projectName,
        ];
    }
}


