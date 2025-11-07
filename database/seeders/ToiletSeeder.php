<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ToiletSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- Ensure base data exists (roles, a host user, categories, wilayas) ----------
        $now = now();

        // Roles
        $roleIdByCode = DB::table('roles')->pluck('id', 'code')->all();
        foreach (['admin', 'host', 'user'] as $code) {
            if (!isset($roleIdByCode[$code])) {
                $id = DB::table('roles')->insertGetId(['code' => $code, 'created_at' => $now, 'updated_at' => $now]);
                $roleIdByCode[$code] = $id;
            }
        }

        // Host users (we’ll create one if none exist)
        $hostRoleId = $roleIdByCode['host'];
        $hostIds = DB::table('users')->where('role_id', $hostRoleId)->pluck('id')->all();
        if (empty($hostIds)) {
            $hostIds[] = DB::table('users')->insertGetId([
                'name' => 'Default Host',
                'email' => 'host@example.com',
                'password' => Hash::make('password'),
                'password_plain_text' => 'password',
                'role_id' => $hostRoleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Categories (create a minimal set if empty)
        if (DB::table('toilet_categories')->count() === 0) {
            DB::table('toilet_categories')->insert([
                ['code' => 'public',     'icon' => 'mdi:toilet',               'en' => 'Public Toilet',     'fr' => 'Toilette publique',  'ar' => 'مرحاض عمومي',   'created_at' => $now, 'updated_at' => $now],
                ['code' => 'restaurant', 'icon' => 'mdi:silverware-fork-knife','en' => 'Restaurant / Café', 'fr' => 'Restaurant / Café',  'ar' => 'مطعم / مقهى', 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'station',    'icon' => 'mdi:gas-station',          'en' => 'Gas Station',       'fr' => 'Station-service',    'ar' => 'محطة بنزين',   'created_at' => $now, 'updated_at' => $now],
                ['code' => 'mall',       'icon' => 'mdi:store',                 'en' => 'Mall',              'fr' => 'Centre commercial',  'ar' => 'مركز تجاري',   'created_at' => $now, 'updated_at' => $now],
                ['code' => 'office',     'icon' => 'mdi:office-building',       'en' => 'Office',            'fr' => 'Bureau',             'ar' => 'مكتب',         'created_at' => $now, 'updated_at' => $now],
            ]);
        }
        $categoryIds = DB::table('toilet_categories')->pluck('id')->all();

        // Wilayas (create a small baseline if empty)
        if (DB::table('wilayas')->count() === 0) {
            $seedWilayas = [
                // number, code, en, fr, ar, approx center (lat,lng)
                [16, 'ALG', 'Algiers',    'Alger',      'الجزائر',   36.753769, 3.058756],
                [31, 'ORN', 'Oran',       'Oran',       'وهران',     35.697070, -0.630799],
                [9,  'BLD', 'Blida',      'Blida',      'البليدة',   36.4701,   2.8277],
                [25, 'CON', 'Constantine','Constantine','قسنطينة',   36.3650,   6.6147],
                [23, 'ANN', 'Annaba',     'Annaba',     'عنابة',     36.9020,   7.7566],
                [19, 'SET', 'Sétif',      'Sétif',      'سطيف',      36.1911,   5.4137],
                [5,  'BAT', 'Batna',      'Batna',      'باتنة',     35.5550,   6.1741],
                [35, 'BOU', 'Boumerdes',  'Boumerdès',  'بومرداس',   36.7666,   3.4772],
            ];
            foreach ($seedWilayas as [$num,$code,$en,$fr,$ar,$lat,$lng]) {
                DB::table('wilayas')->insert([
                    'number' => $num, 'code' => $code, 'en' => $en, 'fr' => $fr, 'ar' => $ar,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // Build a centers map from existing wilayas (try to map known codes to rough centers; unknown get Algiers)
        $wilayas = DB::table('wilayas')->select('id','code','en','fr','ar')->get();
        $wilayaIds = $wilayas->pluck('id')->all();

        // Hard-coded approximate centers for a few codes; others fall back to Algiers
        $centersByCode = [
            'ALG' => [36.753769, 3.058756],
            'ORN' => [35.697070, -0.630799],
            'BLD' => [36.4701, 2.8277],
            'CON' => [36.3650, 6.6147],
            'ANN' => [36.9020, 7.7566],
            'SET' => [36.1911, 5.4137],
            'BAT' => [35.5550, 6.1741],
            'BOU' => [36.7666, 3.4772],
        ];
        $defaultCenter = $centersByCode['ALG'];

        $wilayaCenterById = [];
        foreach ($wilayas as $w) {
            $wilayaCenterById[$w->id] = $centersByCode[$w->code] ?? $defaultCenter;
        }

        // ---------- Generate 600 toilets ----------
        $total = 600;
        $batchSize = 200; // insert in chunks
        $faker = fake('en_US');

        $accessMethods = ['public', 'code', 'staff', 'key', 'app'];
        $pricingModels = ['flat', 'per-visit', 'per-30-min', 'per-60-min'];

        $rows = [];
        for ($i = 1; $i <= $total; $i++) {
            $ownerId = $faker->randomElement($hostIds);
            $categoryId = $faker->randomElement($categoryIds);
            $wilayaId = $faker->randomElement($wilayaIds);

            // Jitter around the wilaya center to keep coords plausible
            [$baseLat, $baseLng] = $wilayaCenterById[$wilayaId] ?? $defaultCenter;
            $lat = $baseLat + $faker->randomFloat(6, -0.08, 0.08); // ~ up to ~9km jitter
            $lng = $baseLng + $faker->randomFloat(6, -0.08, 0.08);

            $isFree = $faker->boolean(70); // 70% free
            $priceCents = $isFree ? null : $faker->randomElement([500, 800, 1000, 1500, 2000, 2500]);
            $pricingModel = $isFree ? null : $faker->randomElement($pricingModels);

            $access = $faker->randomElement($accessMethods);
            $capacity = $faker->numberBetween(1, 6);
            $isUnisex = $faker->boolean(60);

            $status = $faker->randomElement(['pending', 'active', 'suspended']);
            // Bias toward active so the app looks populated
            if ($faker->boolean(75)) $status = 'active';

            $avgRating = $status === 'active'
                ? round($faker->randomFloat(2, 3.2, 4.9), 2)
                : 0.00;
            $reviewsCount = $status === 'active'
                ? $faker->numberBetween(0, 48)
                : 0;
            $photosCount = $faker->numberBetween(0, 6);

            $rows[] = [
                'owner_id'            => $ownerId,
                'toilet_category_id'  => $categoryId,
                'name'                => $this->placeName($faker),
                'description'         => $faker->boolean(65) ? $faker->sentence(12) : null,
                'phone_numbers'       => json_encode($this->phones($faker)),
                'lat'                 => $lat,
                'lng'                 => $lng,
                'address_line'        => $faker->streetAddress(),
                'wilaya_id'           => $wilayaId,
                'place_hint'          => $faker->boolean(50) ? $faker->randomElement(['Near entrance', 'Basement level', '2nd floor', 'Back of the café', 'Left wing']) : null,
                'access_method'       => $access,
                'capacity'            => $capacity,
                'is_unisex'           => $isUnisex,
                'amenities'           => json_encode($this->amenities($faker)),
                'rules'               => json_encode($this->rules($faker)),
                'is_free'             => $isFree,
                'price_cents'         => $priceCents,
                'pricing_model'       => $pricingModel,
                'status'              => $status,
                'avg_rating'          => $avgRating,
                'reviews_count'       => $reviewsCount,
                'photos_count'        => $photosCount,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];

            if (count($rows) >= $batchSize) {
                DB::table('toilets')->insert($rows);
                $rows = [];
            }
        }

        if (!empty($rows)) {
            DB::table('toilets')->insert($rows);
        }
    }

    private function placeName($faker): string
    {
        // A mix to sound like real places
        $prefix = $faker->randomElement(['Public', 'City', 'Central', 'Municipal', 'Green', 'Family', 'River']);
        $type   = $faker->randomElement(['Toilet', 'Restroom', 'WC', 'Facilities']);
        $extra  = $faker->boolean(40) ? ' - '.$faker->randomElement(['Park', 'Mall', 'Station', 'Café', 'Office']) : '';
        return "{$prefix} {$type}{$extra}";
    }

    private function phones($faker): array
    {
        $count = $faker->boolean(60) ? $faker->numberBetween(1, 2) : 0;
        $arr = [];
        for ($i = 0; $i < $count; $i++) {
            $arr[] = '+213'.(string)$faker->numberBetween(550000000, 799999999);
        }
        return $arr;
    }

    private function amenities($faker): array
    {
        $pool = ['paper', 'soap', 'bidet', 'hand_dryer', 'water_sink', 'disabled_access', 'baby_change', 'mirror'];
        shuffle($pool);
        return array_slice($pool, 0, $faker->numberBetween(1, 5));
    }

    private function rules($faker): array
    {
        $pool = ['no_smoking', 'for_customers_only', 'no_pets', 'card_only', 'staff_assistance'];
        shuffle($pool);
        return array_slice($pool, 0, $faker->numberBetween(0, 3));
    }
}
