<?php

namespace Database\Seeders;

use App\Models\District;
use Illuminate\Database\Seeder;

class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        $districts = [
            'Bektemir',
            'Chilanzar',
            'Hamza',
            'Mirobod',
            'Mirzo Ulugbek',
            'Sergeli',
            'Shayhontohur',
            'Olmazor',
            'Uchtepa',
            'Yunusabad',
            'Yakkasaray',
            'Yashnabad',
        ];

        foreach ($districts as $district) {
            District::create(['name' => $district]);
        }
    }
}
