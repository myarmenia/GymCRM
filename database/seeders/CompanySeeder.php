<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\CompanyTranslation;
use App\Models\Gym;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $countryMap = [
            'Armenia' => 1,
            'RA' => 1,
            'ARM' => 1,
            'Georgia' => 62,
        ];

        $companies = [
            ['Tatev Tour Agency', '19 Nalbandyan street', 'Yerevan', 'Armenia', 0, '52 44 01', '52 44 02', null, 'Kamelia'],
            ['Armenia Travel +M', 'Vardanants 16', 'Yerevan', 'Armenia', 0, '56 36 67, 56 21 04', '535430', null, null],
            ['Arsis Tour', null, null, 'Yerevan', 0, '091 26 06 26', '25 24 08, 52 05 45', null, 'Mr. Varujan'],
            ['Saberatour', '32-38 Hanrapetutyan St.', 'Yerevan', 'Armenia', 0, '52 55 55', null, null, 'Lilit, Gagik'],
            ['Nork Marash Medical Center', null, 'Yerevan', 'Armenia', 0, '655821', null, null, 'Nazeli'],
            ['Roody Iraq', null, null, 'RA', 0, '53 30 92', null, null, 'Roody'],
            ['Hyur Service', '50, Nalbandyan str.', 'Yerevan', null, 0, '54 60 40, 54 60 80', null, null, null],
            ['Armen Voyage Avanture', null, null, 'RA', 0, null, null, null, null],
            ['Araz Ejmineh', null, null, null, 0, null, null, null, null],
            ['7 Days', '3/3 Baghramyan Ave.', 'Yerevan', 'Armenia', 0, '52 77 37', '58 00 91', null, null],

            ['Individual', null, null, 'ARM', 0, null, null, null, null],
            ['Omnes', null, 'Tbilisi', 'Georgia', 0, null, null, null, null],
            ['Football Federation', 'Khanjian str. 27', 'Yerevan', 'Armenia', 0, '54 88 85', null, null, null],
            ['Just Travel', 'Charents 1,home 12', 'Yerevan', 'RA', 0, '55 00 66,094 43 24 22', null, null, null],
            ['Babylon Tour', null, null, 'RA', 0, '094 93 92 55', null, null, 'Saman'],
            ['Baze Travel Agency', null, 'Yerevan', 'RA', 0, '091 57 53 05', null, null, 'Dro'],
            ['Noyan Tour', null, null, 'Yerevan', 0, '545439/ 52 37 87', null, null, 'Sofia'],
            ['Geographic', null, null, null, 0, null, null, null, null],
            ['Andako', null, null, null, 0, null, null, null, null],
            ['Nmar Iraq', null, null, null, 0, null, null, null, null],

            ['Blue Bell Tour', null, null, null, 0, null, null, null, null],
            ['Apada Tour', null, null, null, 0, null, null, null, null],
            ['Rest Tour', 'Rich Plaza,203 room', 'Yerevan', 'Armenia', 0, '53 00 11, 95 53 00 11', '39 68 75', 'rest.tour@yahoo.com', null],
            ['Aragast Travel', '46 Moldovakan street', 'Yerevan', 'RA', 62, '+374 93 68 17 68', null, null, null],
            ['Best Trip', null, null, 'RA', 0, '033 022 145 Anna', null, null, null],
            ['Visarm Tour', null, null, null, 0, '091 42-16-08', null, null, null],
            ['Artsakh FC', null, null, null, 0, null, null, null, null],
            ['Happy Holidays', null, null, null, 0, null, null, null, null],
            ['KTC Holidays', null, null, null, 0, null, null, null, null],
            ['Synergy International Systems', null, null, null, 0, null, null, null, null],

            ['Pars Travel', null, null, null, 0, null, null, null, null],
            ['Caucasian Dream', null, null, null, 0, null, null, null, null],
            ['Expedia', null, null, null, 0, null, null, null, null],
            ['Bronevik', null, null, null, 0, null, null, null, null],
            ['Booking', null, null, null, 0, null, null, null, null],
            ['Armroutes', null, null, null, 0, null, null, null, null],
            ['Sevana Tour', null, null, null, 0, null, null, null, null],
            ['Pegas Touristiks', null, null, null, 0, null, null, null, null],
            ['Just Travel', null, null, null, 0, null, null, null, null],
            ['Vaime Travel', null, null, null, 0, null, null, null, null],

            ['Ostrovok', null, null, null, 0, null, null, null, null],
            ['New Way', null, null, null, 0, null, null, null, null],
            ['Global Trading Solutions', null, null, null, 0, null, null, null, null],
            ['Geofit', null, null, null, 0, null, null, null, null],
            ['Armski', null, null, null, 0, null, null, null, null],
            ['Lion', null, null, null, 0, null, null, null, null],
            ['Find', null, null, null, 0, null, null, null, null],
            ['Armski', null, null, null, 0, null, null, null, null],
            ['Asa Seir', null, null, null, 0, null, null, null, null],
            ['Dreamy Travel', null, null, null, 0, null, null, null, null],

            ['Alma Tourism', null, null, null, 0, null, null, null, null],
            ['ITS', null, null, null, 0, null, null, null, null],
            ['Angels Tour', null, null, null, 0, null, null, null, null],
            ['Silk Road', null, null, null, 0, null, null, null, null],
            ['Jacobs', null, null, null, 0, null, null, null, null],
            ['Armenia Holiday', null, null, null, 0, null, null, null, null],
            ['Supra Smak', null, null, null, 0, null, null, null, null],
            ['Volleyball', null, null, null, 0, null, null, null, null],
            ['Ad Orientem', null, null, null, 0, null, null, null, null],
            ['Etno Armenia', null, null, null, 0, null, null, null, null],

            ['Ministry of Planning in Iraq', null, null, null, 0, null, null, null, null],
            ['GSY', null, null, null, 0, null, null, null, null],
            ['Sky Agency', null, null, null, 0, null, null, null, null],
            ['Company', null, null, null, 0, null, null, null, null],
        ];

        foreach ($companies as $item) {

            [$name, $address, $city, $state, $postal, $phone, $fax, $email, $responsible] = $item;
            $gymId = Gym::first()?->id;

            $company = Company::create([
                'gym_id' => $gymId ?? null,
                'country_id' => $countryMap[$state] ?? null,
                'postal_code' => $postal ?: null,
                'phone' => $phone,
                'fax' => $fax,
                'email' => $email,
                'responsible' => $responsible,
            ]);

            $city = $city ? trim($city) : null;

            // EN (основной)
            CompanyTranslation::create([
                'company_id' => $company->id,
                'locale' => 'en',
                'name' => $name,
                'address' => $address,
                'city' => $city,
            ]);

            // RU
            CompanyTranslation::create([
                'company_id' => $company->id,
                'locale' => 'ru',
                'name' => $name, // бренды обычно не переводятся
                'address' => $address,
                'city' => match ($city) {
                    'Yerevan' => 'Ереван',
                    'Tbilisi' => 'Тбилиси',
                    default => $city,
                },
            ]);

            // HY
            CompanyTranslation::create([
                'company_id' => $company->id,
                'locale' => 'hy',
                'name' => match ($name) {
                    'Tatev Tour Agency' => 'Տաթև Տուր գործակալություն',
                    'Armenia Travel +M' => 'Արմենիա Թրավել +Մ',
                    'Arsis Tour' => 'Արսիս Տուր',
                    'Hyur Service' => 'Հյուր Սերվիս',
                    'Rest Tour' => 'Ռեստ Տուր',
                    'Best Trip' => 'Բեստ Թրիպ',
                    'Silk Road' => 'Մետաքսի ճանապարհ',
                    default => $name,
                },
                'address' => $address,
                'city' => match ($city) {
                    'Yerevan' => 'Երևան',
                    'Tbilisi' => 'Թբիլիսի',
                    default => $city,
                },
            ]);
        }
    }
}
