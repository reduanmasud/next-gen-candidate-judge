mkdir -p '{{ $workspacePath }}'

cat << 'EOF' > '{{ $workspacePath }}/docker-compose.yaml'
{!! $dockerComposeYaml !!}
EOF
