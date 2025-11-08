<?php

namespace Database\Seeders;

use App\Models\ToiletCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ToiletCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('toilet_categories')->truncate(); // erase existing data

        // idempotent upsert by 'code'
        $rows = [
            // ⚑ Very common sources
            ['code' => 'public',     'icon' => 'toilet',                 'en' => 'Public',        'fr' => 'Public',              'ar' => 'عمومي'],
            ['code' => 'cafe',       'icon' => 'coffee',                 'en' => 'Café',          'fr' => 'Café',                'ar' => 'مقهى'],
            ['code' => 'restaurant', 'icon' => 'silverware-fork-knife',  'en' => 'Restaurant',    'fr' => 'Restaurant',          'ar' => 'مطعم'],
            ['code' => 'mall',       'icon' => 'shopping',               'en' => 'Mall',          'fr' => 'Centre commercial',   'ar' => 'مركز تجاري'],
            ['code' => 'gas_station', 'icon' => 'gas-station',            'en' => 'Gas station',   'fr' => 'Station-service',     'ar' => 'محطة وقود'],
            ['code' => 'hotel',      'icon' => 'office-building-outline', 'en' => 'Hotel',         'fr' => 'Hôtel',               'ar' => 'فندق'],
            ['code' => 'hospital',   'icon' => 'hospital-building',      'en' => 'Hospital',      'fr' => 'Hôpital',             'ar' => 'مستشفى'],
            ['code' => 'park',       'icon' => 'tree',                   'en' => 'Park',          'fr' => 'Parc',                'ar' => 'حديقة'],

            // Transport (keep if your data has these)
            ['code' => 'bus_stop',   'icon' => 'bus-stop',               'en' => 'Bus stop',      'fr' => 'Arrêt de bus',        'ar' => 'موقف حافلات'],
            ['code' => 'metro',      'icon' => 'subway-variant',         'en' => 'Metro',         'fr' => 'Métro',               'ar' => 'مترو'],
            ['code' => 'station',    'icon' => 'train',                  'en' => 'Train station', 'fr' => 'Gare',                'ar' => 'محطة'],

            // Public facilities / institutions
            ['code' => 'library',    'icon' => 'library',                'en' => 'Library',       'fr' => 'Bibliothèque',        'ar' => 'مكتبة'],
            ['code' => 'university', 'icon' => 'school',                 'en' => 'University',    'fr' => 'Université',          'ar' => 'جامعة'],
            ['code' => 'government', 'icon' => 'office-building',        'en' => 'Government',    'fr' => 'Administration',      'ar' => 'إدارة'],

            // Optional outdoor/leisure (keep if relevant to your coverage)
            ['code' => 'beach',      'icon' => 'beach',                  'en' => 'Beach',         'fr' => 'Plage',               'ar' => 'شاطئ'],
            ['code' => 'parking',    'icon' => 'parking',                'en' => 'Parking',       'fr' => 'Parking',             'ar' => 'موقف سيارات'],

            // Region-specific (nice add in DZ)
            ['code' => 'mosque',     'icon' => 'mosque',                 'en' => 'Mosque',        'fr' => 'Mosquée',             'ar' => 'مسجد'],
            // ['code' => 'coworking','icon' => 'briefcase',             'en' => 'Coworking',     'fr' => 'Coworking',           'ar' => 'كووركينغ'], // include if you have data
        ];


        foreach ($rows as $r) {
            ToiletCategory::updateOrCreate(['code' => $r['code']], $r);
        }
    }
}
