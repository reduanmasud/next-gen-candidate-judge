mkdir -p '{!!  $workspace_path !!}'




export SSH_PORT={!! $ssh_port !!}

echo "Writing docker-compose.yaml to {{ $workspace_path }}/docker-compose.yaml"

cat << 'EOF' > '{!! $workspace_path !!}/docker-compose.yaml'
{!! $docker_compose_yaml !!}
EOF


