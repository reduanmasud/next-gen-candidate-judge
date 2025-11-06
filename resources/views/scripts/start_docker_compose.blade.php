cd '{{ $workspacePath }}'

# Stop any existing containers for this project without failing the script
if [ -f "./docker-compose.yaml" ] && docker compose ps >/dev/null 2>&1; then
    docker compose down --remove-orphans || true
fi

docker compose pull || true
docker compose up -d

echo "__DOCKER_PS_START__"
docker compose ps --format '@{{json .}}'
echo "__DOCKER_PS_END__"
