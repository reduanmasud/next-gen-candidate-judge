cd {{ $workspacePath }}
start_port=18000
max_port=65535

if command -v nc >/dev/null 2>&1; then
    echo "[+] nc is already installed."
else
    echo "[+] nc not found. Installing netcat..."

    # Update package list and install netcat
    sudo apt update -y
    sudo apt install -y netcat-openbsd

    # Verify installation
    if command -v nc >/dev/null 2>&1; then
        echo "[✓] nc has been installed successfully."
    else
        echo "[!] Installation failed. Please check your network or sources."
        exit 1
    fi
fi

# Continue with next steps
echo "➡️ Moving to the next step..."

for ((port=$start_port; port<=$max_port; port++)); do
  # Check if the port is free
  if ! nc -z localhost $port 2>/dev/null; then
    echo "__PORT_START__"
    echo $port
    echo "__PORT_END__"

    # Save port to file
    echo "$port" > "{{ $workspacePath }}/.ssh_port"


    
    exit 0
  fi
done


echo "[!] No free port found in range $start_port-$max_port" >&2
exit 1