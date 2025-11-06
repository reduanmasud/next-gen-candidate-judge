mkdir -p '{!!  $workspacePath !!}'

echo "Writing docker-compose.yaml to {{ $workspacePath }}/docker-compose.yaml"

cat << 'EOF' > '{!! $workspacePath !!}/docker-compose.yaml'
{!! $dockerComposeYaml !!}
EOF
