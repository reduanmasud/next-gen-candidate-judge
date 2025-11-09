# Delete workspace
echo "[+] Deleting workspace..."
userdel -r {{ $username }} 2>/dev/null || true
echo "[✓] Workspace deleted successfully"


echo "[+] Deleting Docker containers..."
docker rm -f {{ $container_name }} 2>/dev/null || true
echo "[✓] Docker containers deleted successfully"

@if($allowssh)
echo "[+] Removing SSH access network..."
TARIFIK_DYNAMIC_DIR="/opt/traefik/dynamic"
rm -f $TARIFIK_DYNAMIC_DIR/ssh-{{ $container_name }}.yml 2>/dev/null || true
echo "[✓] SSH access network removed successfully"

ENTRY_PORT = (cat {!! workspacePath !!}/.ssh_port)
ENTRY_NAME = ssh-{{ $container_name }}

yq -i -y "del(.entryPoints.${ENTRY_NAME})" /opt/traefik/static/ssh-entrypoints.yml
@endif

