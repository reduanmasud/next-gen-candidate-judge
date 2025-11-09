# create or update the workspace user
if id -u {{ $username }} >/dev/null 2>&1; then
    echo "User {{ $username }} already exists"
else
    useradd -m -s /bin/bash -G docker {{ $username }}
fi

echo "{{ $username }}:{{ $password }}" | chpasswd

mkdir -p /home/{{ $username }}
mkdir -p {{ $workspace_path }}


chown -R {{ $username }}:{{ $username }} {{ $workspace_path }}

if id -nG {{ $username }} | grep -qw sudo; then
    sudo deluser {{ $username }} sudo
    echo "[+] Removed {{ $username }} from sudo group"
fi

# Remove user from docker group if they are in it
if id -nG {{ $username }} | grep -qw docker; then
    sudo deluser {{ $username }} docker
    echo "[+] Removed {{ $username }} from docker group"
fi

if [ -f /etc/sudoers.d/{{ $username }} ]; then
    sudo rm -f /etc/sudoers.d/{{ $username }}
    echo "[+] Removed sudoers entry for {{ $username }}"
fi

if [ -f /etc/sudoers.d/{{ $username }} ]; then
    sudo rm -f /etc/sudoers.d/{{ $username }}
    echo "[+] Removed sudoers entry for {{ $username }}"
fi

sudo usermod -s /usr/sbin/nologin {{ $username }}

sudo -l -U {{ $username }}


