<?php

namespace App\Services;

use App\Jobs\CloseUserTaskAttemptJob;
use App\Jobs\Scripts\Server\FindFreePort;
use App\Jobs\Scripts\Workspace\CreateUserJob;
use App\Jobs\Scripts\Workspace\FinalizeWorkspaceJob;
use App\Jobs\Scripts\Workspace\SetDockerComposeJob;
use App\Jobs\Scripts\Workspace\SetSshAccessToContainerJob;
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

        $attempt_randorm_uid = bin2hex(random_bytes(4));

        if($task->sandbox)
        {
            // Generate Credentials
            $hostUsername = "user_".$attempt->id;
            $hostPassword = $this->generatePassword();

            $workspacePath = '/home/' . $hostUsername . '/workspace_'. $attempt->id;

            $domain = 'wpqa.online'; // TODO: Later send it to Server Model

            // TODO: Need to create a trait for append Note into a model

            $attempt->notes = $this->appendToNotes(
                $attempt->notes,
                sprintf("[%s] Started workspace attempt", now()->toDateTimeString())
            );

            $attempt->notes = $this->appendToNotes(
                $attempt->notes,
                sprintf("[%s] Workspace username: %s", now()->toDateTimeString(), $hostUsername)
            );

            $attempt->notes = $this->appendToNotes(
                $attempt->notes,
                sprintf("[%s] Workspace password: %s", now()->toDateTimeString(), $hostPassword)
            );

            $attempt->notes = $this->appendToNotes(
                $attempt->notes,
                sprintf("[%s] Workspace path: %s", now()->toDateTimeString(), $workspacePath)
            );

            $attempt->notes = $this->appendToNotes(
                $attempt->notes,
                sprintf("[%s] Workspace domain: %s.%s", now()->toDateTimeString(), $attempt_randorm_uid, $domain)
            );

            $attempt->save();

            $accessPassword = $this->generatePassword();

            $attempt->addMeta([
                'workspace_domain' => $attempt_randorm_uid . '.' . $domain,
                "domain" => $domain,
                "ssh_port" => $attempt->getMeta('ssh_port'),
                "access_user" => "candidate",
                "access_password" => $accessPassword,
                "workspace_path" => $workspacePath,

            ]);
            $attempt->save();

            if (!$attempt->task->server) {
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

            $jobs = [];
            $jobs[] = new CreateUserJob($attempt, $attempt->task->server, $hostUsername, $hostPassword, $workspacePath);
            $jobs[] = new FindFreePort($attempt->task->server->id, $attempt->id);
            $jobs[] = new SetDockerComposeJob($attempt->id, $this->yamlFillWithData($task->docker_compose_yaml, [
                    "attempt_name" => $attempt_randorm_uid,
                    "domain" => $attempt->getMeta('domain'),
                    "ssh_port" => $attempt->getMeta('ssh_port'),
                    "access_user" => "candidate",
                    "access_password" => $accessPassword,
                    "workspace_path" => $workspacePath,
                ]));
            $jobs[] = new StartDockerComposeJob($attempt->id, $attempt->task->server->id, $workspacePath);
            
            if($attempt->task->allowssh)
            {
                $jobs[] = new SetSshAccessToContainerJob($attempt->id, $attempt->task->server->id);
            }

            $jobs[] = new FinalizeWorkspaceJob($attempt->id);



            Bus::chain($jobs)->onQueue('default')->dispatch();
        }

        $attempt->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        if($task->timer > 0)
        {

            CloseUserTaskAttemptJob::dispatch($attempt)->delay(now()->addMinutes($task->timer));


            $attempt->addMeta([
                'timer' => $task->timer,
                'warrning_timer' => $task->warrning_timer,
                'warning_timer_sound' => $task->warning_timer_sound,
            ]);
        }
        $attempt->save();
                

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
