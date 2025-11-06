<?php

namespace App\Services;

final class ScriptWrapper
{
    public static function wrap(string $script): string
    {
        return <<< BASH_SCRIPT_EOT
#!/bin/bash
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

{$script}
BASH_SCRIPT_EOT;

    }
}
