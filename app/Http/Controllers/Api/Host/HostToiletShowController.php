<?php

namespace App\Http\Controllers\Api\Host;

use App\Http\Controllers\Controller;
use App\Models\Toilet;
use App\Models\ToiletReport;
use App\Models\ToiletReview;
use App\Models\ToiletSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HostToiletShowController extends Controller
{
    public function show(Request $request, Toilet $toilet)
    {
        // ------- AuthZ: owner or admin-like role -------
        $user = Auth::user();
        $isOwner = $user && (int)$toilet->owner_id === (int)$user->id;
        $roleCode = optional($user?->role)->code;
        $isAdmin = in_array($roleCode, ['admin', 'superadmin', 'staff', 'moderator'], true);

        if (!$isOwner && !$isAdmin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // ------- Eager-load core relations -------
        $toilet->load([
            'photos' => fn($q) => $q->orderByDesc('is_cover')->oldest('id'),
            'openHours' => fn($q) => $q->orderBy('day_of_week')->orderBy('sequence'),
            'category',
            'wilaya',
            'owner:id,name,phone,email,role_id',
            'owner.role:id,code',
        ]);

        // ------- Aggregates & host-only insights -------
        // Favorites count (how many users bookmarked it)
        $favoritesCount = DB::table('favorites')->where('toilet_id', $toilet->id)->count();

        // Sessions: last 10, plus totals
        $recentSessions = ToiletSession::query()
            ->where('toilet_id', $toilet->id)
            ->orderByDesc('started_at')
            ->limit(10)
            ->get(['id','user_id','started_at','ended_at','charge_cents','start_method','end_method']);

        $sessionsAgg = ToiletSession::query()
            ->where('toilet_id', $toilet->id)
            ->selectRaw('COUNT(*) as total_sessions, COALESCE(SUM(charge_cents),0) as total_revenue_cents')
            ->first();

        // Reviews: avg/rating already cached in columns, but include a few latest with author
        $recentReviews = ToiletReview::query()
            ->where('toilet_id', $toilet->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->with(['user:id,name'])
            ->get(['id','user_id','rating','text','cleanliness','smell','stock','created_at']);

        // Reports: unresolved first, then recent resolved
        $unresolvedReports = ToiletReport::query()
            ->where('toilet_id', $toilet->id)
            ->whereNull('resolved_at')
            ->orderByDesc('created_at')
            ->get(['id','user_id','reason','details','created_at']);

        $recentResolvedReports = ToiletReport::query()
            ->where('toilet_id', $toilet->id)
            ->whereNotNull('resolved_at')
            ->orderByDesc('resolved_at')
            ->limit(5)
            ->get(['id','user_id','reason','details','created_at','resolved_at']);

        // ------- Build host payload (extends your public formatter) -------
        $data = [
            // Core (mirror your formatToilet, but inline to keep this file self-contained)
            'id'                 => (int)$toilet->id,
            'owner_id'           => $toilet->owner_id ? (int)$toilet->owner_id : null,
            'toilet_category_id' => (int)$toilet->toilet_category_id,
            'wilaya_id'          => (int)$toilet->wilaya_id,
            'name'               => (string)$toilet->name,
            'description'        => $toilet->description,
            'phone_numbers'      => $toilet->phone_numbers,
            'lat'                => (float)$toilet->lat,
            'lng'                => (float)$toilet->lng,
            'address_line'       => (string)$toilet->address_line,
            'place_hint'         => $toilet->place_hint,
            'access_method'      => (string)$toilet->access_method,
            'capacity'           => (int)$toilet->capacity,
            'is_unisex'          => (bool)$toilet->is_unisex,
            'amenities'          => $toilet->amenities,
            'rules'              => $toilet->rules,
            'is_free'            => (bool)$toilet->is_free,
            'price_cents'        => $toilet->price_cents ? (int)$toilet->price_cents : null,
            'pricing_model'      => $toilet->pricing_model,
            'status'             => (string)$toilet->status, // host can see pending/suspended
            'avg_rating'         => (float)$toilet->avg_rating,
            'reviews_count'      => (int)$toilet->reviews_count,
            'photos_count'       => (int)$toilet->photos_count,
            'created_at'         => $toilet->created_at?->toISOString(),
            'updated_at'         => $toilet->updated_at?->toISOString(),

            'photos' => $toilet->photos->map(fn($p) => [
                'id'       => (int)$p->id,
                'url'      => (string)$p->url,
                'is_cover' => (bool)$p->is_cover,
            ])->values(),

            'open_hours' => $toilet->openHours->map(fn($oh) => [
                'id'          => (int)$oh->id,
                'day_of_week' => (int)$oh->day_of_week,
                'opens_at'    => $oh->opens_at,  // "HH:MM:SS"
                'closes_at'   => $oh->closes_at,
                'sequence'    => (int)$oh->sequence,
            ])->values(),

            'category' => $toilet->relationLoaded('category') ? [
                'id'    => (int)$toilet->category->id,
                'code'  => $toilet->category->code,
                'icon'  => $toilet->category->icon,
                'label' => $toilet->category->fr ?? $toilet->category->en ?? $toilet->category->ar ?? $toilet->category->code,
            ] : null,

            'wilaya' => $toilet->relationLoaded('wilaya') ? [
                'id'     => (int)$toilet->wilaya->id,
                'number' => (int)$toilet->wilaya->number,
                'code'   => $toilet->wilaya->code,
                'label'  => $toilet->wilaya->fr ?? $toilet->wilaya->en ?? $toilet->wilaya->ar ?? $toilet->wilaya->code,
            ] : null,

            'owner' => $toilet->relationLoaded('owner') ? [
                'id'    => (int)$toilet->owner->id,
                'name'  => (string)$toilet->owner->name,
                'phone' => $toilet->owner->phone,
                'email' => $toilet->owner->email,
                'role'  => $toilet->owner->relationLoaded('role') ? ($toilet->owner->role->code ?? null) : null,
            ] : null,

            // -------- Host-only extras --------
            'host_meta' => [
                'can_edit'  => true, // by virtue of being here
                'is_owner'  => $isOwner,
                'is_admin'  => $isAdmin,

                'favorites_count'       => (int)$favoritesCount,
                'sessions'              => [
                    'total_sessions'       => (int)($sessionsAgg->total_sessions ?? 0),
                    'total_revenue_cents'  => (int)($sessionsAgg->total_revenue_cents ?? 0),
                    'recent'               => $recentSessions->map(function ($s) {
                        return [
                            'id'            => (int)$s->id,
                            'user_id'       => $s->user_id ? (int)$s->user_id : null,
                            'started_at'    => $s->started_at?->toISOString(),
                            'ended_at'      => $s->ended_at?->toISOString(),
                            'charge_cents'  => $s->charge_cents !== null ? (int)$s->charge_cents : null,
                            'start_method'  => $s->start_method,
                            'end_method'    => $s->end_method,
                        ];
                    })->values(),
                ],

                'reviews' => [
                    'recent' => $recentReviews->map(function ($r) {
                        return [
                            'id'          => (int)$r->id,
                            'user'        => $r->relationLoaded('user') && $r->user ? [
                                'id'   => (int)$r->user->id,
                                'name' => (string)$r->user->name,
                            ] : null,
                            'rating'      => (int)$r->rating,
                            'text'        => $r->text,
                            'cleanliness' => $r->cleanliness !== null ? (int)$r->cleanliness : null,
                            'smell'       => $r->smell !== null ? (int)$r->smell : null,
                            'stock'       => $r->stock !== null ? (int)$r->stock : null,
                            'created_at'  => $r->created_at?->toISOString(),
                        ];
                    })->values(),
                ],

                'reports' => [
                    'unresolved' => $unresolvedReports->map(function ($rp) {
                        return [
                            'id'         => (int)$rp->id,
                            'user_id'    => $rp->user_id ? (int)$rp->user_id : null,
                            'reason'     => $rp->reason,
                            'details'    => $rp->details,
                            'created_at' => $rp->created_at?->toISOString(),
                        ];
                    })->values(),
                    'recent_resolved' => $recentResolvedReports->map(function ($rp) {
                        return [
                            'id'          => (int)$rp->id,
                            'user_id'     => $rp->user_id ? (int)$rp->user_id : null,
                            'reason'      => $rp->reason,
                            'details'     => $rp->details,
                            'created_at'  => $rp->created_at?->toISOString(),
                            'resolved_at' => $rp->resolved_at?->toISOString(),
                        ];
                    })->values(),
                ],
            ],
        ];

        return response()->json(['data' => $data]);
    }

}
