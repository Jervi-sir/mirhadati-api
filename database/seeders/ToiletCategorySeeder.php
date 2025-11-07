<?php

namespace Database\Seeders;

use App\Models\ToiletCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ToiletCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // idempotent upsert by 'code'
        $rows = [
            ['code' => 'public',    'icon' => 'public',   'en' => 'Public',     'fr' => 'Public',      'ar' => 'عمومي'],
            ['code' => 'cafe',      'icon' => 'coffee',   'en' => 'Café',       'fr' => 'Café',        'ar' => 'مقهى'],
            ['code' => 'restaurant','icon' => 'utensils', 'en' => 'Restaurant', 'fr' => 'Restaurant',  'ar' => 'مطعم'],
            ['code' => 'mall',      'icon' => 'bag',      'en' => 'Mall',       'fr' => 'Centre co',   'ar' => 'مركز تجاري'],
            ['code' => 'station',   'icon' => 'train',    'en' => 'Station',    'fr' => 'Gare',        'ar' => 'محطة'],
            ['code' => 'coworking', 'icon' => 'work',     'en' => 'Coworking',  'fr' => 'Coworking',   'ar' => 'كووركينغ'],
        ];

        foreach ($rows as $r) {
            ToiletCategory::updateOrCreate(['code' => $r['code']], $r);
        }

    }
}
