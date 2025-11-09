# Update server firewall
echo "[+] Updating server firewall..."

ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 2222/tcp
ufw allow 8080/tcp

echo "[✓] Server firewall updated successfully"