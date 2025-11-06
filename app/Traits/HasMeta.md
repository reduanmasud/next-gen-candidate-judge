# HasMeta Trait Documentation

The `HasMeta` trait provides convenient methods for managing JSON metadata fields in Laravel models.

## Installation

Add the trait to any model that has a `metadata` JSON column:

```php
use App\Traits\HasMeta;

class YourModel extends Model
{
    use HasMeta;
    
    protected $casts = [
        'metadata' => 'array',
    ];
}
```

## Available Methods

### Get Methods

#### `getMeta(string $key, $default = null)`
Get a metadata value by key. Supports dot notation.

```php
$value = $model->getMeta('user.name');
$value = $model->getMeta('missing_key', 'default value');
```

#### `getAllMeta(): array`
Get all metadata as an array.

```php
$allMeta = $model->getAllMeta();
```

#### `hasMeta(string $key): bool`
Check if a metadata key exists.

```php
if ($model->hasMeta('user.email')) {
    // Key exists
}
```

#### `getMetaForSpread(): array`
Get metadata as a safe array for spreading (returns empty array if null).

```php
$data = [
    ...$model->getMetaForSpread(),
    'new_key' => 'value',
];
```

### Set Methods

#### `setMeta(string $key, $value): self`
Set a single metadata value. Creates or updates the key.

```php
$model->setMeta('user.name', 'John Doe');
$model->setMeta('settings.theme', 'dark');
```

#### `addMeta(array $data, bool $overwrite = true): self`
Add/merge multiple metadata values.

```php
// Overwrite existing keys (default)
$model->addMeta([
    'key1' => 'value1',
    'key2' => 'value2',
]);

// Only add keys that don't exist
$model->addMeta([
    'key1' => 'value1',
], false);
```

#### `updateMeta(array $data): self`
Update metadata by merging with existing data. Alias for `addMeta($data, true)`.

```php
$model->updateMeta([
    'containers' => $containers,
    'name' => $name,
]);
```

#### `mergeMeta(array $data): self`
Deep merge metadata using `array_merge_recursive`.

```php
$model->mergeMeta([
    'settings' => [
        'notifications' => true,
    ],
]);
```

### Append Methods

#### `appendMeta(string $key, $value): self`
Append a value to a metadata array key.

```php
// If key doesn't exist, creates array
$model->appendMeta('tags', 'php');

// If key exists as array, appends to it
$model->appendMeta('tags', 'laravel');
// Result: ['php', 'laravel']

// If key exists but is not array, converts to array
$model->setMeta('status', 'active');
$model->appendMeta('status', 'verified');
// Result: ['active', 'verified']
```

### Delete Methods

#### `deleteMeta(string $key): self`
Delete a single metadata key.

```php
$model->deleteMeta('user.email');
```

#### `deleteMetaKeys(array $keys): self`
Delete multiple metadata keys.

```php
$model->deleteMetaKeys(['key1', 'key2', 'user.email']);
```

#### `clearMeta(): self`
Clear all metadata.

```php
$model->clearMeta();
```

### Numeric Methods

#### `incrementMeta(string $key, $amount = 1): self`
Increment a numeric metadata value.

```php
$model->incrementMeta('views');
$model->incrementMeta('score', 10);
```

#### `decrementMeta(string $key, $amount = 1): self`
Decrement a numeric metadata value.

```php
$model->decrementMeta('attempts');
$model->decrementMeta('credits', 5);
```

## Usage Examples

### Example 1: Managing Container Metadata

```php
$attempt = UserTaskAttempt::find(1);

// Add container information
$attempt->updateMeta([
    'containers' => $containers,
    'name' => $containerName,
    'port' => 8080,
]);

// Get specific value
$port = $attempt->getMeta('port');

// Append to array
$attempt->appendMeta('logs', 'Container started');
$attempt->appendMeta('logs', 'Health check passed');
```

### Example 2: Tracking Job Execution

```php
$jobRun = ScriptJobRun::find(1);

// Initialize metadata
$jobRun->updateMeta([
    'started_at' => now()->toISOString(),
    'attempts' => 0,
]);

// Increment attempts
$jobRun->incrementMeta('attempts');

// Add execution details
$jobRun->appendMeta('execution_log', [
    'timestamp' => now(),
    'status' => 'running',
]);

// Check if key exists
if ($jobRun->hasMeta('error')) {
    $error = $jobRun->getMeta('error');
}
```

### Example 3: Safe Spreading (Replacing Manual Null Checks)

**Before:**
```php
$model->update([
    'metadata' => [
        ...($model->metadata ?? []),
        'new_key' => 'value',
    ],
]);
```

**After:**
```php
// Option 1: Use updateMeta (recommended)
$model->updateMeta([
    'new_key' => 'value',
]);

// Option 2: Use getMetaForSpread if you need manual control
$model->update([
    'metadata' => [
        ...$model->getMetaForSpread(),
        'new_key' => 'value',
    ],
]);
```

## Notes

- All methods that modify metadata automatically call `save()` on the model
- Methods support dot notation for nested keys (e.g., `'user.profile.name'`)
- The trait assumes your model has a `metadata` column cast to `array`
- All methods return `$this` for method chaining (except getters)

## Method Chaining

```php
$model
    ->setMeta('status', 'active')
    ->incrementMeta('views')
    ->appendMeta('tags', 'featured')
    ->updateMeta(['priority' => 'high']);
```

