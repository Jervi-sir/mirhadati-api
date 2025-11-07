<?php

namespace App\Http\Controllers\Api\Toilet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ToiletMarkerIndexController extends Controller
{
    public function __invoke(Request $r)
    {
        /* ---------- Coerce typical query params ---------- */
        $coerced = [
            'use_bbox'      => filter_var($r->input('use_bbox', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'with_distance' => filter_var($r->input('with_distance', null), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'is_free'       => filter_var($r->input('is_free', null), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'page'          => is_null($r->input('page'))      ? null : (int) $r->input('page'),
            'perPage'       => is_null($r->input('perPage'))   ? null : (int) $r->input('perPage'),
            'radius_km'     => is_null($r->input('radius_km')) ? null : (int) $r->input('radius_km'),
            'lat'           => is_null($r->input('lat')) ? null : (float) $r->input('lat'),
            'lng'           => is_null($r->input('lng')) ? null : (float) $r->input('lng'),
        ];
        $r->merge(array_filter($coerced, fn($v) => $v !== null));

        // ---- Validate inputs (trimmed) ----
        $v = validator($r->all(), [
            'wilaya_id'     => ['nullable', 'integer', 'exists:wilayas,id'],
            'lat'           => ['nullable', 'numeric', 'between:-90,90'],
            'lng'           => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km'     => ['nullable', 'integer', 'min:1', 'max:500'],

            'is_free'       => ['nullable', 'boolean'],
            'page'          => ['nullable', 'integer', 'min:1'],
            'perPage'       => ['nullable', 'integer', 'min:1', 'max:1000'],
            'sort'          => ['nullable', 'in:distance,created_at'],
            'order'         => ['nullable', 'in:asc,desc'],
            'use_bbox'      => ['nullable', 'boolean'],

            'with_distance' => ['nullable', 'boolean'],
        ])->validate();

        $page          = (int)($v['page'] ?? 1);
        $perPage       = (int)($v['perPage'] ?? 500); // larger default for pins
        $sort          = $v['sort'] ?? 'distance';
        $order         = $v['order'] ?? 'asc';
        $useBbox       = (bool)($v['use_bbox'] ?? true);
        $withDistance  = (bool)($v['with_distance'] ?? true);

        // ---- Resolve center/bbox (reuse wilaya bounds) ----
        $centerLat = $v['lat'] ?? null;
        $centerLng = $v['lng'] ?? null;
        $radiusKm  = $v['radius_km'] ?? null;
        $bbox      = null;

        if (!empty($v['wilaya_id'])) {
            $wilaya = DB::table('wilayas')->where('id', $v['wilaya_id'])->first();
            if (!$wilaya) return response()->json(['message' => 'Wilaya not found'], 404);

            $centerLat ??= (float)$wilaya->center_lat;
            $centerLng ??= (float)$wilaya->center_lng;
            $radiusKm  ??= (int)($wilaya->default_radius_km ?? 25);

            if ($useBbox && $wilaya->min_lat !== null && $wilaya->max_lat !== null && $wilaya->min_lng !== null && $wilaya->max_lng !== null) {
                $bbox = [
                    'min_lat' => (float)$wilaya->min_lat,
                    'max_lat' => (float)$wilaya->max_lat,
                    'min_lng' => (float)$wilaya->min_lng,
                    'max_lng' => (float)$wilaya->max_lng,
                ];
            }
        }

        $hasCenter = $centerLat !== null && $centerLng !== null && $radiusKm !== null;

        // ---- Base query: only columns needed for pins ----
        $q = DB::table('toilets')
            ->select([
                'toilets.id',
                'toilets.lat',
                'toilets.lng',
                'toilets.is_free',
                'toilets.status',
                'toilets.created_at',
            ])
            ->where('toilets.status', '=', 'active');

        // Prefilter by bbox / rough square around center
        if ($bbox) {
            $q->whereBetween('toilets.lat', [$bbox['min_lat'], $bbox['max_lat']])
              ->whereBetween('toilets.lng', [$bbox['min_lng'], $bbox['max_lng']]);
        } elseif ($hasCenter) {
            $latDelta = $radiusKm / 111.0;
            $lngDelta = $radiusKm / (111.0 * max(cos(deg2rad($centerLat)), 0.000001));
            $q->whereBetween('toilets.lat', [$centerLat - $latDelta, $centerLat + $latDelta])
              ->whereBetween('toilets.lng', [$centerLng - $lngDelta, $centerLng + $lngDelta]);
        }

        // Filters
        if (array_key_exists('is_free', $v) && $v['is_free'] !== null) {
            $q->where('toilets.is_free', $v['is_free'] ? 1 : 0);
        }

        // Distance select + filter (only if requested & has center)
        if ($withDistance && $hasCenter) {
            $lat = (float)$centerLat;
            $lng = (float)$centerLng;

            $distanceSql =
                "6371 * 2 * ASIN(SQRT(" .
                "POWER(SIN(RADIANS(? - toilets.lat) / 2), 2) + " .
                "COS(RADIANS(?)) * COS(RADIANS(toilets.lat)) * " .
                "POWER(SIN(RADIANS(? - toilets.lng) / 2), 2)" .
                "))";

            $q->selectRaw("$distanceSql as distance_km", [$lat, $lat, $lng])
              ->whereRaw("$distanceSql <= ?", [$lat, $lat, $lng, (float)$radiusKm]);
        } else {
            $q->selectRaw('NULL as distance_km');
        }

        // Sorting
        if ($sort === 'distance' && $hasCenter && $withDistance) {
            $q->orderBy('distance_km', $order);
        } else {
            $q->orderBy('toilets.created_at', 'desc');
            if ($hasCenter && $withDistance) $q->orderBy('distance_km', 'asc');
        }

        // Count & fetch
        $total = (clone $q)->count();
        $rows  = $q->forPage($page, $perPage)->get();

        // Output markers (guard numeric)
        $markers = [];
        foreach ($rows as $t) {
            $lat = (float) $t->lat;
            $lng = (float) $t->lng;
            if (!is_finite($lat) || !is_finite($lng)) continue;

            $markers[] = [
                'id'          => (int) $t->id,
                'lat'         => $lat,
                'lng'         => $lng,
                'is_free'     => (bool) $t->is_free,
                'distance_km' => isset($t->distance_km) ? (float) $t->distance_km : null,
            ];
        }

        return response()->json([
            'markers' => $markers,
            'meta' => [
                'page'    => $page,
                'perPage' => $perPage,
                'total'   => $total,
            ],
            'center' => $hasCenter ? [
                'lat'       => (float)$centerLat,
                'lng'       => (float)$centerLng,
                'radius_km' => (int)$radiusKm,
            ] : null,
        ]);
    }
}
