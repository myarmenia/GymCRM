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

    public function paginate(int $perPage = 10, array $filters = [])
    {
        $user = auth()->user();
        $query = EntryCode::orderBy('id', 'desc');

        if (!$user->hasRole('owner')) {
            $userGymId = $user->gym_id ?? null;
            if ($userGymId) {
                $query->where('gym_id', $userGymId);
            }
        }

        $query->with('gym');

        return $query
            ->filter($this->normalizeFilters($filters))
            ->paginate($perPage)
            ->withQueryString();
    }

    protected function normalizeFilters(array $filters): array
    {
        unset($filters['page'], $filters['per_page']);

        return array_intersect_key($filters, array_flip([
            'type',
            'gym_id',
            'status',
        ]));
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

    public function getByGymId(int $gymId, ?int $currentId = null)
    {
        return EntryCode::where('gym_id', $gymId)
            ->where(function ($query) use ($currentId) {
                $query->where('activation', 0);
                if ($currentId) {
                    $query->orWhere('id', $currentId);
                }
            })
            ->with('gym:id,name')
            ->get(['id', 'token', 'gym_id', 'type']);
    }

    public function activate(int $entryCodeId, bool $active = true): void
    {
        EntryCode::where('id', $entryCodeId)->update(['activation' => $active ? 1 : 0]);
    }
}
