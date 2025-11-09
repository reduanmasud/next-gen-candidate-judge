<?php

namespace App\Exceptions;

use Exception;

class MetadataKeyNotFoundException extends Exception
{
    public function __construct(
        string $key,
        object $model,
        array $availableKeys,
        array $caller
    )
    {


        $modelClass = get_class($model);
        $modelId = $model->id ?? "unsaved";

        $availableKeyStr = empty($availableKeys) ? 'none' : implode(', ', $availableKeys);

        $callerInfo = "";

        if (!empty($caller)) {
            $callerClass = $caller['class'] ?? "Unknown";
            $callerFunction = $caller['function'] ?? "Unknown";
            $callerFile = basename($caller['file'] ?? "Unknown");
            $callerLine = $caller['line'] ?? "Unknown";

            $callerInfo = "\nCalled from: {$callerClass}::{$callerFunction} at ({$callerFile}:{$callerLine})}";
        }

        $message = "Metadata key '{$key}' not found in $modelClass (ID: {$modelId})\n. Available keys: [{$availableKeyStr}].$callerInfo.";
        parent::__construct($message);
    }
}
