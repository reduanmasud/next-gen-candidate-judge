# Provision remote server
# This script connects to the server via SSH and installs necessary dependencies

IP="{{ $ipAddress }}"
USER="{{ $sshUser }}"
PASSWORD="{{ $sshPassword }}"

# Check if sshpass is installed, install if not (with sudo when needed)
if ! command -v sshpass &> /dev/null; then
    echo "Installing sshpass..."
    if command -v apt-get &> /dev/null; then
        if command -v sudo &> /dev/null; then
            sudo apt-get update -qq && sudo apt-get install -y -qq sshpass || true
        else
            apt-get update -qq && apt-get install -y -qq sshpass || true
        fi
    elif command -v yum &> /dev/null; then
        if command -v sudo &> /dev/null; then
            sudo yum install -y -q sshpass || true
        else
            yum install -y -q sshpass || true
        fi
    elif command -v dnf &> /dev/null; then
        if command -v sudo &> /dev/null; then
            sudo dnf install -y -q sshpass || true
        else
            dnf install -y -q sshpass || true
        fi
    elif command -v apk &> /dev/null; then
        if command -v sudo &> /dev/null; then
            sudo apk add --no-cache sshpass || true
        else
            apk add --no-cache sshpass || true
        fi
    fi
fi

# Fail early if sshpass is still unavailable
if ! command -v sshpass &> /dev/null; then
    echo "ERROR: sshpass is required but could not be installed. Install sshpass on the application host and retry."
    exit 1
fi

# Test SSH connection (force password auth to avoid key prompts)
echo "Testing SSH connection to $IP..."
if sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no -o PubkeyAuthentication=no -o PreferredAuthentications=password -o ConnectTimeout=10 "$USER"@$IP "echo 'Connection successful'" 2>&1; then
    echo "SSH connection successful"
else
    echo "ERROR: Failed to connect to $IP via SSH"
    exit 1
fi

# Provision the server
echo "Provisioning server $IP..."

# Install Docker if not present
sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no -o PubkeyAuthentication=no -o PreferredAuthentications=password "$USER"@$IP << 'DOCKER_INSTALL'
    set -e
    
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
DOCKER_INSTALL

if [ $? -eq 0 ]; then
    echo "Server $IP has been provisioned successfully"
else
    echo "ERROR: Failed to provision server $IP"
    exit 1
fi

