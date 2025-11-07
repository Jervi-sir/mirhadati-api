<?php

namespace Database\Seeders;

use App\Models\Wilaya;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WilayaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // Helper: compute bbox (very good approximation)
        $bbox = function (float $lat, float $lng, float $radiusKm) {
            $latDelta = $radiusKm / 111.0; // ~111 km per 1° latitude
            $lngDelta = $radiusKm / (111.0 * max(0.1, cos(deg2rad($lat)))); // avoid div/0 near poles
            return [
                'min_lat' => $lat - $latDelta,
                'max_lat' => $lat + $latDelta,
                'min_lng' => $lng - $lngDelta,
                'max_lng' => $lng + $lngDelta,
            ];
        };

        $rows = [
            // number, en, fr, ar, center_lat, center_lng, default_radius_km
            [ 1, 'Adrar', 'Adrar', 'أدرار', 27.874,  -0.293,  90 ],
            [ 2, 'Chlef', 'Chlef', 'الشلف', 36.165,   1.334,  30 ],
            [ 3, 'Laghouat', 'Laghouat', 'الأغواط', 33.807,   2.882,  45 ],
            [ 4, 'Oum El Bouaghi', 'Oum El Bouaghi', 'أم البواقي', 35.875,  7.113,  35 ],
            [ 5, 'Batna', 'Batna', 'باتنة', 35.555,   6.174,  40 ],
            [ 6, 'Béjaïa', 'Béjaïa', 'بجاية', 36.753,   5.055,  30 ],
            [ 7, 'Biskra', 'Biskra', 'بسكرة', 34.850,   5.728,  40 ],
            [ 8, 'Béchar', 'Béchar', 'بشار', 31.611,  -2.230,  70 ],
            [ 9, 'Blida', 'Blida', 'البليدة', 36.470,   2.827,  30 ],
            [10, 'Bouira', 'Bouira', 'البويرة', 36.374,   3.902,  35 ],
            [11, 'Tamanrasset', 'Tamanrasset', 'تمنراست', 22.785,   5.522, 120 ],
            [12, 'Tébessa', 'Tébessa', 'تبسة', 35.404,   8.124,  40 ],
            [13, 'Tlemcen', 'Tlemcen', 'تلمسان', 34.878,  -1.315,  35 ],
            [14, 'Tiaret', 'Tiaret', 'تيارت', 35.371,   1.317,  40 ],
            [15, 'Tizi Ouzou', 'Tizi Ouzou', 'تيزي وزو', 36.711,   4.046,  30 ],
            [16, 'Algiers', 'Alger', 'الجزائر', 36.754,   3.059,  30 ],
            [17, 'Djelfa', 'Djelfa', 'الجلفة', 34.667,   3.250,  45 ],
            [18, 'Jijel', 'Jijel', 'جيجل', 36.821,   5.766,  30 ],
            [19, 'Sétif', 'Sétif', 'سطيف', 36.191,   5.414,  35 ],
            [20, 'Saïda', 'Saïda', 'سعيدة', 34.830,   0.152,  40 ],
            [21, 'Skikda', 'Skikda', 'سكيكدة', 36.876,   6.909,  30 ],
            [22, 'Sidi Bel Abbès', 'Sidi Bel Abbès', 'سيدي بلعباس', 35.206,  -0.641,  35 ],
            [23, 'Annaba', 'Annaba', 'عنابة', 36.900,   7.766,  30 ],
            [24, 'Guelma', 'Guelma', 'قالمة', 36.466,   7.433,  30 ],
            [25, 'Constantine', 'Constantine', 'قسنطينة', 36.365,   6.615,  30 ],
            [26, 'Médéa', 'Médéa', 'المدية', 36.264,   2.754,  35 ],
            [27, 'Mostaganem', 'Mostaganem', 'مستغانم', 35.931,   0.089,  30 ],
            [28, 'M\'Sila', 'M\'Sila', 'المسيلة', 35.706,   4.542,  40 ],
            [29, 'Mascara', 'Mascara', 'معسكر', 35.394,   0.138,  35 ],
            [30, 'Ouargla', 'Ouargla', 'ورقلة', 31.950,   5.316,  60 ],
            [31, 'Oran', 'Oran', 'وهران', 35.697,  -0.631,  30 ],
            [32, 'El Bayadh', 'El Bayadh', 'البيض', 32.483,   1.950,  60 ],
            [33, 'Illizi', 'Illizi', 'إليزي', 26.500,   8.470, 120 ],
            [34, 'Bordj Bou Arréridj', 'Bordj Bou Arréridj', 'برج بوعريريج', 36.073,   4.763,  35 ],
            [35, 'Boumerdès', 'Boumerdès', 'بومرداس', 36.766,   3.477,  30 ],
            [36, 'El Tarf', 'El Tarf', 'الطارف', 36.757,   8.313,  30 ],
            [37, 'Tindouf', 'Tindouf', 'تندوف', 27.670,  -8.130, 120 ],
            [38, 'Tissemsilt', 'Tissemsilt', 'تيسمسيلت', 35.607,   1.810,  35 ],
            [39, 'El Oued', 'El Oued', 'الوادي', 33.368,   6.867,  60 ],
            [40, 'Khenchela', 'Khenchela', 'خنشلة', 35.436,   7.143,  35 ],
            [41, 'Souk Ahras', 'Souk Ahras', 'سوق أهراس', 36.286,   7.951,  30 ],
            [42, 'Tipaza', 'Tipaza', 'تيبازة', 36.589,   2.448,  30 ],
            [43, 'Mila', 'Mila', 'ميلة', 36.450,   6.270,  30 ],
            [44, 'Aïn Defla', 'Aïn Defla', 'عين الدفلى', 36.2648,  1.968,  30 ],
            [45, 'Naâma', 'Naâma', 'النعامة', 33.267,  -0.313,  60 ],
            [46, 'Aïn Témouchent', 'Aïn Témouchent', 'عين تموشنت', 35.297,  -1.140,  30 ],
            [47, 'Ghardaïa', 'Ghardaïa', 'غرداية', 32.490,   3.673,  60 ],
            [48, 'Relizane', 'Relizane', 'غليزان', 35.737,   0.556,  35 ],
            // 2019 new wilayas
            [49, 'Timimoun', 'Timimoun', 'تيميمون', 29.263,   0.230,  90 ],
            [50, 'Bordj Badji Mokhtar', 'Bordj Badji Mokhtar', 'برج باجي مختار', 21.327,  -0.955, 120 ],
            [51, 'Ouled Djellal', 'Ouled Djellal', 'أولاد جلال', 34.432,   5.066,  50 ],
            [52, 'Béni Abbès', 'Béni Abbès', 'بني عباس', 30.130,  -2.170,  90 ],
            [53, 'In Salah', 'In Salah', 'عين صالح', 27.190,   2.460, 120 ],
            [54, 'In Guezzam', 'In Guezzam', 'عين قزّام', 19.571,   5.774, 120 ],
            [55, 'Touggourt', 'Touggourt', 'تقرت', 33.103,   6.066,  60 ],
            [56, 'Djanet', 'Djanet', 'جانت', 24.553,   9.484, 120 ],
            [57, 'El M\'Ghair', 'El M\'Ghair', 'المغير', 33.951,   5.924,  60 ],
            [58, 'El Menia', 'El Menia', 'المنيعة', 30.580,   2.890,  90 ],
        ];

        foreach ($rows as [$number, $en, $fr, $ar, $clat, $clng, $rad]) {
            $bb = $bbox($clat, $clng, $rad);

            Wilaya::updateOrCreate(
                ['number' => (int)$number],
                [
                    'code'               => $en,
                    'en'                 => $en,
                    'fr'                 => $fr,
                    'ar'                 => $ar,
                    'center_lat'         => $clat,
                    'center_lng'         => $clng,
                    'default_radius_km'  => $rad,
                    'min_lat'            => $bb['min_lat'],
                    'max_lat'            => $bb['max_lat'],
                    'min_lng'            => $bb['min_lng'],
                    'max_lng'            => $bb['max_lng'],
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ]
            );
        }
    }
}
