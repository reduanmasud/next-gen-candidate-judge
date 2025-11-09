cd '{{ $workspacePath }}'

# Stop any existing containers for this project without failing the script
if [ -f "./docker-compose.yaml" ] && docker compose ps >/dev/null 2>&1; then
    docker compose down --remove-orphans --volumes --rmi all || true
fi

docker compose pull || true

docker compose up -d

@if ($task->timer > 0)
    # Run the timer and shutdown in the background so the job doesn't wait
    nohup bash -c "sleep {{ $task->timer }}m && cd '{{ $workspacePath }}' && docker compose down --volumes --rmi all" > /dev/null 2>&1 &
@endif


echo "__DOCKER_PS_START__"
docker compose ps --format '@{{json .}}'
echo "__DOCKER_PS_END__"
