<?php

namespace App\Jobs\Scripts\Workspace;

use App\Contracts\TracksProgressInterface;
use App\Enums\AttemptTaskStatus;
use App\Models\ScriptJobRun;
use App\Models\Server;
use App\Models\UserTaskAttempt;
use App\Scripts\ScriptDescriptor;
use App\Services\ScriptEngine;
use Illuminate\Support\Arr;
use Throwable;

class StartDockerComposeJob extends BaseWorkspaceJob
{

    public UserTaskAttempt $attempt;
    public Server $server;

    public function __construct(
        public Int $attemptId,
        public Int $serverId,
    ) {
        parent::__construct();
    }
    public static function getStepMetadata(): array
    {
        return [
            'id' => 'starting_docker_compose',
            'label' => 'Starting Docker Compose',
            'description' => 'Starting docker-compose.yaml for workspace',
            'icon' => 'docker',
            'estimatedDuration' => 9,
        ];
    }
    public function getTrackableModel(): TracksProgressInterface
    {
        if(!isset($this->attempt)) {
            $this->attempt = UserTaskAttempt::find($this->attemptId);
        }
        return $this->attempt;
    }

    protected function execute(): void
    {
        $this->attempt = UserTaskAttempt::find($this->attemptId);
        $this->server = Server::find($this->serverId);


        $script = ScriptDescriptor::make(
            'scripts.start_docker_compose',
            [
                'workspace_path' => $this->attempt->getMeta('workspace_path'),
                'task' => $this->attempt->task,
            ],
            'Start Docker Compose Script'
        );

        $this->attempt->appendNote("Starting docker compose for workspace");

        [$this->jobRun, $result] = ScriptJobRun::createAndExecute(
            script: $script,
            engine: app(ScriptEngine::class),
            attempt: $this->attempt,
            server: $this->server,
            metadata: [
                'workspace_path' => $this->attempt->getMeta('workspace_path'),
                'attempt_id' => $this->attempt->id,
            ]
        );

        $this->attempt->appendNote("Started docker compose for workspace");
        // Parse docker compose output to get container info
        $containers = $this->parseDockerComposeOutput($result['output'] ?? '');
        $primaryContainer = $containers[0] ?? null;
        $publishedPort = $primaryContainer ? $this->extractPublishedPort($primaryContainer) : null;

        $this->attempt->appendNote("Parsed docker compose output for workspace");
        $this->attempt->appendNote("Primary container: ".json_encode($primaryContainer));

        // Update attempt with container information
        $this->attempt->update([
            'container_id' => Arr::get($primaryContainer, 'ID'),
            'container_name' => Arr::get($primaryContainer, 'Name'),
            'container_port' => $publishedPort,
        ]);

        $this->attempt->appendNote("Updated attempt with container information");
        $this->attempt->appendNote("Docker compose started successfully");


        // Update job run metadata using HasMeta trait
        $this->jobRun->addMeta([
            'containers' => $containers,
            'primary_container' => $primaryContainer,
            'primary_container_name' => Arr::get($primaryContainer, 'Name'),
        ]);


        // Update attempt metadata using HasMeta trait
        $this->attempt->addMeta([
            'containers' => $containers,
            'primary_container' => $primaryContainer,
            'primary_container_name' => Arr::get($primaryContainer, 'Name'),
        ]);
    }

    protected function failed(Throwable $exception): void
    {
        $this->attempt->appendNote("Failed to start docker compose: ".$exception->getMessage());
        $this->jobRun->update([
            'status' => 'failed',
            'error_output' => "Failed to start docker compose: " . $exception->getMessage(),
            'failed_at' => now(),
            'completed_at' => now(),
        ]);
        $this->attempt->update([
            'status' => AttemptTaskStatus::FAILED,
            'failed_at' => now(),
        ]);
    }


    protected function parseDockerComposeOutput(string $output): array
    {
        if (!preg_match('/__DOCKER_PS_START__(.*?)__DOCKER_PS_END__/s', $output, $matches)) {
            return [];
        }

        $lines = preg_split('/\r?\n/', trim($matches[1]));
        $containers = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $containers[] = $decoded;
            }
        }

        return $containers;
    }

    protected function extractPublishedPort(array $container): ?int
    {
        $publishers = Arr::get($container, 'Publishers');

        if (!is_array($publishers)) {
            return null;
        }

        foreach ($publishers as $publisher) {
            if (isset($publisher['PublishedPort'])) {
                return (int) $publisher['PublishedPort'];
            }
        }

        return null;
    }
}

