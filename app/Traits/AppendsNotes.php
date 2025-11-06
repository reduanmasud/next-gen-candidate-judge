<?php

namespace App\Traits;

trait AppendsNotes
{
    protected function appendToNotes(?string $existing, string $message): string
    {
        $existing = $existing ? trim($existing) . "\n" : '';
        return $existing . $message;
    }
}
