cat << 'EOF' > /home/{{ $username }}/workspace/docker-compose.yaml
{{ $dockerComposeYaml }}
EOF

