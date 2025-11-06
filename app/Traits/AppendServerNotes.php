<?php

namespace App\Traits;

use App\Models\Server;

trait AppendServerNotes
{
    use AppendsNotes;
    /**
     * Append a timestamped message to a Server's notes field.
     */
    protected function appendServerNotes(Server $server, string $message): void
    {
        $server->update([
            'notes' => $this->appendToNotes($server->notes, $message),
        ]);
    }
}
