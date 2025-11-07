<?php

namespace App\Services;

use App\Jobs\Scripts\Workspace\CreateUserJob;
use App\Jobs\Scripts\Workspace\FinalizeWorkspaceJob;
use App\Jobs\Scripts\Workspace\SetupCaddyServerJob;
use App\Jobs\Scripts\Workspace\SetDockerComposeJob;
use App\Jobs\Scripts\Workspace\StartDockerComposeJob;
use App\Models\Task;
use App\Models\User;
use App\Models\UserTaskAttempt;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use App\Traits\AppendsNotes;

class WorkspaceService
{
    use AppendsNotes;
    // No longer need ScriptEngine dependency since jobs handle execution
    public function __construct()
    {
        //
    }

    public function start(Task $task, User $user): UserTaskAttempt
    {
        // Create the attempt record
        $attempt = new UserTaskAttempt([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'status' => 'pending',
        ]);
        $attempt->save();

        // Generate credentials
        $username = "user_{$attempt->id}";
        $password = $this->generatePassword();
        $workspacePath = '/home/' . $username . '/workspace_' . $attempt->id;

        $attempt_name = bin2hex(random_bytes(4));
        $domain = 'wpqa.online';

        // Update attempt with initial notes
        $attempt->notes = $this->appendToNotes(
            $attempt->notes,
            sprintf("[%s] Started workspace attempt", now()->toDateTimeString())
        );
        $attempt->notes = $this->appendToNotes(
            $attempt->notes,
            sprintf("[%s] Workspace username: %s", now()->toDateTimeString(), $username)
        );
        $attempt->notes = $this->appendToNotes(
            $attempt->notes,
            sprintf("[%s] Workspace password: %s", now()->toDateTimeString(), $password)
        );

        $attempt->notes = $this->appendToNotes(
            $attempt->notes,
            sprintf("[%s] Workspace domain: %s", now()->toDateTimeString(), $domain)
        );
        $attempt->notes = $this->appendToNotes(
            $attempt->notes,
            sprintf("[%s] Workspace attempt name: %s", now()->toDateTimeString(), $attempt_name)
        );
        $attempt->save();

        // Validate server configuration
        $server = $task->server;
        if (!$server) {
            $attempt->update([
                'status' => 'failed',
                'completed_at' => now(),
                'notes' => $this->appendToNotes(
                    $attempt->notes,
                    sprintf("[%s] No server assigned to task", now()->toDateTimeString())
                ),
            ]);
            throw new RuntimeException('No server assigned to task');
        }



        // Dispatch job chain
        Bus::chain([
            new CreateUserJob($attempt, $server, $username, $password, $workspacePath),
            new SetDockerComposeJob($attempt, $server, $username, $workspacePath, $this->yamlFillWithData($task->docker_compose_yaml, [
                "attempt_name" => $attempt_name,
                "domain" => $domain
            ])),
            new StartDockerComposeJob($attempt, $server, $workspacePath),
            new FinalizeWorkspaceJob($attempt),
        ])->onQueue('default')->dispatch();


        return $attempt;
    }



    protected function generatePassword(): string
    {
        return Str::random(16);
    }

    protected function yamlFillWithData(string $yaml, array $data): string
    {

        preg_match_all('/{{(\w+)}}/', $yaml, $matches);
        $placeholders = $matches[1];

        // If no placeholders found, return YAML as-is
        if (empty($matches[1])) {
            return $yaml;
        }

        foreach ($placeholders as $key) {
            if (!array_key_exists($key, $data)) {
                throw new \InvalidArgumentException("Missing value for placeholder: {{$key}}");
            }
            $yaml = str_replace('{{'.$key.'}}', $data[$key], $yaml);
        }

        return $yaml;
    }


    // appendToNotes provided by AppendsNotes trait
}
