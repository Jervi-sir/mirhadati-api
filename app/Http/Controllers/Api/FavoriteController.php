<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiFormatter;
use App\Http\Controllers\Controller;
use App\Models\Toilet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    /** POST /api/toilets/{toilet}/favorite */
    public function store(Request $request, Toilet $toilet)
    {
        $userId = $request->user()->id;

        DB::table('favorites')->updateOrInsert(
            ['user_id' => $userId, 'toilet_id' => $toilet->id],
            ['created_at' => now(), 'updated_at' => now()]
        );

        return response()->json([
            'message' => 'Added to favorites',
            'data'    => [
                ...ApiFormatter::toilet($toilet),
                'favorited' => true,
            ],
        ], 201);
    }

    /** DELETE /api/toilets/{toilet}/favorite */
    public function destroy(Request $request, Toilet $toilet)
    {
        DB::table('favorites')
            ->where('user_id', $request->user()->id)
            ->where('toilet_id', $toilet->id)
            ->delete();

        return response()->json([
            'message' => 'Removed from favorites',
            'data'    => [
                ...ApiFormatter::toilet($toilet),
                'favorited' => false,
            ],
        ]);
    }

    /** GET /api/me/favorites */
    public function index(Request $r)
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

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
            'sort'          => ['nullable', 'in:distance,avg_rating,reviews_count,created_at,favorited_at'],
            'order'         => ['nullable', 'in:asc,desc'],
            'use_bbox'      => ['nullable', 'boolean'],

            'include'       => ['nullable', 'string'], // e.g. "category,wilaya,owner,photos,open_hours"
        ])->validate();

        $page    = (int)($v['page'] ?? 1);
        $perPage = (int)($v['perPage'] ?? 20);
        $sort    = $v['sort'] ?? 'favorited_at';
        $order   = $v['order'] ?? 'desc';
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

        // Map include tokens to Eloquent relations (trim columns if you want)
        $with = [];
        if ($include->contains('category')) { $with['category'] = fn($q) => $q; }
        if ($include->contains('wilaya'))   { $with['wilaya']   = fn($q) => $q; }
        if ($include->contains('owner'))    { $with['owner']    = fn($q) => $q; }
        if ($include->contains('photos'))   {
            $with['photos'] = function ($q) {
                $q->orderByDesc('is_cover')->orderBy('id', 'asc');
            };
        }
        if ($include->contains('open_hours')) {
            $with['openHours'] = function ($q) {
                $q->orderBy('day_of_week')->orderBy('sequence');
            };
        }

        /* ------------------------ Base query -------------------------- */
        $q = Toilet::query()
            ->select([
                'toilets.*',
                'favorites.created_at as favorited_at',
            ])
            ->join('favorites', 'favorites.toilet_id', '=', 'toilets.id')
            ->where('favorites.user_id', '=', $userId)
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
            foreach ($v['amenities'] as $am) {
                $q->whereJsonContains('toilets.amenities', $am);
            }
        }

        // Distance (optional)
        if ($hasCenter) {
            $lat = (float) $centerLat;
            $lng = (float) $centerLng;

            $distanceSql =
                "6371 * 2 * ASIN(SQRT(" .
                "POWER(SIN(RADIANS(? - toilets.lat) / 2), 2) + " .
                "COS(RADIANS(?)) * COS(RADIANS(toilets.lat)) * " .
                "POWER(SIN(RADIANS(? - toilets.lng) / 2), 2)" .
                "))";

            $q->selectRaw("$distanceSql as distance_km", [$lat, $lat, $lng]);
            $q->whereRaw("$distanceSql <= ?", [$lat, $lat, $lng, (float)$radiusKm ?? 999999]);
        } else {
            $q->selectRaw('NULL as distance_km');
        }

        // Sorting
        if ($sort === 'distance' && $hasCenter) {
            $q->orderBy('distance_km', $order);
            $q->orderBy('favorites.created_at', 'desc');
        } elseif (in_array($sort, ['avg_rating', 'reviews_count', 'created_at'], true)) {
            $q->orderBy('toilets.' . $sort, $order);
            $q->orderBy('favorites.created_at', 'desc');
        } else { // default or favorited_at
            $q->orderBy('favorites.created_at', $order);
        }

        // Count before pagination
        $total = (clone $q)->count();

        if (!empty($with)) {
            $q->with($with);
        }

        $rows = $q->forPage($page, $perPage)->get();

        /* ---------------- Format with ApiFormatter -------------------- */
        $data = [];
        foreach ($rows as $t) {
            $item = ApiFormatter::toilet($t, [
                'include' => 'id,owner_id,toilet_category_id,name,description,phone_numbers,lat,lng,address_line,wilaya_id,place_hint,access_method,capacity,is_unisex,amenities,rules,is_free,price_cents,pricing_model,status,avg_rating,reviews_count,photos_count,created_at,updated_at,cover_photo,category,wilaya,owner,photos,open_hours,is_favorite',
            ]);

            // extras/overrides
            $item['is_favorite']  = true; // by definition in this endpoint
            $item['cover_photo']  = $item['cover_photo'] ?? ($t->cover_photo_url ?? null);
            $item['favorited_at'] = $t->favorited_at ? (string)$t->favorited_at : null;
            $item['distance_km']  = isset($t->distance_km) ? (float)$t->distance_km : null;

            $data[] = $item;
        }

        return response()->json([
            'data' => $data,
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
