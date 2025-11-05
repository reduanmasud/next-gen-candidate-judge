# Write docker-compose.yaml to remote workspace
# This script runs ON the remote server after being uploaded by ScriptEngine

WORKDIR="{{ $workspacePath }}"

set -e

# Ensure workspace directory exists
mkdir -p "$WORKDIR"
chmod 755 "$WORKDIR"

# Write docker-compose.yaml content
cat > "$WORKDIR/docker-compose.yaml" << 'COMPOSE_EOF'
{{ $dockerComposeYaml }}
COMPOSE_EOF

echo "docker-compose.yaml written to $WORKDIR"


