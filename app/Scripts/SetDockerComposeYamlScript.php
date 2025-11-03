<?php

namespace App\Scripts;

class SetDockerComposeYamlScript extends Script
{
    public function __construct(
        protected string $dockerComposeYaml,
    ) {
        //
    }

    public function name(): string
    {
        return 'Set Docker Compose Yaml Script';
    }

    public function template(): string
    {
        return 'scripts.set_docker_compose_yaml';
    }

    public function data(): array
    {
        return [
            'dockerComposeYaml' => $this->dockerComposeYaml,
        ];
    }
}
