<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiFormatter;
use App\Http\Controllers\Controller;
use App\Models\Toilet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ToiletSessionController extends Controller
{
    /** POST /api/toilets/{toilet}/sessions/start */
    public function start(Request $request, Toilet $toilet)
    {
        $data = $request->validate([
            'start_method' => 'nullable|string|max:20', // tap/qr/code
        ]);

        $id = DB::table('toilet_sessions')->insertGetId([
            'toilet_id'    => $toilet->id,
            'user_id'      => $request->user()->id,
            'started_at'   => now(),
            'ended_at'     => null,
            'charge_cents' => null,
            'start_method' => $data['start_method'] ?? 'tap',
            'end_method'   => null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $session = DB::table('toilet_sessions')->where('id', $id)->first();

        return response()->json([
            'message' => 'Session started',
            'data'    => ApiFormatter::toiletSession($session),
        ], 201);
    }

    /** POST /api/toilets/{toilet}/sessions/{sessionId}/end */
    public function end(Request $request, Toilet $toilet, int $sessionId)
    {
        $data = $request->validate([
            'end_method'   => 'nullable|string|max:20', // tap/auto/qr
            'charge_cents' => 'nullable|integer|min:0',
        ]);

        $session = DB::table('toilet_sessions')->where('id', $sessionId)->first();
        if (!$session || (int) $session->toilet_id !== (int) $toilet->id) {
            return response()->json(['message' => 'Session not found'], 404);
        }

        // only owner of session or admin can end
        $me = $request->user();
        $role = DB::table('roles')->where('id', $me->role_id)->value('code');
        $isAdmin = $role === 'admin';
        if (!$isAdmin && (int) $session->user_id !== (int) $me->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($session->ended_at) {
            return response()->json(['message' => 'Session already ended'], 422);
        }

        DB::table('toilet_sessions')
            ->where('id', $sessionId)
            ->update([
                'ended_at'     => now(),
                'end_method'   => $data['end_method'] ?? 'tap',
                'charge_cents' => $data['charge_cents'] ?? $session->charge_cents,
                'updated_at'   => now(),
            ]);

        $updated = DB::table('toilet_sessions')->where('id', $sessionId)->first();

        return response()->json([
            'message' => 'Session ended',
            'data'    => ApiFormatter::toiletSession($updated),
        ]);
    }

    /** GET /api/me/sessions */
    public function mySessions(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->input('perPage', 20)));

        $q = DB::table('toilet_sessions')
            ->join('toilets', 'toilet_sessions.toilet_id', '=', 'toilets.id')
            ->where('toilet_sessions.user_id', $request->user()->id)
            ->orderByDesc('toilet_sessions.started_at')
            ->select('toilet_sessions.*', 'toilets.name as toilet_name');

        $page = $q->paginate($perPage);

        // Format rows with ApiFormatter::toiletSession and append toilet_name
        $items = array_map(function ($row) {
            $item = ApiFormatter::toiletSession($row);
            $item['toilet_name'] = $row->toilet_name ?? null;
            return $item;
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
}
