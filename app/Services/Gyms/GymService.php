<?php

namespace App\Services\Gyms;

use App\Interfaces\Gyms\GymInterface;
use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GymService
{
    // Ավելացնում ենք FileUploadService-ը constructor-ի մեջ
    public function __construct(
        protected GymInterface $gymRepository,
        protected FileUploadService $fileUploadService
    ) {}

    public function getAll()
    {
        return $this->gymRepository->getAll();
    }

    public function getAllPaginated()
    {
        return $this->gymRepository->paginate(10);
    }

    public function find(int $id)
    {
        return $this->gymRepository->find($id);
    }

    public function create(array $data)
    {

        $folder = 'gyms/logos';

        if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
            $data['logo'] = $this->fileUploadService->upload($data['logo'], $folder);
        }

        return $this->gymRepository->create($data);
    }

    public function update(int $id, array $data)
    {
        $gym = $this->find($id);
        $folder = 'gyms/logos';

        if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {

            if ($gym->logo && Storage::disk('public')->exists($gym->logo)) {
                Storage::disk('public')->delete($gym->logo);
            }

            $data['logo'] = $this->fileUploadService->upload($data['logo'], $folder);
        } else {
            $data['logo'] = $data['logo'] ?? $gym->logo;
        }

        return $this->gymRepository->update($id, $data);
    }


}
