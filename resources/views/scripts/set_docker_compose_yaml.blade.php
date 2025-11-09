mkdir -p '{!!  $workspacePath !!}'



# Run Pre Scripts
{!! $preScripts !!}

export SSH_PORT=$(cat {!! $workspacePath !!}/.ssh_port)

echo "Writing docker-compose.yaml to {{ $workspacePath }}/docker-compose.yaml"

cat << 'EOF' > '{!! $workspacePath !!}/docker-compose.yaml'
{!! $dockerComposeYaml !!}
EOF

# Run Post Scripts
{!! $postScripts !!}
