<?php

namespace App\Traits;

use App\Exceptions\MetadataKeyNotFoundException;

trait HasMeta
{
    public function getMeta(string $key, $default = null)
    {
        # If key is missing throw error key missing
        if(!array_key_exists($key, $this->metadata))
        {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = $trace[1] ?? $trace[0];
            throw new MetadataKeyNotFoundException(
                key: $key,
                model: $this,
                availableKeys: array_keys($this->metadata ?? []),
                caller: $caller,
            );
        }

        return data_get($this->metadata, $key, $default);
    }


    public function getAllMeta(): array
    {
        return $this->metadata ?? [];
    }


    public function addMeta(array $data, bool $overwrite = true): self
    {
        if($overwrite)
        {
            $this->metadata = array_merge($this->metadata ?? [], $data);
        } else  {
            $this->metadata = array_merge_recursive($this->metadata ?? [], $data);
        }
        $this->save();
        return $this;
    }

}

