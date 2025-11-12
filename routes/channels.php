<?php

use App\Models\ScriptJobRun;
use Illuminate\Support\Facades\Broadcast;

// Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
//     return (int) $user->id === (int) $id;
// });


Broadcast::channel('job-runs-updated', function ($user) {

    // return true;
    return $user->hasRole('admin');

});
