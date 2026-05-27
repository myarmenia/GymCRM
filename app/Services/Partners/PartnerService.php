<?php

namespace App\Services\Partners;

use App\Interfaces\Partners\PartnerInterface;
use Illuminate\Support\Facades\Auth;

class PartnerService
{

    public function __construct(protected PartnerInterface $partnerRepository)
    {}

    /**
     * Բերում է միայն ընթացիկ օգտատիրոջ հյուրանոցի գործընկերներին
     */
    public function all(int $perPage = 10)
    {
        $gymId = auth()->user()->gym_id;

        // 💡 Ուղիղ Մոդելին դիմելու փոխարեն կանչում ենք Ռեպոզիտորիայի մեր նոր մեթոդը
        return $this->partnerRepository->getByGymWithPagination($gymId, $perPage);
    }

    public function find(int $id)
    {
        return $this->partnerRepository->find($id);
    }

    public function create(array $data)
    {
        $data['gym_id'] = auth()->user()->gym_id;

        return $this->partnerRepository->create($data);
    }

    public function update(int $id, array $data)
    {
        // 💡 Թարմացումը նույնպես անում ենք ռեպոզիտորիայի միջոցով
        return $this->partnerRepository->update($id, $data);
    }

    public function delete(int $id)
    {
        // 💡 Ջնջումը նույնպես անում ենք ռեպոզիտորիայի միջոցով
        return $this->partnerRepository->delete($id);
    }
}
