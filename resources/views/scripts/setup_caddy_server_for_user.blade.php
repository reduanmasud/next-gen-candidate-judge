# Setup Caddy server for user
echo "Setting up Caddy server for user..."

CONTAINER_NAME={{ $container_name }}

# Create required directories and permissions
sudo mkdir -p /etc/caddy/sites /var/lib/caddy
sudo chmod -R 755 /etc/caddy /var/lib/caddy


# Start Caddy container if it doesn't exist
if docker ps -a --format '@{{.Names}}' | grep -q '^caddy$'; then
    echo "Caddy container exists. Removing..."
    docker rm -f caddy || true
fi

echo "Creating new Caddy container..."
docker run -d --name caddy \
    --restart unless-stopped \
    --privileged \
    --network caddy_network \
    -p 80:80 \
    -p 443:443 \
    -v /etc/caddy:/etc/caddy \
    -v /var/lib/caddy:/var/lib/caddy \
    caddy:latest


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
cat > /etc/caddy/sites/$CONTAINER_NAME.caddy << 'CADDY_EOF'
$CONTAINER_NAME.wpqa.online {
    reverse_proxy $CONTAINER_NAME
}
CADDY_EOF

# Reload Caddy
docker exec caddy caddy reload --config /etc/caddy/Caddyfile

