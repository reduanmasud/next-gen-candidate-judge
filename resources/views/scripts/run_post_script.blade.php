mkdir -p '{!!  $workspace_path !!}'

cd {!!  $workspace_path !!}
echo "Running post script"

{!! $post_scripts !!}

echo "Post script completed"


