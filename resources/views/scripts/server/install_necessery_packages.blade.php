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