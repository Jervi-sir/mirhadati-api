<?php

namespace App\Http\Controllers\Api\Host;

use App\Helpers\ApiFormatter;
use App\Http\Controllers\Controller;
use App\Models\Toilet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class HostToiletIndexController extends Controller
{
    /**
     * GET /api/host/toilets
     * Paginated list of toilets owned by the authenticated host.
     * Filters:
     *   - ?status=pending|active|suspended
     *   - ?is_free=true|false
     *   - ?q=<search in name/address_line>
     *   - ?wilaya_id=<id>
     *   - ?page=1&perPage=20
     */
    public function __invoke(Request $r)
    {
        $user = Auth::user();
        if (!$user) abort(401, 'Unauthenticated.');

        $roleCode = strtolower(optional($user->role)->code ?? 'user');
        if (!in_array($roleCode, ['host', 'admin'], true)) {
            abort(403, 'Only hosts/admins can access host endpoints.');
        }

        // filters
        $page     = is_null($r->input('page'))      ? 1  : (int) $r->input('page');
        $perPage  = is_null($r->input('perPage'))   ? 20 : (int) $r->input('perPage');

        // optional: allow admin to filter by owner_id from query
        $ownerId  = is_null($r->input('owner_id')) ? null : (int) $r->input('owner_id');

        $builder = Toilet::query()
            ->where('owner_id', $user->id)
            ->with([
                'category:id,code,en,fr,ar',
                'wilaya:id,number,code,en,fr,ar', // include number for your formatter
                'photos:id,toilet_id,url,is_cover',
            ]);

        $builder->orderByDesc('id');

        $paginator = $builder->paginate($perPage, ['*'], 'page', $page);
        // Format rows
        $items = array_map(
            fn($t) => ApiFormatter::toilet($t, [
                // include common host bits by default; null means all if you prefer everything
                'include' => 'id,owner_id,toilet_category_id,name,description,phone_numbers,lat,lng,address_line,wilaya_id,place_hint,access_method,capacity,is_unisex,amenities,rules,is_free,price_cents,pricing_model,status,avg_rating,reviews_count,photos_count,created_at,updated_at,cover_photo,category,wilaya,owner,photos,open_hours,is_favorite',
                // 'groups' => 'coords,pricing,labels,counts,relations,meta',
                // 'drop_nulls' => true,
                // 'drop_empty_arrays' => true,
            ]),
            $paginator->items()
        );

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }
}
