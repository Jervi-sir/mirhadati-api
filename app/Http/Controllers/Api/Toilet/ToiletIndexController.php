<?php

namespace App\Http\Controllers\Api\Toilet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Helpers\ApiFormatter;
use App\Models\Toilet;

class ToiletIndexController extends Controller
{
    public function __invoke(Request $r)
    {
        /* ---------- Coerce typical query params ---------- */
        $coerced = [
            'use_bbox'  => filter_var($r->input('use_bbox', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'is_free'   => filter_var($r->input('is_free', null), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'page'      => is_null($r->input('page'))      ? null : (int) $r->input('page'),
            'perPage'   => is_null($r->input('perPage'))   ? null : (int) $r->input('perPage'),
            'radius_km' => is_null($r->input('radius_km')) ? null : (int) $r->input('radius_km'),
            'lat'       => is_null($r->input('lat')) ? null : (float) $r->input('lat'),
            'lng'       => is_null($r->input('lng')) ? null : (float) $r->input('lng'),
        ];
        $r->merge(array_filter($coerced, fn($v) => $v !== null));

        /* ----------------------- Validate inputs ----------------------- */
        $v = validator($r->all(), [
            'wilaya_id'     => ['nullable', 'integer', 'exists:wilayas,id'],
            'lat'           => ['nullable', 'numeric', 'between:-90,90'],
            'lng'           => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km'     => ['nullable', 'integer', 'min:1', 'max:500'],

            'is_free'       => ['nullable', 'boolean'],
            'access_method' => ['nullable', 'in:public,code,staff,key,app'],
            'pricing_model' => ['nullable', 'in:flat,per-visit,per-30-min,per-60-min'],
            'min_rating'    => ['nullable', 'numeric', 'min:0', 'max:5'],
            'amenities'     => ['nullable', 'array'],
            'amenities.*'   => ['string'],

            'page'          => ['nullable', 'integer', 'min:1'],
            'perPage'       => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort'          => ['nullable', 'in:distance,avg_rating,reviews_count,created_at'],
            'order'         => ['nullable', 'in:asc,desc'],
            'use_bbox'      => ['nullable', 'boolean'],

            'include'       => ['nullable', 'string'], // e.g. "category,wilaya,owner,photos,open_hours,favorite"
        ])->validate();

        $page    = (int)($v['page'] ?? 1);
        $perPage = (int)($v['perPage'] ?? 20);
        $sort    = $v['sort'] ?? 'distance';
        $order   = $v['order'] ?? 'asc';
        $useBbox = (bool)($v['use_bbox'] ?? true);
        $defaultIncludes = ['category', 'wilaya', 'owner', 'photos', 'open_hours'];


        /* ------------------- Resolve center / bbox -------------------- */
        $centerLat = $v['lat'] ?? null;
        $centerLng = $v['lng'] ?? null;
        $radiusKm  = $v['radius_km'] ?? null;

        $bbox = null;

        if (!empty($v['wilaya_id'])) {
            $wilaya = DB::table('wilayas')->where('id', $v['wilaya_id'])->first();
            if (!$wilaya) {
                return response()->json(['message' => 'Wilaya not found'], 404);
            }

            $centerLat = $centerLat ?? $wilaya->center_lat;
            $centerLng = $centerLng ?? $wilaya->center_lng;
            $radiusKm  = $radiusKm  ?? ($wilaya->default_radius_km ?? 25);

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

        /* ----------------------- Includes parsing ---------------------- */
        $include = collect(explode(',', (string)($v['include'] ?? '')))
            ->map(fn($s) => trim($s))
            ->filter()
            ->unique()
            ->values();
        if ($include->isEmpty()) {
            $include = collect($defaultIncludes);
        }
        // Map include tokens to Eloquent relations
        $with = [];
        if ($include->contains('category')) {
            // trim columns for payload
            $with['category'] = function ($q) {};
        }
        if ($include->contains('wilaya')) {
            $with['wilaya'] = function ($q) {};
        }
        if ($include->contains('owner')) {
            $with['owner'] = function ($q) {};
        }
        if ($include->contains('photos')) {
            // keep all photos but order so cover comes first
            $with['photos'] = function ($q) {
                $q->orderByDesc('is_cover')->orderBy('id', 'asc');
            };
        }
        if ($include->contains('open_hours')) {
            $with['openHours'] = function ($q) {
                $q->orderBy('day_of_week')->orderBy('sequence');
            };
        }
        $includeFavorite = $include->contains('favorite');


        $userId = Auth::id();

        /* ------------------------ Base query -------------------------- */
        $q = Toilet::query()
            ->select([
                'toilets.*',
            ])
            ->where('toilets.status', '=', 'active');

        // Cover photo as subquery
        $q->selectSub(
            DB::table('toilet_photos')
                ->select('url')
                ->whereColumn('toilet_photos.toilet_id', 'toilets.id')
                ->orderByDesc('is_cover')
                ->orderBy('id', 'asc')
                ->limit(1),
            'cover_photo_url'
        );

        // is_favorite via EXISTS subquery if requested and user available
        if ($includeFavorite && $userId) {
            $q->selectSub(
                DB::table('favorites')
                    ->selectRaw('1')
                    ->whereColumn('favorites.toilet_id', 'toilets.id')
                    ->where('favorites.user_id', $userId)
                    ->limit(1),
                'is_favorite_flag'
            );
        }

        // Region prefilter
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
        if (!empty($v['access_method'])) {
            $q->where('toilets.access_method', $v['access_method']);
        }
        if (array_key_exists('pricing_model', $v) && $v['pricing_model'] !== null) {
            $q->where('toilets.pricing_model', $v['pricing_model']);
        }
        if (!empty($v['min_rating'])) {
            $q->where('toilets.avg_rating', '>=', (float)$v['min_rating']);
        }
        if (!empty($v['amenities']) && is_array($v['amenities'])) {
            // All amenities must be contained
            foreach ($v['amenities'] as $am) {
                $q->whereJsonContains('toilets.amenities', $am);
            }
        }

        // --- Distance select/filter (Postgres-safe: no alias in HAVING) ---
        // Distance select/filter (Postgres-safe; no alias in HAVING; balanced parens)
        if ($hasCenter) {
            $lat = (float) $centerLat;
            $lng = (float) $centerLng;

            // Balanced expression, no outer wrapping ()
            $distanceSql =
                "6371 * 2 * ASIN(SQRT(" .
                "POWER(SIN(RADIANS(? - toilets.lat) / 2), 2) + " .
                "COS(RADIANS(?)) * COS(RADIANS(toilets.lat)) * " .
                "POWER(SIN(RADIANS(? - toilets.lng) / 2), 2)" .
                "))";

            // 1) expose distance in SELECT
            $q->selectRaw("$distanceSql as distance_km", [$lat, $lat, $lng]);

            // 2) filter by radius with WHERE (repeat the expression)
            $q->whereRaw("$distanceSql <= ?", [$lat, $lat, $lng, (float)$radiusKm]);
        } else {
            $q->selectRaw('NULL as distance_km');
        }


        // Sorting
        if ($sort === 'distance' && $hasCenter) {
            $q->orderBy('distance_km', $order);
        } elseif (in_array($sort, ['avg_rating', 'reviews_count', 'created_at'], true)) {
            $q->orderBy('toilets.' . $sort, $order);
            if ($hasCenter) $q->orderBy('distance_km', 'asc');
        } else {
            if ($hasCenter) $q->orderBy('distance_km', 'asc');
            else $q->orderBy('toilets.created_at', 'desc');
        }

        // Count & fetch page
        $total = (clone $q)->count();
        if (!empty($with)) {
            $q->with($with);
        }

        $rows = $q->forPage($page, $perPage)->get();

        /* ---------------- Format with ApiFormatter -------------------- */
        $data = [];
        foreach ($rows as $t) {
            $item = ApiFormatter::toilet($t, [
                // explicitly include what you care about; you can also pass 'all' => true
                'include' => 'id,owner_id,toilet_category_id,name,description,phone_numbers,lat,lng,address_line,wilaya_id,place_hint,access_method,capacity,is_unisex,amenities,amenities_meta,rules,rules_meta,is_free,price_cents,pricing_model,status,avg_rating,reviews_count,photos_count,created_at,updated_at,cover_photo,category,wilaya,owner,photos,open_hours,is_favorite',
            ]);

            // extras
            $item['distance_km']   = isset($t->distance_km) ? (float)$t->distance_km : null;
            $item['cover_photo']   = $item['cover_photo'] ?? ($t->cover_photo_url ?? null);

            // If favorites was selected and subquery present, translate flag to boolean
            if ($includeFavorite && property_exists($t, 'is_favorite_flag')) {
                $item['is_favorite'] = (bool) $t->is_favorite_flag;
            }


            $data[] = $item;
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'page'     => $page,
                'perPage'  => $perPage,
                'total'    => $total,
            ],
            'center' => $hasCenter ? [
                'lat'        => (float)$centerLat,
                'lng'        => (float)$centerLng,
                'radius_km'  => (int)$radiusKm,
            ] : null,
        ]);
    }
}
