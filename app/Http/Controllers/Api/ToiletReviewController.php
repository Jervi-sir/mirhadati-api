<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiFormatter;
use App\Http\Controllers\Controller;
use App\Models\Toilet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ToiletReviewController extends Controller
{
    /** GET /api/toilets/{toilet}/reviews */
    public function index(Request $request, Toilet $toilet)
    {
        $perPage = min(100, max(1, (int)$request->input('perPage', 20)));

        $q = DB::table('toilet_reviews')
            ->join('users', 'toilet_reviews.user_id', '=', 'users.id')
            ->where('toilet_reviews.toilet_id', $toilet->id)
            ->orderByDesc('toilet_reviews.created_at')
            ->select('toilet_reviews.*', 'users.name as author_name');

        $page = $q->paginate($perPage);

        $items = array_map(function ($row) {
            return ApiFormatter::toiletReview($row);
        }, $page->items());

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
                'has_more'     => $page->hasMorePages(),
            ],
        ]);
    }

    /** POST /api/toilets/{toilet}/reviews */
    public function store(Request $request, Toilet $toilet)
    {
        $data = $request->validate([
            'rating'      => 'required|integer|min:1|max:5',
            'text'        => 'nullable|string|max:2000',
            'cleanliness' => 'nullable|integer|min:1|max:5',
            'smell'       => 'nullable|integer|min:1|max:5',
            'stock'       => 'nullable|integer|min:1|max:5',
        ]);

        // Upsert by unique(toilet_id,user_id)
        $now = now();
        DB::table('toilet_reviews')->updateOrInsert(
            ['toilet_id' => $toilet->id, 'user_id' => $request->user()->id],
            [
                ...$data,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $this->recalcAggregates($toilet->id);

        $review = DB::table('toilet_reviews')
            ->leftJoin('users', 'toilet_reviews.user_id', '=', 'users.id')
            ->select('toilet_reviews.*', 'users.name as author_name')
            ->where('toilet_reviews.toilet_id', $toilet->id)
            ->where('toilet_reviews.user_id', $request->user()->id)
            ->first();

        return response()->json([
            'message' => 'Review saved',
            'data'    => ApiFormatter::toiletReview($review),
        ], 201);
    }

    /** PATCH /api/toilets/{toilet}/reviews/me  (edit my review) */
    public function updateMine(Request $request, Toilet $toilet)
    {
        $data = $request->validate([
            'rating'      => 'sometimes|integer|min:1|max:5',
            'text'        => 'nullable|string|max:2000',
            'cleanliness' => 'nullable|integer|min:1|max:5',
            'smell'       => 'nullable|integer|min:1|max:5',
            'stock'       => 'nullable|integer|min:1|max:5',
        ]);

        $affected = DB::table('toilet_reviews')
            ->where('toilet_id', $toilet->id)
            ->where('user_id', $request->user()->id)
            ->update([...$data, 'updated_at' => now()]);

        if (!$affected) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        $this->recalcAggregates($toilet->id);

        $review = DB::table('toilet_reviews')
            ->leftJoin('users', 'toilet_reviews.user_id', '=', 'users.id')
            ->select('toilet_reviews.*', 'users.name as author_name')
            ->where('toilet_reviews.toilet_id', $toilet->id)
            ->where('toilet_reviews.user_id', $request->user()->id)
            ->first();

        return response()->json([
            'message' => 'Review updated',
            'data'    => ApiFormatter::toiletReview($review),
        ]);
    }

    /** DELETE /api/toilets/{toilet}/reviews/me */
    public function destroyMine(Request $request, Toilet $toilet)
    {
        $deleted = DB::table('toilet_reviews')
            ->where('toilet_id', $toilet->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        $this->recalcAggregates($toilet->id);

        return response()->json(['message' => 'Review deleted']);
    }

    private function recalcAggregates(int $toiletId): void
    {
        $stats = DB::table('toilet_reviews')
            ->where('toilet_id', $toiletId)
            ->selectRaw('COUNT(*) as c, COALESCE(AVG(rating),0) as avg')
            ->first();

        DB::table('toilets')
            ->where('id', $toiletId)
            ->update([
                'reviews_count' => (int) $stats->c,
                'avg_rating'    => round((float) $stats->avg, 2),
                'updated_at'    => now(),
            ]);
    }
}
