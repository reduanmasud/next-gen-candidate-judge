# Setup Caddy server for user
echo "Setting up Caddy server for user..."

# Create network if it doesn't exist
docker network create caddy_network || true

# Start Caddy container if it doesn't exist
if ! docker ps -a -f name=caddy >/dev/null 2>&1; then 
    docker run -d --name caddy \
        --restart unless-stopped \
        --privileged \
        --network caddy_network \
        -p 80:80 \
        -p 443:443 \
        -p 22:22 \
        -v /etc/caddy:/etc/caddy \
        -v /var/lib/caddy:/var/lib/caddy \
        caddy:latest
fi

# Create Caddyfile if it doesn't exist
if [ ! -f /etc/caddy/Caddyfile ]; then
    echo "Caddyfile does not exist. Creating..."
    touch /etc/caddy/Caddyfile
fi

# Import all caddy files
cat > /etc/caddy/Caddyfile << 'CADDY_EOF'
import /etc/caddy/sites/*.caddy
CADDY_EOF

# Create Caddyfile
mkdir -p /etc/caddy/sites

# Create Caddyfile for user
cat > /etc/caddy/sites/{{ $username }}.caddy << 'CADDY_EOF'
{{ $username }}.wpqa.online {
    reverse_proxy {{ $username }}
}
CADDY_EOF
