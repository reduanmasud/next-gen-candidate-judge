<?php

namespace App\Scripts;

/**
 * Lightweight value object that describes a script to be executed.
 */
final class ScriptDescriptor
{
    public function __construct(
        public string $name,
        public string $template,
        public array $data = []
    ) {
    }

    public static function make(string $template, array $data = [], ?string $name = null): self
    {
        return new self($name ?? $template, $template, $data);
    }
}
