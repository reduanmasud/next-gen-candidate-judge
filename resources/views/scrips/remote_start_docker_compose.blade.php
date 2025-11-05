# Start docker compose on remote and print container info markers
# This script runs ON the remote server after being uploaded by ScriptEngine

WORKDIR="{{ $workspacePath }}"
PROJECT="{{ $projectName }}"

set -e

# Navigate to workspace and start docker compose
cd "$WORKDIR"
docker compose -p "$PROJECT" up -d --remove-orphans

# Output docker ps data wrapped with markers for parser compatibility
echo '__DOCKER_PS_START__'
docker ps --filter label=com.docker.compose.project=$PROJECT --format '{{json .}}'
echo '__DOCKER_PS_END__'


