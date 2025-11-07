<?php

namespace App\Http\Controllers\Api\Toilet;

use App\Helpers\ApiFormatter;
use App\Http\Controllers\Controller;
use App\Models\Toilet;
use App\Models\ToiletPhoto;
use Illuminate\Http\Request;

class ToiletShowController extends Controller
{
    public function __invoke(Request $request, Toilet $toilet)
    {
        $userId = $request->user()?->id;

        // Re-query the bound model so we can add computed selects/exists + eager loads in one round-trip
        $toilet = Toilet::query()
            ->whereKey($toilet->id)
            // cover photo URL via subquery (no N+1)
            ->addSelect([
                'cover_photo_url' => ToiletPhoto::select('url')
                    ->whereColumn('toilet_id', 'toilets.id')
                    ->orderByDesc('is_cover')
                    ->orderBy('id')
                    ->limit(1)
            ])
            // is_favorite for current user (boolean)
            ->when(
                $userId,
                fn($q) =>
                $q->withExists([
                    'favorites as is_favorite' => fn($qq) => $qq->where('user_id', $userId)
                ])
            )
            // relations
            ->with([
                'category',
                'wilaya',
                'photos'    => fn($q) => $q->orderByDesc('is_cover')->orderBy('id'),
                'openHours' => fn($q) => $q->orderBy('day_of_week')->orderBy('sequence'),
            ])
            ->firstOrFail();

        // Let formatter include the bundles we care about
        $payload = ApiFormatter::toilet($toilet, [
            // you can switch to 'all' => true if you want literally everything
            'include'   => ['id', 'category', 'wilaya', 'photos', 'open_hours', 'phone_numbers', 'is_favorite', 'lat', 'lng', 'is_free', 'price_cents', 'pricing_model', 'name', 'address_line', 'place_hint', 'access_method', 'reviews_count', 'photos_count', 'created_at', 'updated_at'],
            'groups'    => ['meta'], // adds created_at, updated_at (already included, but kept for clarity)
            'drop_nulls' => true,
            'drop_empty_arrays' => true,
        ]);

        // Add the preselected cover_photo (optional)
        if (!isset($payload['cover_photo']) && isset($toilet->cover_photo_url)) {
            $payload['cover_photo'] = [
                'id'        => null,
                'toilet_id' => $toilet->id,
                'url'       => (string) $toilet->cover_photo_url,
                'is_cover'  => true,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        return response()->json(['data' => $payload]);
    }
}
