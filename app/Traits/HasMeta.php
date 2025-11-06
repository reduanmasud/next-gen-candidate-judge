<?php

namespace App\Traits;

trait HasMeta
{
    /**
     * Get a metadata value by key.
     * Returns null if the key doesn't exist.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getMeta(string $key, $default = null)
    {
        $metadata = $this->metadata ?? [];
        
        return data_get($metadata, $key, $default);
    }

    /**
     * Get all metadata as an array.
     *
     * @return array
     */
    public function getAllMeta(): array
    {
        return $this->metadata ?? [];
    }

    /**
     * Set a metadata value by key.
     * Creates the key if it doesn't exist, updates if it does.
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setMeta(string $key, $value): self
    {
        $metadata = $this->metadata ?? [];
        
        data_set($metadata, $key, $value);
        
        $this->metadata = $metadata;
        $this->save();
        
        return $this;
    }

    /**
     * Add/merge metadata values.
     * Merges the provided array with existing metadata.
     *
     * @param array $data
     * @param bool $overwrite Whether to overwrite existing keys (default: true)
     * @return $this
     */
    public function addMeta(array $data, bool $overwrite = true): self
    {
        $metadata = $this->metadata ?? [];
        
        if ($overwrite) {
            $metadata = array_merge($metadata, $data);
        } else {
            // Only add keys that don't exist
            foreach ($data as $key => $value) {
                if (!array_key_exists($key, $metadata)) {
                    $metadata[$key] = $value;
                }
            }
        }
        
        $this->metadata = $metadata;
        $this->save();
        
        return $this;
    }

    /**
     * Update metadata by merging with existing data.
     * Alias for addMeta with overwrite enabled.
     *
     * @param array $data
     * @return $this
     */
    public function updateMeta(array $data): self
    {
        return $this->addMeta($data, true);
    }

    /**
     * Append a value to a metadata array key.
     * If the key doesn't exist, creates it as an array.
     * If the key exists but is not an array, converts it to an array.
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function appendMeta(string $key, $value): self
    {
        $metadata = $this->metadata ?? [];
        
        $existing = data_get($metadata, $key);
        
        if ($existing === null) {
            // Key doesn't exist, create as array
            data_set($metadata, $key, [$value]);
        } elseif (is_array($existing)) {
            // Key exists and is array, append to it
            $existing[] = $value;
            data_set($metadata, $key, $existing);
        } else {
            // Key exists but is not array, convert to array
            data_set($metadata, $key, [$existing, $value]);
        }
        
        $this->metadata = $metadata;
        $this->save();
        
        return $this;
    }

    /**
     * Delete a metadata key.
     *
     * @param string $key
     * @return $this
     */
    public function deleteMeta(string $key): self
    {
        $metadata = $this->metadata ?? [];
        
        data_forget($metadata, $key);
        
        $this->metadata = $metadata;
        $this->save();
        
        return $this;
    }

    /**
     * Delete multiple metadata keys.
     *
     * @param array $keys
     * @return $this
     */
    public function deleteMetaKeys(array $keys): self
    {
        $metadata = $this->metadata ?? [];
        
        foreach ($keys as $key) {
            data_forget($metadata, $key);
        }
        
        $this->metadata = $metadata;
        $this->save();
        
        return $this;
    }

    /**
     * Check if a metadata key exists.
     *
     * @param string $key
     * @return bool
     */
    public function hasMeta(string $key): bool
    {
        $metadata = $this->metadata ?? [];
        
        return data_get($metadata, $key) !== null;
    }

    /**
     * Clear all metadata.
     *
     * @return $this
     */
    public function clearMeta(): self
    {
        $this->metadata = [];
        $this->save();
        
        return $this;
    }

    /**
     * Merge metadata with the provided array.
     * Uses array_merge_recursive for deep merging.
     *
     * @param array $data
     * @return $this
     */
    public function mergeMeta(array $data): self
    {
        $metadata = $this->metadata ?? [];
        
        $this->metadata = array_merge_recursive($metadata, $data);
        $this->save();
        
        return $this;
    }

    /**
     * Increment a numeric metadata value.
     *
     * @param string $key
     * @param int|float $amount
     * @return $this
     */
    public function incrementMeta(string $key, $amount = 1): self
    {
        $metadata = $this->metadata ?? [];
        
        $current = data_get($metadata, $key, 0);
        data_set($metadata, $key, $current + $amount);
        
        $this->metadata = $metadata;
        $this->save();
        
        return $this;
    }

    /**
     * Decrement a numeric metadata value.
     *
     * @param string $key
     * @param int|float $amount
     * @return $this
     */
    public function decrementMeta(string $key, $amount = 1): self
    {
        return $this->incrementMeta($key, -$amount);
    }

    /**
     * Get metadata as a safe array for spreading.
     * Returns empty array if metadata is null.
     *
     * @return array
     */
    public function getMetaForSpread(): array
    {
        return $this->metadata ?? [];
    }
}

