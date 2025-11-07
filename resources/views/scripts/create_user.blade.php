# create or update the workspace user
if id -u {{ $username }} >/dev/null 2>&1; then
    echo "User {{ $username }} already exists"
else
    useradd -m -s /bin/bash -G docker {{ $username }}
fi

echo "{{ $username }}:{{ $password }}" | chpasswd

mkdir -p /home/{{ $username }}
mkdir -p {{ $workspacePath }}

chown -R {{ $username }}:{{ $username }} {{ $workspacePath }}
