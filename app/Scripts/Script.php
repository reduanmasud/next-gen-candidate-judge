<?php

namespace App\Scripts;

abstract class Script
{


    /**
     * The name of the script.
     */
    public function name(): string
    {
        return 'Script';
    }

    /**
     * The template for the script.
     */
    public function template(): string
    {
        return '';
    }

    /**
     * The data to be passed to the script.
     */
    public function data(): array
    {
        return [];
    }
}
