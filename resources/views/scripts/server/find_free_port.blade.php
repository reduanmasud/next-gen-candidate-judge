cd {{ $workspace_path }}
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

    # Output JSON for PHP
      echo "__OUTPUT_JSON__"
      echo "{\"ssh_port\": $port }"
      echo "__OUTPUT_JSON_END__"

    # Save port to file
    echo "$port" > "{{ $workspace_path }}/.ssh_port"



    exit 0
  fi
done


echo "[!] No free port found in range $start_port-$max_port" >&2
echo "__OUTPUT_JSON__"
echo "{\"error\": \"No free port found between $start_port and $max_port\"}"
echo "__OUTPUT_JSON_END__"
exit 1
