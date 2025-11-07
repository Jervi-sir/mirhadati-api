<?php

namespace Database\Seeders;

use App\Models\Toilet;
use App\Models\ToiletPhoto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ToiletAndPhotoSeeder extends Seeder
{
    // Tune these if you want
    private int $minToilets = 600;
    private int $targetTotalPhotos = 2000;

    public function run(): void
    {
        DB::transaction(function () {
            // 1) Ensure we have at least N toilets
            $existingCount = Toilet::count();
            $need = max(0, $this->minToilets - $existingCount);

            if ($need > 0) {
                // NOTE: assumes you already have wilayas & categories populated.
                // If not, plug in factories/ids of your choice.
                Toilet::factory($need)->create();
            }

            // Refresh list
            $toilets = Toilet::query()->select('id')->pluck('id')->all();
            $toiletCount = count($toilets);
            if ($toiletCount === 0) return;

            // 2) Count how many photos already exist per toilet (in case you re-run)
            $existingPhotos = ToiletPhoto::query()
                ->selectRaw('toilet_id, COUNT(*) as c, MAX(CASE WHEN is_cover THEN 1 ELSE 0 END) as has_cover')
                ->groupBy('toilet_id')
                ->pluck('c', 'toilet_id');

            $hasCover = ToiletPhoto::query()
                ->selectRaw('toilet_id, MAX(CASE WHEN is_cover THEN 1 ELSE 0 END) as has_cover')
                ->groupBy('toilet_id')
                ->pluck('has_cover', 'toilet_id');

            $totalExisting = (int) ToiletPhoto::count();

            // 3) Ensure at least 1 photo per toilet (and a cover)
            $toInsert = [];
            foreach ($toilets as $tid) {
                $count = (int) ($existingPhotos[$tid] ?? 0);
                $cover = (int) ($hasCover[$tid] ?? 0);

                if ($count === 0) {
                    // Insert exactly 1 as cover
                    $toInsert[] = $this->makePhotoRow($tid, true);
                    $totalExisting++;
                } elseif ($cover === 0) {
                    // Mark one as cover if none (upgrade first)
                    $toInsert[] = $this->makePhotoRow($tid, true);
                    $totalExisting++;
                }
            }

            // Bulk insert initial fixes
            if (!empty($toInsert)) {
                foreach (array_chunk($toInsert, 1000) as $chunk) {
                    ToiletPhoto::insert($chunk);
                }
                $toInsert = [];
            }

            // 4) Distribute remaining photos randomly to approach targetTotalPhotos
            $remaining = max(0, $this->targetTotalPhotos - $totalExisting);
            if ($remaining > 0) {
                // Create a pool to sample toilet ids from
                // Bias a bit so some toilets get more photos
                $pool = [];
                foreach ($toilets as $tid) {
                    // Weight: 1..4 entries per toilet for uneven distribution
                    $w = random_int(1, 4);
                    for ($i = 0; $i < $w; $i++) $pool[] = $tid;
                }

                $batch = [];
                for ($i = 0; $i < $remaining; $i++) {
                    $tid = $pool[random_int(0, count($pool) - 1)];
                    $batch[] = $this->makePhotoRow($tid, false);

                    if (count($batch) === 1000) {
                        ToiletPhoto::insert($batch);
                        $batch = [];
                    }
                }
                if (!empty($batch)) {
                    ToiletPhoto::insert($batch);
                }
            }

            // 5) One-shot sync of toilets.photos_count
            DB::statement("
                UPDATE toilets t
                SET photos_count = sub.cnt
                FROM (
                    SELECT toilet_id, COUNT(*)::int AS cnt
                    FROM toilet_photos
                    GROUP BY toilet_id
                ) sub
                WHERE sub.toilet_id = t.id
            ");

            // 6) (Optional) ensure exactly one cover per toilet:
            // If multiple marked cover by mistake, keep the lowest id.
            DB::statement("
                WITH ranked AS (
                    SELECT id, toilet_id,
                           ROW_NUMBER() OVER (PARTITION BY toilet_id ORDER BY is_cover DESC, id ASC) AS rn
                    FROM toilet_photos
                )
                UPDATE toilet_photos p
                SET is_cover = CASE WHEN r.rn = 1 THEN true ELSE false END
                FROM ranked r
                WHERE p.id = r.id
            ");
        });
    }

    private function makePhotoRow(int $toiletId, bool $isCover): array
    {
        // Use deterministic but unique-ish URLs. Swap with your CDN/local path if needed.
        // e.g. "photos/toilets/{id}/{uuid}.jpg" if you’ll actually upload later.
        $seed = Str::random(10);
        $url  = "https://picsum.photos/seed/{$toiletId}{$seed}/800/600";

        return [
            'toilet_id' => $toiletId,
            'url'       => $url,
            'is_cover'  => $isCover,
            'created_at'=> now(),
            'updated_at'=> now(),
        ];
    }
}
