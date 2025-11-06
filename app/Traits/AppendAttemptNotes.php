<?php

namespace App\Traits;

use App\Models\UserTaskAttempt;

trait AppendAttemptNotes
{
    use AppendsNotes;
    /**
     * Append a timestamped message to a UserTaskAttempt's notes field.
     */
    protected function appendAttemptNotes(UserTaskAttempt $attempt, string $message): void
    {
        $attempt->update([
            'notes' => $this->appendToNotes($attempt->notes, $message),
        ]);
    }
}
