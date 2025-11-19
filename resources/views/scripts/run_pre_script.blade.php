mkdir -p '{!!  $workspace_path !!}'

cd {!!  $workspace_path !!}
echo "Running pre script"

{!! $pre_scripts !!}

echo "Pre script completed"


