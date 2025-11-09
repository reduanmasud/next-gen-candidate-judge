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
use Illuminate\Support\Str;
use RuntimeException;

class WorkspaceService
{
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

            $attempt->addMeta([
                'username' => $hostUsername,
                'password' => $hostPassword,
                'ssh_port' => 22,

            ]);

            $workspacePath = '/home/' . $hostUsername . '/workspace_'. $attempt->id;

            $domain = 'wpqa.online'; // TODO: Later send it to Server Model

            $attempt->addMeta([
                'domain' => $attempt_randorm_uid.'.'.$domain,
                'workspace_path' => $workspacePath,
            ]);

            $attempt->addMeta([
                'ssh' => "ssh $hostUsername@$attempt_randorm_uid.$domain",
            ]);

            $attempt->appendNote("Started workspace attempt.");
            $attempt->appendNote("Workspace username: ".$hostUsername);
            $attempt->appendNote("Workspace password: ".$hostPassword);
            $attempt->appendNote("Workspace path: ".$workspacePath);
            $attempt->appendNote("Workspace domain: ".$attempt_randorm_uid.'.'.$domain);


            if(!$task->server)
            {
                throw new RuntimeException('No server assigned to task with sandbox enabled');
            }


            $jobs = [];
            $jobs[] = new CreateUserJob($attempt->id, $attempt->task->server->id);
            $jobs[] = new FindFreePort($attempt->task->server->id, $attempt->id);
            $jobs[] = new SetDockerComposeJob($attempt->id, $this->yamlFillWithData($task->docker_compose_yaml, [
                    "attempt_name" => $attempt_randorm_uid,
                    "domain" => $attempt->getMeta('domain'),
                    "ssh_port" => $attempt->getMeta('ssh_port'),
                    "workspace_path" => $workspacePath,
                ]));
            $jobs[] = new StartDockerComposeJob($attempt->id, $attempt->task->server->id);

            if($attempt->task->allowssh)
            {
                $jobs[] = new SetSshAccessToContainerJob($attempt->id, $attempt->task->server->id);
            }

            $jobs[] = new FinalizeWorkspaceJob($attempt->id);



            Bus::chain($jobs)->onQueue('default')->dispatch();

        }
        else
        {
            // For non-sandbox tasks, mark as running immediately
            $attempt->status = 'running';
            $attempt->started_at = now();
            $attempt->appendNote("Started non-sandbox task attempt.");
        }

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


    /**
     * Fill placeholders in a YAML string with provided data.
     * currently suppert datas are [attempt_name, domain, ssh_port, access_user, access_password, workspace_path]
    */
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


}
