<?php

namespace App\Scripts;

class SetDockerComposeYamlScript extends Script
{
    public function __construct(
        protected string $username,
        protected string $workspacePath,
        protected string $dockerComposeYaml,
    ) {
        //
    }

    public function name(): string
    {
        return 'Set Docker Compose Yaml Script for user'. $this->username;
    }

    public function template(): string
    {
        return 'scrips.set_docker_compose_yaml';
    }

    public function data(): array
    {
        return [
            'workspacePath' => $this->workspacePath,
            'dockerComposeYaml' => $this->dockerComposeYaml,
        ];
    }
}
