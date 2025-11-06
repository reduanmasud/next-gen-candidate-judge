<?php

namespace App\Scripts;

class StartDockerComposeScript extends Script
{
    public function __construct(
        protected string $workspacePath,
    ) {
        //
    }

    public function name(): string
    {
        return 'Start Docker Compose Script';
    }

    public function template(): string
    {
        return 'scrips.start_docker_compose';
    }

    public function data(): array
    {
        return [
            'workspacePath' => $this->workspacePath,
        ];
    }
}
