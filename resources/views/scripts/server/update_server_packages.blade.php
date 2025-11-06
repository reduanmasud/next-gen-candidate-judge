# Update server package
echo "[+] Updating server package..."

apt-get update -qq
apt-get upgrade -y -qq

echo "[✓] Server package updated successfully"
