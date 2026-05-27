<?php

namespace App\Services;

use App\Models\ReservationType;

class ReservationTypeService
{
    /**
     * Get all reservation types for dropdown/display
     */
    public function getAllTypes(): array
    {
        $types = ReservationType::with('translations')->get();

        $result = [];
        foreach ($types as $type) {
            $translations = [];
            foreach ($type->translations as $translation) {
                $translations[$translation->locale] = $translation->name;
            }

            $result[] = [
                'id' => $type->id,
                'slug' => $type->slug,
                'name' => $translations,
                'translations' => $translations
            ];
        }

        return $result;
    }

    /**
     * Get reservation type by slug
     */
    public function getBySlug(string $slug): ?ReservationType
    {
        return ReservationType::where('slug', $slug)->first();
    }

    public function getById(string $id): ?ReservationType
    {
        return ReservationType::where('id', $id)->first();
    }

    /**
     * Get all types as key-value pairs for frontend
     */
    public function getTypesForFrontend(): array
    {
        $types = ReservationType::with('translations')->get();

        $result = [];
        foreach ($types as $type) {
            $result[$type->slug] = [
                'id' => $type->id,
                'slug' => $type->slug,
                'label' => $type->translations->first()?->name ?? $type->slug,
                'class' => $this->getTypeClass($type->slug),
                'icon' => $this->getTypeIcon($type->slug)
            ];
        }

        return $result;
    }

    /**
     * Get CSS class for reservation type badge
     */
    private function getTypeClass(string $slug): string
    {
        return match ($slug) {
            'reservation' => 'bg-label-warning',
            'checkin' => 'bg-label-info',
            'checkout' => 'bg-label-success',
            'cancelled' => 'bg-label-danger',
            default => 'bg-label-secondary'
        };
    }

    /**
     * Get icon for reservation type
     */
    private function getTypeIcon(string $slug): string
    {
        return match ($slug) {
            'reservation' => 'ti-calendar',
            'checkin' => 'ti-home',
            'checkout' => 'ti-door-exit',
            'cancelled' => 'ti-ban',
            default => 'ti-info-circle'
        };
    }
}
