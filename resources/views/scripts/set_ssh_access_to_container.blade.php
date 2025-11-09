
# Set SSH access to container
echo "[+] Setting SSH access to container..."

sudo usermod -s /bin/bash {{ $username }}


sudo tee /usr/local/bin/enter_{{ $username }}.sh > /dev/null <<EOF
#!/bin/bash
# Wrapper for allowed container
docker exec -it {{ $container_name }} "\$@"
EOF


sudo chown root:{{ $username }} /usr/local/bin/enter_{{ $username }}.sh
sudo chmod 750 /usr/local/bin/enter_{{ $username }}.sh

echo "{{ $username }} ALL=(ALL) NOPASSWD: /usr/local/bin/enter_{{ $username }}.sh" | sudo tee /etc/sudoers.d/{{ $username }}
sudo chmod 440 /etc/sudoers.d/{{ $username }}

echo "alias ssh_to_app='sudo /usr/local/bin/enter_{{ $username }}.sh bash'" >> /home/{{ $username }}/.bashrc
chown {{ $username }}:{{ $username }} /home/{{ $username }}/.bashrc


# ------------------------------
# Optional: Force SSH login directly to container
# ------------------------------
ssh_config="/etc/ssh/sshd_config"

# Only add forced command if not already present
if ! grep -q "Match User {{ $username }}" "$ssh_config"; then
    sudo tee -a "$ssh_config" > /dev/null <<'EOF'

# --- Forced SSH login for user {{ $username }} ---
Match User {{ $username }}
    ForceCommand sudo /usr/local/bin/enter_{{ $username }}.sh bash
    PermitTTY yes
    X11Forwarding no
# --- End Forced SSH login for user {{ $username }} ---
EOF

    # Reload SSH to apply changes
    sudo systemctl restart sshd
    echo "[+] SSH forced command added for {{ $username }}"
fi


echo "[✓] SSH access to container set successfully"

