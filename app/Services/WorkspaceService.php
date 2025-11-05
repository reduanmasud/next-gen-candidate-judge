<?php

namespace App\Services;

use App\Models\Server;
use App\Models\Task;
use App\Models\User;
use App\Models\UserTaskAttempt;
use App\Scripts\CreateUserScript;
use App\Scripts\SetDockerComposeYamlScript;
use App\Scripts\StartDockerComposeScript;
use App\Scripts\RemoteSetDockerComposeYamlScript;
use App\Scripts\RemoteStartDockerComposeScript;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WorkspaceService
{
    public function __construct(
        protected ScriptEngine $engine,
    ) {
        //
    }

    public function setServer(?Server $server): void
    {
        $this->engine->setServer($server);
    }

    public function start(Task $task, User $user): UserTaskAttempt
    {
        $attempt = new UserTaskAttempt([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'status' => 'pending',
        ]);

        $attempt->save();

        $config = config('services.workspace');
        $mode = $config['mode'] ?? 'system';
        $basePath = rtrim($config['base_path'] ?? '/home', '/');

        $username = $this->makeLinuxUsername($attempt->id);
        $password = null;
        $projectName = $this->makeComposeProjectName($task->id, $attempt->id);
        $workspacePath = $this->resolveWorkspacePath($mode, $basePath, $username);

        try {
            if ($task->server) {
                // Remote mode
                $server = $task->server;
                $sshUser = $server->ssh_username ?: 'root';
                $sshPass = (string) ($server->ssh_password ?? '');

                if ($sshPass === '') {
                    throw new RuntimeException('Remote server SSH password is missing. Edit the server and set ssh_password.');
                }

                // Use a user-writable workspace path to avoid sudo
                $workspacePath = $sshUser === 'root'
                    ? '/opt/workspaces/' . $projectName
                    : '/home/' . $sshUser . '/workspaces/' . $projectName;

                $this->ensureSuccessful(
                    $this->engine->executeViaStdin(new RemoteSetDockerComposeYamlScript(
                        $server->ip_address,
                        $sshUser,
                        $sshPass,
                        $workspacePath,
                        $task->docker_compose_yaml,
                    )),
                    'write remote docker-compose.yaml'
                );

                $startResult = $this->engine->executeViaStdin(new RemoteStartDockerComposeScript(
                    $server->ip_address,
                    $sshUser,
                    $sshPass,
                    $workspacePath,
                    $projectName,
                ));
                $this->ensureSuccessful($startResult, 'start remote docker compose');
            } elseif ($mode === 'system') {
                $password = $this->generatePassword();

                $this->ensureSuccessful(
                    $this->engine->execute(new CreateUserScript($username, $password)),
                    'create workspace user'
                );
            } else {
                File::ensureDirectoryExists($workspacePath);
                File::ensureDirectoryExists($workspacePath . '/html');
            }

            if (! $task->server) {
                $this->ensureSuccessful(
                    $this->engine->execute(new SetDockerComposeYamlScript($workspacePath, $task->docker_compose_yaml)),
                    'write docker-compose.yaml'
                );

                $startResult = $this->engine->execute(new StartDockerComposeScript($workspacePath, $projectName));
                $this->ensureSuccessful($startResult, 'start docker compose');
            }

            $containers = $this->parseDockerComposeOutput($startResult['output']);

            if (empty($containers)) {
                throw new RuntimeException('Docker compose started but no container information was returned.');
            }

            $primaryContainer = $containers[0];
            $publishedPort = $this->extractPublishedPort($primaryContainer);

            $notesData = [
                'workspace_mode' => $mode,
                'workspace_path' => $workspacePath,
                'compose_project' => $projectName,
                'containers' => $containers,
            ];

            if ($mode === 'system') {
                $notesData['workspace_username'] = $username;
                $notesData['workspace_password'] = $password;
            }

            $attempt->fill([
                'status' => 'running',
                'container_id' => Arr::get($primaryContainer, 'ID'),
                'container_name' => Arr::get($primaryContainer, 'Name'),
                'container_port' => $publishedPort,
                'started_at' => now(),
                'notes' => json_encode($notesData),
            ]);

            $attempt->save();

            return $attempt;
        } catch (Throwable $exception) {
            $attempt->status = 'failed';
            $attempt->notes = $this->appendToNotes($attempt->notes, $exception->getMessage());
            $attempt->save();

            Log::error('Workspace provisioning failed', [
                'task_id' => $task->id,
                'user_id' => $user->id,
                'attempt_id' => $attempt->id,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
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

    protected function parseDockerComposeOutput(string $output): array
    {
        if (! preg_match('/__DOCKER_PS_START__(.*?)__DOCKER_PS_END__/s', $output, $matches)) {
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

        if (! is_array($publishers)) {
            return null;
        }

        foreach ($publishers as $publisher) {
            if (isset($publisher['PublishedPort'])) {
                return (int) $publisher['PublishedPort'];
            }
        }

        return null;
    }

    protected function makeLinuxUsername(int $attemptId): string
    {
        return 'wsu' . $attemptId;
    }

    protected function makeComposeProjectName(int $taskId, int $attemptId): string
    {
        return sprintf('task_%d_attempt_%d', $taskId, $attemptId);
    }

    protected function resolveWorkspacePath(string $mode, string $basePath, string $username): string
    {
        if ($mode === 'system') {
            return sprintf('/home/%s/workspace', $username);
        }

        return rtrim($basePath, '/') . '/' . $username;
    }

    protected function generatePassword(): string
    {
        return Str::random(16);
    }

    protected function appendToNotes(?string $existing, string $message): string
    {
        $existing = $existing ? trim($existing) . "\n" : '';

        return $existing . $message;
    }
}
