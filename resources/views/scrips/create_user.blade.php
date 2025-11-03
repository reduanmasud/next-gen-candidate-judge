# create a user
useradd -m -s /bin/bash -G docker {{ $username }}
echo "{{ $username }}:{{ $password }}" | chpasswd

mkdir -p /home/{{ $username }}
mkdir -p /home/{{ $username }}/workspace

chown -R {{ $username }}:{{ $username }} /home/{{ $username }}/workspace
