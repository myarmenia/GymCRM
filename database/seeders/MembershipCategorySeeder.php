<?php

namespace Database\Seeders;

use App\Models\Gym;
use App\Models\MembershipCategory;
use Illuminate\Database\Seeder;

class MembershipCategorySeeder extends Seeder
{
    public function run(): void
    {
        $gym = Gym::first();

        if (!$gym) {
            $this->command?->warn('No gyms found. Membership categories were not seeded.');

            return;
        }

        $categories = [
            [
                'slug' => 'gym',
                'translations' => [
                    'ru' => [
                        'name' => 'Тренажёрный зал',
                        'description' => 'Абонементы для тренажёрного зала',
                    ],
                    'en' => [
                        'name' => 'Gym',
                        'description' => 'Gym memberships',
                    ],
                    'hy' => [
                        'name' => 'Մարզասրահ',
                        'description' => 'Մարզասրահի անդամակցություններ',
                    ],
                ],
            ],
            [
                'slug' => 'pool',
                'translations' => [
                    'ru' => [
                        'name' => 'Бассейн',
                        'description' => 'Абонементы для бассейна',
                    ],
                    'en' => [
                        'name' => 'Pool',
                        'description' => 'Pool memberships',
                    ],
                    'hy' => [
                        'name' => 'Լողավազան',
                        'description' => 'Լողավազանի անդամակցություններ',
                    ],
                ],
            ],
            [
                'slug' => 'group-classes',
                'translations' => [
                    'ru' => [
                        'name' => 'Групповые занятия',
                        'description' => 'Абонементы на групповые занятия',
                    ],
                    'en' => [
                        'name' => 'Group Classes',
                        'description' => 'Group class memberships',
                    ],
                    'hy' => [
                        'name' => 'Խմբային պարապմունքներ',
                        'description' => 'Խմբային պարապմունքների անդամակցություններ',
                    ],
                ],
            ],
            [
                'slug' => 'personal-training',
                'translations' => [
                    'ru' => [
                        'name' => 'Персональные тренировки',
                        'description' => 'Абонементы на персональные тренировки',
                    ],
                    'en' => [
                        'name' => 'Personal Training',
                        'description' => 'Personal training memberships',
                    ],
                    'hy' => [
                        'name' => 'Անհատական մարզումներ',
                        'description' => 'Անդամակցություններ անհատական մարզումների համար',
                    ],
                ],
            ],
        ];

        foreach ($categories as $item) {

            $category = MembershipCategory::firstOrCreate(
                [
                    'gym_id' => $gym->id,
                    'slug' => $item['slug'],
                ],
                [
                    'active' => true,
                ]
            );

            foreach ($item['translations'] as $locale => $translation) {

                $category->translations()->updateOrCreate(
                    [
                        'locale' => $locale,
                    ],
                    [
                        'name' => $translation['name'],
                        'description' => $translation['description'],
                    ]
                );
            }
        }
    }
}
