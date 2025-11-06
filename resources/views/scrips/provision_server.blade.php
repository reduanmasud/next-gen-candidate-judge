# Provision remote server
# This script runs ON the remote server after being uploaded by ScriptEngine

echo "Starting server provisioning..."

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "Installing Docker..."

    # Remove old versions
    apt-get remove -y docker docker-engine docker.io containerd runc 2>/dev/null || true

    # Install prerequisites
    apt-get update -qq
    apt-get install -y -qq \
        ca-certificates \
        curl \
        gnupg \
        lsb-release

    # Add Docker's official GPG key
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg

    # Set up repository
    echo \
      "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
      $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null

    # Install Docker Engine
    apt-get update -qq
    apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

    echo "Docker installed successfully"
else
    echo "Docker is already installed"
    docker --version
fi

# Install Docker Compose standalone if not present (for older systems)
if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    echo "Installing Docker Compose standalone..."
    curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
    echo "Docker Compose installed successfully"
else
    echo "Docker Compose is already available"
fi

# Start and enable Docker service
systemctl start docker 2>/dev/null || true
systemctl enable docker 2>/dev/null || true

# Verify installation
docker --version
if command -v docker-compose &> /dev/null; then
    docker-compose --version
elif docker compose version &> /dev/null; then
    docker compose version
fi

echo "Server provisioning completed successfully"

# Install Caddy
echo "Installing Caddy..."
apt install -y debian-keyring debian-archive-keyring apt-transport-https
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | tee /etc/apt/trusted.gpg.d/caddy-stable.asc
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
apt update
apt install caddy

mkdir -p /etc/caddy/sites 
touch /etc/caddy/Caddyfile

cat > /etc/caddy/Caddyfile << 'CADDY_EOF'
import /etc/caddy/sites/*.caddy
CADDY_EOF


