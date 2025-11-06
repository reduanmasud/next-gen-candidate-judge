<?php

namespace App\Services;

use App\Jobs\Workspace\CreateUserJob;
use App\Jobs\Workspace\FinalizeWorkspaceJob;
use App\Jobs\Workspace\SetDockerComposeJob;
use App\Jobs\Workspace\StartDockerComposeJob;
use App\Models\Task;
use App\Models\User;
use App\Models\UserTaskAttempt;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class WorkspaceService
{
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
        $workspacePath = '/home/' . $username . '/workspace';

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

        $sshPass = (string) ($server->ssh_password ?? '');
        if ($sshPass === '') {
            $attempt->update([
                'status' => 'failed',
                'completed_at' => now(),
                'notes' => $this->appendToNotes(
                    $attempt->notes,
                    sprintf("[%s] Remote server SSH password is missing", now()->toDateTimeString())
                ),
            ]);
            throw new RuntimeException('Remote server SSH password is missing. Edit the server and set ssh_password.');
        }

        // Dispatch job chain
        Bus::chain([
            new CreateUserJob($attempt, $server, $username, $password),
            new SetDockerComposeJob($attempt, $server, $username, $workspacePath, $task->docker_compose_yaml),
            new StartDockerComposeJob($attempt, $server, $workspacePath),
            new FinalizeWorkspaceJob($attempt),
        ])->onQueue('workspace')->dispatch();

        Log::info('Workspace provisioning job chain dispatched', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'attempt_id' => $attempt->id,
            'username' => $username,
        ]);

        return $attempt;
    }



    protected function generatePassword(): string
    {
        return Str::random(16);
    }

    protected function appendToNotes(?string $existing, string $message): string
    {
        $existing = $existing ? trim($existing)."\n" : '';

        return $existing.$message;
    }
}
