<?php

namespace App\Traits;

trait NotesAccessor
{


    /**
     * Append a message to the model's notes field and save.
     */
    public function appendNote(string $message, bool $timestamp = true): void
    {
        if ($timestamp) {
            $message = sprintf("[%s] %s", now()->toDateTimeString(), $message);
        }

        // Check if the model has 'notes' column
        if (!in_array('notes', $this->getFillable()) && !array_key_exists('notes', $this->attributes)) {
            throw new \Exception('Model does not have notes column');
        }

        // Append the new message on a new line
        $currentNotes = $this->notes ?? '';
        if (!empty($currentNotes)) {
            $currentNotes .= "\n"; // Add a newline if there are existing notes
        }
        $this->notes = $currentNotes . $message;

        $this->save();
    }

    public function getNotes(): string
    {
        return $this->notes ?? '';
    }

    

}
