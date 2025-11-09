echo "[+] Installing Necessary Packages"
apt-get install -y -qq \
    -o Dpkg::Options::="--force-confdef" \
    -o Dpkg::Options::="--force-confold" \
    ca-certificates \
    curl \
    gnupg \
    lsb-release \
    apt-transport-https \
    software-properties-common \
    sshpass \
    openssh-client
echo "[✓] Installing Necessary Packages" 

echo "[+] Installing yq package..."
wget https://github.com/mikefarah/yq/releases/latest/download/yq_linux_amd64 -O /usr/bin/yq
chmod +x /usr/bin/yq
yq --version
echo "[✓] yq package installed"