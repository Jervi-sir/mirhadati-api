<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiFormatter;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TaxonomyController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = validator($request->all(), [
            'type'        => 'nullable|in:wilayas,categories,access_methods,amenities,rules,all',
            'scope'       => 'nullable|in:all,with_toilets',
            'status'      => 'nullable|in:pending,active,suspended',
            'with_counts' => 'nullable|in:0,1',
            'lang'        => 'nullable|in:en,fr,ar',
            'q'           => 'nullable|string|max:100',
        ])->validate();

        // If no 'type' was provided, return a helpful message
        if (empty($validated['type'])) {
            return response()->json([
                'error' => 'Missing "type" parameter.',
                'message' => 'Please specify one of the following valid types.',
                'options' => [
                    'wilayas'         => 'List Algerian provinces (all or those with toilets).',
                    'categories'      => 'List toilet categories (from database).',
                    'access_methods'  => 'List available toilet access methods (hardcoded).',
                    'amenities'       => 'List available amenities (hardcoded).',
                    'rules'           => 'List available rules (hardcoded).',
                    'all'             => 'Return everything in one response (recommended for bootstrapping).',
                ],
            ], 400);
        }

        $type        = $validated['type'];
        $scope       = $validated['scope'] ?? 'all';
        $status      = $validated['status'] ?? 'active';
        $withCounts  = ($validated['with_counts'] ?? '0') === '1';
        $lang        = $validated['lang'] ?? null;
        $q           = trim($validated['q'] ?? '');

        $cacheKey = sprintf(
            'taxonomy:%s:%s:%s:%s:%s',
            $type,
            $scope,
            $status,
            $withCounts ? '1' : '0',
            md5($lang . '|' . $q)
        );

        $callback = function () use ($type, $scope, $status, $withCounts, $lang, $q) {
            if ($type === 'all') {
                return response()->json([
                    'data' => [
                        'wilayas'        => $this->buildWilayas($scope, $status, $withCounts, $lang, $q),
                        'categories'     => $this->buildCategories($lang, $q),
                        'access_methods' => $this->buildAccessMethods($lang, $q),
                        'amenities'      => $this->buildAmenities($lang, $q),
                        'rules'          => $this->buildRules($lang, $q),
                    ],
                ]);
            }

            $map = [
                'wilayas'        => fn() => $this->buildWilayas($scope, $status, $withCounts, $lang, $q),
                'categories'     => fn() => $this->buildCategories($lang, $q),
                'access_methods' => fn() => $this->buildAccessMethods($lang, $q),
                'amenities'      => fn() => $this->buildAmenities($lang, $q),
                'rules'          => fn() => $this->buildRules($lang, $q),
            ];

            return response()->json(['data' => $map[$type]()]);
        };

        $shouldCache = false;
        
        return $shouldCache
            ? Cache::remember($cacheKey, now()->addMinutes(10), $callback)
            : $callback();
    }

    /* ----------------------------- DB-backed ----------------------------- */

    protected function buildWilayas(string $scope, string $status, bool $withCounts, ?string $lang, string $q)
    {
        $labelExpr = $this->labelExpr('wilayas', $lang);

        $base = DB::table('wilayas')
            ->select([
                'wilayas.id',
                'wilayas.code',
                'wilayas.number',
                DB::raw("$labelExpr as label"),
                'wilayas.center_lat as lat',
                'wilayas.center_lng as lng',
                'wilayas.default_radius_km',
                'wilayas.min_lat',
                'wilayas.max_lat',
                'wilayas.min_lng',
                'wilayas.max_lng',
            ]);

        if ($scope === 'with_toilets') {
            $base->whereExists(function ($q2) use ($status) {
                $q2->from('toilets')
                    ->whereColumn('toilets.wilaya_id', 'wilayas.id')
                    ->when($status, fn($qq) => $qq->where('toilets.status', $status));
            });
        }

        if ($q !== '') {
            $base->where(function ($w) use ($q, $labelExpr) {
                $w->where('wilayas.code', 'ilike', "%{$q}%")
                    ->orWhere('wilayas.number', '=', is_numeric($q) ? (int)$q : -99999)
                    ->orWhereRaw("$labelExpr ilike ?", ["%{$q}%"]);
            });
        }

        if ($withCounts) {
            $countsSub = DB::table('toilets')
                ->selectRaw('wilaya_id, COUNT(*) as toilets_count')
                ->when(true, fn($qq) => $qq->when($status, fn($qqq) => $qqq->where('status', $status)))
                ->groupBy('wilaya_id');

            $base->leftJoinSub($countsSub, 'tc', fn($join) => $join->on('tc.wilaya_id', '=', 'wilayas.id'))
                ->addSelect('tc.toilets_count');
        }

        return $base->orderBy('wilayas.number')->get();
    }

    protected function buildCategories(?string $lang, string $q)
    {
        $labelExpr = $this->labelExpr('toilet_categories', $lang);

        $query = DB::table('toilet_categories')
            ->select(['id', 'code', 'icon', DB::raw("$labelExpr as label")])
            ->orderBy('code');

        if ($q !== '') {
            $query->where(function ($w) use ($q, $labelExpr) {
                $w->where('code', 'ilike', "%{$q}%")
                    ->orWhereRaw("$labelExpr ilike ?", ["%{$q}%"]);
            });
        }

        return $query->get();
    }

    /* --------------------------- Hardcoded sets -------------------------- */

    protected function buildAccessMethods(?string $lang, string $q)
    {
        // Must match your toilets.access_method enum
        $rows = ApiFormatter::buildAccessMethods();

        return $this->mapStaticRows($rows, $lang, $q);
    }

    protected function buildAmenities(?string $lang, string $q)
    {
        $rows = ApiFormatter::getAmenities();
        return $this->mapStaticRows($rows, $lang, $q);
    }


    protected function buildRules(?string $lang, string $q)
    {
        $rows = ApiFormatter::getRules();
        return $this->mapStaticRows($rows, $lang, $q);
    }

    /* ------------------------------- Helpers ----------------------------- */

    protected function mapStaticRows(array $rows, ?string $lang, string $q)
    {
        // Choose label column with fallbacks, then filter by q if provided
        $rows = collect($rows)->map(function ($r) use ($lang) {
            $label = $this->pickLabel($r, $lang);
            return [
                'code'  => $r['code'],
                'icon'  => $r['icon'] ?? null,
                'label' => $label,
            ];
        });

        if ($q !== '') {
            $qq = mb_strtolower($q);
            $rows = $rows->filter(function ($r) use ($qq) {
                return str_contains(mb_strtolower($r['code']), $qq)
                    || str_contains(mb_strtolower($r['label']), $qq);
            });
        }

        // Sort by label asc for deterministic order
        return $rows->sortBy('label')->values()->all();
    }

    protected function pickLabel(array $row, ?string $lang): string
    {
        $order = $lang ? [$lang, 'fr', 'en', 'ar'] : ['fr', 'en', 'ar'];
        foreach ($order as $l) {
            if (!empty($row[$l])) return $row[$l];
        }
        return $row['code']; // last-resort fallback
    }

    /**
     * SQL CASE/COALESCE expression to pick display label from multilingual columns.
     * Priority: requested lang → fr → en → ar → code
     */
    protected function labelExpr(string $table, ?string $lang): string
    {
        $col = fn($c) => $table . '.' . $c;
        $order = $lang ? [$lang, 'fr', 'en', 'ar'] : ['fr', 'en', 'ar'];
        $parts = array_map(fn($l) => "NULLIF(" . $col($l) . ", '')", $order);
        $coalesce = implode(', ', $parts);
        return "COALESCE($coalesce, " . $col('code') . ")";
    }
}
