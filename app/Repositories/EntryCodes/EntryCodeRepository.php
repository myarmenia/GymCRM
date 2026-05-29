<?php

namespace App\Repositories\EntryCodes;

use App\Interfaces\EntryCodes\EntryCodeInterface;
use App\Models\EntryCode;
use App\Models\Staff;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EntryCodeRepository implements EntryCodeInterface
{
    public function getAll()
    {
        return EntryCode::orderBy('id', 'desc')->get();
    }

    public function paginate(int $perPage = 10, ?int $gymId = null)
    {
        $user = auth()->user();
        $query = EntryCode::orderBy('id', 'desc');

        // ֆիլտրացիա (եթե կա gymId, կամ ըստ դերի)
        if ($gymId !== null) {
            $query->where('gym_id', $gymId);
        } elseif (!$user->hasRole('owner')) {
            $userGymId = $user->gym_id ?? null;
            if ($userGymId) {
                $query->where('gym_id', $userGymId);
            }
        }

        // ԿԱՐԵՎՈՐ է սա՝ միանգամից բերել gym-ը
        $query->with('gym');

        return $query->paginate($perPage);
    }

    public function find(int $id)
    {
        return EntryCode::with('gym')->findOrFail($id);
    }

    public function create(array $data)
    {
        return EntryCode::create($data);
    }

    public function update(int $id, array $data)
    {
        $entryCode = $this->find($id);
        $entryCode->update($data);
        return $entryCode;
    }

    public function delete(int $id)
    {
        $entryCode = $this->find($id);
        return $entryCode->delete();
    }

    private function getUserGymId($user)
    {
        if ($user->gym_id) {
            return $user->gym_id;
        }

        // if ($user->hasRole('manager')) {
        //     $staff = Staff::where('user_id', $user->id)->first();
        //     return $staff->gym_id ?? null;
        // }

        return null;
    }
}