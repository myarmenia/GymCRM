<?php

namespace App\Services\Users;

use App\Models\AttendanceSheet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class StaffAttendanceService
{
    private const LOCAL_TIMEZONE = 'Asia/Yerevan';

    public function lastAttendance(User $user): ?AttendanceSheet
    {
        return AttendanceSheet::query()
            ->where('relation_type', User::class)
            ->where('relation_id', $user->id)
            ->where('gym_id', $user->gym_id)
            ->latest('date')
            ->latest('id')
            ->first();
    }

    public function store(User $user, string $action, string $manualDateTime): AttendanceSheet
    {
        $date = Carbon::createFromFormat('Y-m-d\TH:i', $manualDateTime, self::LOCAL_TIMEZONE);
        $lastAttendance = $this->lastAttendance($user);

        if ($action === 'entry' && $lastAttendance?->direction === 'entry') {
            throw ValidationException::withMessages([
                'action' => 'An exit must be recorded before another entry.',
            ]);
        }

        if ($action === 'exit' && $lastAttendance?->direction !== 'entry') {
            throw ValidationException::withMessages([
                'action' => 'An entry must be recorded before an exit.',
            ]);
        }

        return AttendanceSheet::create([
            'relation_id' => $user->id,
            'relation_type' => User::class,
            'gym_id' => $user->gym_id,
            'entry_code' => 'manual',
            'date' => $date,
            'type' => 'manual',
            'direction' => $action,
            'online' => true,
        ]);
    }
}
