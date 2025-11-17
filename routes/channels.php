<?php

use Illuminate\Support\Facades\Broadcast;

// Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
//     return (int) $user->id === (int) $id;
// });


Broadcast::channel('job-runs-updated', function ($user) {
    // return true;
    return $user->hasRole('admin');
});

Broadcast::channel('server-updates.{serverId}', function ($user, $serverId) {
    // Only admins can listen to server provisioning updates
    return $user->hasRole('admin');
});

Broadcast::channel('workspace-updates.{attemptId}', function ($user, $attemptId) {
    if ($user->hasRole('admin')) {
        return true;
    }

    $attempt = App\Models\UserTaskAttempt::find($attemptId);
    return $attempt && (int) $user->id === (int) $attempt->user_id;
});

