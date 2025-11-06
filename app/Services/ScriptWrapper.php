<?php

namespace App\Services;

class ScriptWrapper
{
    public function wrap(string $script): string
    {
        return <<< BASH_SCRIPT_EOT
#!/bin/bash
set -euo pipefail

{$script}
BASH_SCRIPT_EOT;

}
}
