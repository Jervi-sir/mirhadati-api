<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Carbon\Carbon;

class ToiletExtraInfoSeeder extends Seeder
{
    // ---------- Tunables ----------
    protected int $limitUsersForFavs   = 200;
    protected int $limitUsersForReviews= 300;
    protected int $limitUsersForSess   = 400;

    protected int $hoursBatchSize      = 1000;
    protected int $genericBatchSize    = 1500;

    public function run(): void
    {
        // Minimal existence checks
        if (!DB::table('toilets')->exists()) {
            $this->command?->warn('No toilets found. Skipping.');
            return;
        }
        if (!DB::table('users')->exists()) {
            $this->command?->warn('No users found. Seed users first if you want favorites/reviews/sessions.');
        }

        // 1) Opening hours (idempotent: skip toilets that already have rows)
        $this->seedOpeningHours();

        // Prepare common IDs
        $userFavIds    = DB::table('users')->inRandomOrder()->limit($this->limitUsersForFavs)->pluck('id')->all();
        $userReviewIds = DB::table('users')->inRandomOrder()->limit($this->limitUsersForReviews)->pluck('id')->all();
        $userSessIds   = DB::table('users')->inRandomOrder()->limit($this->limitUsersForSess)->pluck('id')->all();

        $toiletIds = DB::table('toilets')->pluck('id')->all();

        // 2) Favorites
        if (!empty($userFavIds) && !empty($toiletIds)) {
            $this->seedFavorites($userFavIds, $toiletIds);
        }

        // 3) Reviews
        if (!empty($userReviewIds) && !empty($toiletIds)) {
            $this->seedReviews($userReviewIds, $toiletIds);
        }

        // 4) Sessions (needs price info)
        $toiletsForSessions = DB::table('toilets')->select('id','is_free','price_cents','pricing_model')->get();
        if (!empty($userSessIds) && $toiletsForSessions->count() > 0) {
            $this->seedSessions($userSessIds, $toiletsForSessions);
        }

        // 5) Reports
        if (!empty($toiletIds)) {
            $this->seedReports($toiletIds);
        }

        // 6) Recompute aggregates for reviews_count + avg_rating
        $this->recomputeReviewAggregates();

        $this->command?->info('ToiletExtraInfoSeeder completed.');
    }

    /* ========================= 1) Opening Hours ========================= */

    protected function seedOpeningHours(): void
    {
        $batch = [];

        foreach (DB::table('toilets')->select('id')->cursor() as $toilet) {
            $exists = DB::table('toilet_open_hours')->where('toilet_id', $toilet->id)->exists();
            if ($exists) continue;

            $rows = $this->makeWeeklyHours($toilet->id);
            $batch = array_merge($batch, $rows);

            if (count($batch) >= $this->hoursBatchSize) {
                DB::table('toilet_open_hours')->insert($batch);
                $batch = [];
            }
        }

        if ($batch) {
            DB::table('toilet_open_hours')->insert($batch);
        }

        $this->command?->info('Opening hours seeded (skipped existing).');
    }

    // Mon=0..Sun=6 patterns
    protected function makeWeeklyHours(int $toiletId): array
    {
        $pattern = Arr::random(['A','B','C']);
        $rows = [];
        $add = function (int $dow, string $opens, string $closes, int $seq = 0) use (&$rows, $toiletId) {
            $rows[] = [
                'toilet_id'   => $toiletId,
                'day_of_week' => $dow,
                'opens_at'    => $opens,
                'closes_at'   => $closes,
                'sequence'    => $seq,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        };

        switch ($pattern) {
            case 'A': // mall-like: daily, longer weekend
                foreach ([0,1,2,3] as $d) $add($d, '09:00:00', '21:00:00');
                $add(4, '09:00:00', '22:00:00'); // Fri
                $add(5, '10:00:00', '23:00:00'); // Sat
                $add(6, '10:00:00', '20:00:00'); // Sun
                break;

            case 'B': // office-like: Mon-Fri only
                foreach ([0,1,2,3,4] as $d) $add($d, '09:00:00', '18:00:00');
                break;

            case 'C': // split lunch Mon-Fri; short Sat
                foreach ([0,1,2,3,4] as $d) {
                    $add($d, '09:00:00', '12:00:00', 0);
                    $add($d, '13:00:00', '20:00:00', 1);
                }
                $add(5, '10:00:00', '14:00:00', 0); // Sat
                // Sun closed
                break;
        }
        return $rows;
    }

    /* =========================== 2) Favorites =========================== */

    protected function seedFavorites(array $userIds, array $toiletIds): void
    {
        $batch = [];
        foreach ($userIds as $uid) {
            $count = rand(10, 30);
            foreach (collect($toiletIds)->shuffle()->take($count) as $tid) {
                $batch[] = [
                    'user_id'    => $uid,
                    'toilet_id'  => $tid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (count($batch) >= $this->genericBatchSize) {
                    DB::table('favorites')->insertOrIgnore($batch); // respects unique index
                    $batch = [];
                }
            }
        }
        if ($batch) {
            DB::table('favorites')->insertOrIgnore($batch);
        }
        $this->command?->info('Favorites seeded.');
    }

    /* ============================ 3) Reviews ============================ */

    protected function seedReviews(array $userIds, array $toiletIds): void
    {
        $batch = [];
        foreach ($toiletIds as $tid) {
            $n = rand(0, 15); // reviews per toilet
            if ($n === 0) continue;

            $reviewers = (array) Arr::random($userIds, min($n, count($userIds)));
            foreach ($reviewers as $uid) {
                $rating = rand(3, 5); // skew positive
                $created = now()->subDays(rand(0, 180))->subMinutes(rand(0, 1440));
                $batch[] = [
                    'toilet_id'   => $tid,
                    'user_id'     => $uid,
                    'rating'      => $rating,
                    'text'        => Arr::random([null, 'Clean and convenient.', 'Could be better stocked.', 'Great location!', 'Okay for a quick stop.']),
                    'cleanliness' => Arr::random([null, 3, 4, 5]),
                    'smell'       => Arr::random([null, 3, 4, 5]),
                    'stock'       => Arr::random([null, 2, 3, 4, 5]),
                    'created_at'  => $created,
                    'updated_at'  => $created,
                ];

                if (count($batch) >= $this->genericBatchSize) {
                    DB::table('toilet_reviews')->insertOrIgnore($batch); // unique (toilet_id,user_id)
                    $batch = [];
                }
            }
        }
        if ($batch) {
            DB::table('toilet_reviews')->insertOrIgnore($batch);
        }
        $this->command?->info('Reviews seeded.');
    }

    /* ============================ 4) Sessions =========================== */

    protected function seedSessions(array $userIds, $toilets): void
    {
        $batch = [];
        foreach ($toilets as $t) {
            $n = rand(0, 8); // per toilet
            for ($i = 0; $i < $n; $i++) {
                [$start, $end] = $this->makeSessionWindow(30);
                $uid = Arr::random($userIds);
                $charge = $t->is_free ? null : $this->computeCharge($t->price_cents, $t->pricing_model);

                $batch[] = [
                    'toilet_id'    => $t->id,
                    'user_id'      => $uid,
                    'started_at'   => $start,
                    'ended_at'     => $end,
                    'charge_cents' => $charge,
                    'start_method' => $this->randomStartMethod(),
                    'end_method'   => $this->randomEndMethod(),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];

                if (count($batch) >= $this->genericBatchSize) {
                    DB::table('toilet_sessions')->insert($batch);
                    $batch = [];
                }
            }
        }
        if ($batch) {
            DB::table('toilet_sessions')->insert($batch);
        }
        $this->command?->info('Sessions seeded.');
    }

    protected function makeSessionWindow(int $maxDaysAgo = 30): array
    {
        $start = Carbon::now()->subDays(rand(0, $maxDaysAgo))->setTime(rand(8, 20), rand(0, 59));
        $durationMin = Arr::random([5,10,15,20,30,45,60,90]);
        $end = (clone $start)->addMinutes($durationMin);
        return [$start, $end];
    }

    protected function computeCharge(?int $priceCents, ?string $model): ?int
    {
        if ($priceCents === null) return null;
        if ($model === null || $model === 'flat' || $model === 'per-visit') return $priceCents;
        if ($model === 'per-30-min') return $priceCents; // adjust if you meter by duration
        if ($model === 'per-60-min') return $priceCents;
        return $priceCents;
    }

    protected function randomStartMethod(): ?string
    {
        return Arr::random([null, 'tap', 'qr', 'code', 'tap', 'qr']);
    }
    protected function randomEndMethod(): ?string
    {
        return Arr::random([null, 'tap', 'auto', 'qr', 'tap']);
    }

    /* ============================= 5) Reports ============================ */

    protected function seedReports(array $toiletIds): void
    {
        $userIds = DB::table('users')->inRandomOrder()->limit(200)->pluck('id')->all();
        $reasons = ['closed','fake','unsafe','harassment','other'];

        $batch = [];
        foreach ($toiletIds as $tid) {
            $n = rand(0, 2);
            for ($i = 0; $i < $n; $i++) {
                $reason = Arr::random($reasons);
                $created = now()->subDays(rand(0, 120))->subMinutes(rand(0, 1440));
                $resolved = Arr::random([null, null, (clone $created)->addDays(rand(1, 15))]);

                $batch[] = [
                    'toilet_id'   => $tid,
                    'user_id'     => Arr::random([null, !empty($userIds) ? Arr::random($userIds) : null]),
                    'reason'      => $reason,
                    'details'     => Arr::random([null, 'Reported via app.', 'Temporary closure.', 'Missing signage.']),
                    'resolved_at' => $resolved,
                    'created_at'  => $created,
                    'updated_at'  => $resolved ?? $created,
                ];

                if (count($batch) >= $this->genericBatchSize) {
                    DB::table('toilet_reports')->insert($batch);
                    $batch = [];
                }
            }
        }
        if ($batch) {
            DB::table('toilet_reports')->insert($batch);
        }
        $this->command?->info('Reports seeded.');
    }

    /* ===================== 6) Review aggregates refresh ===================== */

    protected function recomputeReviewAggregates(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
                UPDATE toilets t
                LEFT JOIN (
                    SELECT toilet_id, COUNT(*) AS cnt, CAST(AVG(rating) AS DECIMAL(3,2)) AS avg_rating
                    FROM toilet_reviews
                    GROUP BY toilet_id
                ) sub ON sub.toilet_id = t.id
                SET t.reviews_count = COALESCE(sub.cnt, 0),
                    t.avg_rating    = COALESCE(sub.avg_rating, 0)
            ");
        } else {
            // Postgres / others
            DB::statement("
                UPDATE toilets t
                SET reviews_count = COALESCE(sub.cnt, 0),
                    avg_rating    = COALESCE(sub.avg_rating, 0)
                FROM (
                    SELECT toilet_id, COUNT(*) AS cnt, CAST(AVG(rating) AS numeric(3,2)) AS avg_rating
                    FROM toilet_reviews
                    GROUP BY toilet_id
                ) sub
                WHERE sub.toilet_id = t.id
            ");
        }

        $this->command?->info('Aggregates recomputed (reviews_count, avg_rating).');
    }
}
