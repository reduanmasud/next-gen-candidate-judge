
# Set SSH access to container
echo "[+] Setting SSH access to container..."

ENTRY_PORT=$(cat {!! $workspacePath !!}/.ssh_port)
ENTRY_NAME=ssh-{{ $container_name }}

yq eval -i ".entryPoints.${ENTRY_NAME}.address = \":${ENTRY_PORT}\"" /opt/traefik/static/ssh-entrypoints.yml

cat << 'EOF' > /opt/traefik/dynamic/ssh-{{ $container_name }}.yml
tcp:
  routers:
    ssh-{{ $container_name }}:
      rule: "HostSNI(`*`)"
      service: ssh-{{ $container_name }}
      entryPoints:
        - ssh-{{ $container_name }}
  services:
    ssh-{{ $container_name }}:
      loadBalancer:
        servers:
          - address: "{{ $container_name }}:22"
EOF

echo "[✓] SSH access to container set successfully"



docker exec -i {{ $container_name }} bash -c "apt update && apt install -y openssh-server"
docker exec -i {{ $container_name }} bash -c "echo 'root:root' | chpasswd"


