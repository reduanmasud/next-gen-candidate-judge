<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScriptJobRunResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'script_name' => $this->script_name,
            'script_path' => $this->script_path,
            'script_content' => $this->script_content,
            'status' => $this->status,
            'output' => $this->output,
            'error_output' => $this->error_output,
            'exit_code' => $this->exit_code,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'task' => $this->task ? [
                'id' => $this->task->id,
                'title' => $this->task->title,
            ] : null,
            'server' => $this->server ? [
                'id' => $this->server->id,
                'name' => $this->server->name,
            ] : null,
            'attempt' => $this->attempt ? [
                'id' => $this->attempt->id,
                'status' => $this->attempt->status,
            ] : null,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'started_at' => $this->started_at?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
        ];

    }
}
