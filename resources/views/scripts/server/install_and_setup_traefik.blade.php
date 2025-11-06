# Setup Traefik server for user
echo "[+] Setting up Traefik server for user..."

# ======================
# CONFIGURATION
# ======================
TRAEFIK_DIR="/opt/traefik"
CLOUDFLARE_EMAIL="{{ $cloudflareEmail }}"       # your Cloudflare account email
CLOUDFLARE_API_TOKEN="{{ $cloudflareApiToken }}" # create from Cloudflare dashboard
DOMAIN="{{ $cloudflareDomain }}"
TRAEFIK_NETWORK="web"

# ======================
# SETUP
# ======================

echo "[+] Creating Traefik directory..."
sudo mkdir -p $TRAEFIK_DIR/{letsencrypt,config}
sudo chmod -R 755 $TRAEFIK_DIR

# ----------------------
# Create docker-compose.yml
# ----------------------
cat <<EOF | sudo tee $TRAEFIK_DIR/docker-compose.yml > /dev/null
services:
  traefik:
    image: traefik:v2.11
    container_name: traefik
    restart: unless-stopped
    command:
      - "--api.dashboard=true"
      - "--providers.docker=true"
      - "--providers.docker.exposedbydefault=false"
      - "--entrypoints.web.address=:80"
      - "--entrypoints.websecure.address=:443"
      - "--certificatesresolvers.cloudflare.acme.dnschallenge=true"
      - "--certificatesresolvers.cloudflare.acme.dnschallenge.provider=cloudflare"
      - "--certificatesresolvers.cloudflare.acme.email=$CLOUDFLARE_EMAIL"
      - "--certificatesresolvers.cloudflare.acme.storage=/letsencrypt/acme.json"
      - "--certificatesresolvers.cloudflare.acme.dnschallenge.resolvers=1.1.1.1:53"
    environment:
      - CF_API_EMAIL=$CLOUDFLARE_EMAIL
      - CF_DNS_API_TOKEN=$CLOUDFLARE_API_TOKEN
    ports:
      - "80:80"
      - "443:443"
      - "8080:8080"
    volumes:
      - "/var/run/docker.sock:/var/run/docker.sock:ro"
      - "./letsencrypt:/letsencrypt"
    networks:
      - $TRAEFIK_NETWORK

networks:
  $TRAEFIK_NETWORK:
    external: true
EOF

# ----------------------
# Create Docker network if missing
# ----------------------
if ! docker network ls | grep -q "$TRAEFIK_NETWORK"; then
    echo "[+] Creating Docker network: $TRAEFIK_NETWORK"
    docker network create $TRAEFIK_NETWORK
else
    echo "[✓] Docker network $TRAEFIK_NETWORK already exists."
fi

# ----------------------
# Create empty acme.json
# ----------------------
sudo touch $TRAEFIK_DIR/letsencrypt/acme.json
sudo chmod 600 $TRAEFIK_DIR/letsencrypt/acme.json

# ----------------------
# Launch Traefik
# ----------------------
echo "[+] Starting Traefik..."
cd $TRAEFIK_DIR
sudo docker compose up -d

# ----------------------
# Show Traefik dashboard
# ----------------------
SERVER_IP=$(hostname -I)
echo ""
echo "--------------------------------------------------"
echo " Dashboard : http://${SERVER_IP}:8080"
echo " Network   : $TRAEFIK_NETWORK"
echo " Certificates: Wildcard SSL via Cloudflare (*.${DOMAIN})"
echo "--------------------------------------------------"
echo "[✓] Traefik setup complete!"