<?php

namespace App\Http\Resources;

use App\Enums\AttemptTaskStatus;
use App\Enums\TaskUserLockStatus;
use App\Services\TaskScoreService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $userId = $request->user()->id;

        $latestAttempt = $this->attempts->first();
        $status = optional($latestAttempt)->status;

        $isPreparing = $status === AttemptTaskStatus::PREPARING;
        $isStarted = $status === AttemptTaskStatus::RUNNING;

        $isFailed = in_array($status, [AttemptTaskStatus::FAILED, AttemptTaskStatus::TERMINATED], true);

        $isCompleted = $status === AttemptTaskStatus::COMPLETED;

        $isLockedByPenalty = $this->lockedUsers->where('status', TaskUserLockStatus::PENALTY)->isNotEmpty();
        $isLockedByCompletion = $this->lockedUsers->where('status', TaskUserLockStatus::COMPLETED)->isNotEmpty();


        $attemptNumber = $this->attempt_count;

        $service = app(TaskScoreService::class);

        $isCompletedSuccessfully = $service->isCompletedSuccessfully(
            $this,
            $latestAttempt,
            $attemptNumber
        );

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'score' => $this->score,
            'is_started' => $isStarted,
            'is_completed' => $isCompleted,
            'is_preparing' => $isPreparing,
            'is_locked_by_penalty' => $isLockedByPenalty,
            'is_locked_by_completion' => $isLockedByCompletion,
            'is_failed' => $isFailed,
            'is_completed_successfully' => $isCompletedSuccessfully,
            'attempt_id' => optional($latestAttempt)->id,
            'attempt_count' => $attemptNumber,
            'sandbox' => $this->sandbox,
            'allowssh' => $this->allowssh,
            'timer' => $this->timer,
            'started_at' => optional($latestAttempt)->started_at?->toIso8601String(),
        ];
    }
}
