<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->authorizedUser($request);

        $logs = $this->visibleLogs($user)
            ->with(['user', 'loggable'])
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => $this->formatLog($log));

        return Inertia::render('Logs/Index', [
            'logs' => $logs,
        ]);
    }

    public function show(Request $request, string $locale, ActivityLog $log): Response
    {
        $user = $this->authorizedUser($request);

        abort_unless(
            $this->visibleLogs($user)->whereKey($log->getKey())->exists(),
            404,
        );

        $log->load(['user', 'loggable']);

        return Inertia::render('Logs/Show', [
            'log' => $this->formatLogDetail($log),
        ]);
    }

    protected function authorizedUser(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->user();

        abort_unless(
            $user?->hasAnyRole(['owner', 'admin', 'super_admin']),
            403,
            'You are not allowed to view activity logs.',
        );

        return $user;
    }

    protected function visibleLogs(User $user): Builder
    {
        return ActivityLog::query()
            ->when(
                ! $user->hasRole('owner'),
                fn (Builder $query) => $user->gym_id
                    ? $query->where('gym_id', $user->gym_id)
                    : $query->whereRaw('1 = 0'),
            );
    }

    protected function formatLog(ActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'subject' => $this->subjectLabel($log),
            'description' => $log->message,
            'user' => $this->userLabel($log),
            'created_at' => $log->created_at?->format('Y-m-d H:i'),
        ];
    }

    protected function formatLogDetail(ActivityLog $log): array
    {
        [$oldValues, $newValues] = $this->extractValues($log);

        return [
            ...$this->formatLog($log),
            'title' => "Log #{$log->id}",
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ];
    }

    protected function extractValues(ActivityLog $log): array
    {
        $oldValues = [];
        $newValues = [];

        foreach ($log->changes ?? [] as $change) {
            $field = $change['field'] ?? null;

            if (! $field) {
                continue;
            }

            if (array_key_exists('old', $change)) {
                $oldValues[$field] = $change['old'];
            }

            if (array_key_exists('new', $change)) {
                $newValues[$field] = $change['new'];
            }
        }

        if (isset($log->meta['snapshot'])) {
            $newValues = $log->meta['snapshot'];
        }

        if (! empty($log->meta['old_snapshot'])) {
            $oldValues = $log->meta['old_snapshot'];
        }

        return [$oldValues, $newValues];
    }

    protected function subjectLabel(ActivityLog $log): string
    {
        $modelName = class_basename($log->loggable_type);
        $subject = $log->loggable;

        if (! $subject) {
            return "{$modelName} #{$log->loggable_id}";
        }

        if (isset($subject->name)) {
            $name = trim($subject->name.' '.($subject->surname ?? ''));

            return "{$modelName} #{$subject->id} - {$name}";
        }

        $label = $subject->number
            ?? $subject->title
            ?? $subject->slug
            ?? $subject->id;

        return "{$modelName} #{$label}";
    }

    protected function userLabel(ActivityLog $log): ?string
    {
        if (! $log->user) {
            return null;
        }

        return trim($log->user->name.' '.($log->user->surname ?? ''));
    }
}
