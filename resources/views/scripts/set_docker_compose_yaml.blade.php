mkdir -p '{!!  $workspacePath !!}'

# Create network if it doesn't exist
docker network create caddy_network || true


echo "Writing docker-compose.yaml to {{ $workspacePath }}/docker-compose.yaml"

cat << 'EOF' > '{!! $workspacePath !!}/docker-compose.yaml'
{!! $dockerComposeYaml !!}
EOF

