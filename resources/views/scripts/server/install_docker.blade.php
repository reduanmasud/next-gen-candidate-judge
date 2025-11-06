echo "[+] Installing Docker"
# Install Docker 
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh    
echo "[✓] Installing Docker"

echo "[+] Starting Docker Service"
# Start and enable Docker service
systemctl start docker 2>/dev/null || true
systemctl enable docker 2>/dev/null || true
echo "[✓] Starting Docker Service"

echo "[+] Verifying Installation"
# Verify installation
docker --version

if docker compose version &> /dev/null; then
    docker compose version
fi
echo "[✓] Verifying Installation"