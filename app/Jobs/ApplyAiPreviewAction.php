<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ShiftSourceEnum;
use App\Models\Schedule;
use App\Models\ShiftRequirement;
use App\Models\Store;
use App\Models\User;
use App\Services\Scheduling\ConflictDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Async job that takes the AI preview and applies it. In MVP we apply
 * synchronously and the job just records audit/log; keeping the queue
 * for future scale.
 */
class ApplyAiPreviewAction implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Constructor.
     *
     * @param array<int, array<string, mixed>> $shiftRequirements
     */
    public function __construct(
        public readonly int $scheduleId,
        public readonly int $actorId,
        public readonly array $shiftRequirements,
    ) {}

    /**
     * Handle the job.
     */
    public function handle(ConflictDetectionService $conflicts): void
    {
        $schedule = Schedule::query()->find($this->scheduleId);
        if (!$schedule instanceof Schedule) {
            return;
        }

        $actor = User::query()->find($this->actorId);
        if (!$actor instanceof User) {
            return;
        }

        $store = $schedule->store;
        if (!$store instanceof Store) {
            return;
        }

        foreach ($this->shiftRequirements as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $date = $row['date'] ?? '';
            $startTime = $row['start_time'] ?? '';
            $endTime = $row['end_time'] ?? '';
            $count = $row['required_employee_count'] ?? 1;
            $roleLabel = $row['role_label'] ?? null;
            $note = $row['note'] ?? null;

            $req = new ShiftRequirement();
            $req->forceFill([
                'schedule_id' => $schedule->getKey(),
                'store_id' => $store->getKey(),
                'date' => \is_string($date) ? $date : '',
                'start_time' => \is_string($startTime) ? $startTime : '',
                'end_time' => \is_string($endTime) ? $endTime : '',
                'required_employee_count' => \is_int($count) ? $count : (\is_string($count) && \ctype_digit($count) ? (int) $count : 1),
                'role_label' => \is_string($roleLabel) ? $roleLabel : null,
                'note' => \is_string($note) ? $note : null,
                'source' => ShiftSourceEnum::Ai->value,
                'created_by' => $actor->getKey(),
            ])->save();
        }

        $conflicts->recompute($schedule);
    }
}
