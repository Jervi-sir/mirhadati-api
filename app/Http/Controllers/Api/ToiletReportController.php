<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiFormatter;
use App\Http\Controllers\Controller;
use App\Models\Toilet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class ToiletReportController extends Controller
{
    /** POST /api/toilets/{toilet}/reports */
    public function store(Request $request, Toilet $toilet)
    {
        $data = $request->validate([
            'reason'  => ['required', Rule::in(['closed','fake','unsafe','harassment','other'])],
            'details' => 'nullable|string|max:2000',
        ]);

        $id = DB::table('toilet_reports')->insertGetId([
            'toilet_id'   => $toilet->id,
            'user_id'     => $request->user()->id,
            'reason'      => $data['reason'],
            'details'     => $data['details'] ?? null,
            'resolved_at' => null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $report = DB::table('toilet_reports')->where('id', $id)->first();

        return response()->json([
            'message' => 'Report submitted',
            'data'    => ApiFormatter::toiletReport($report),
        ], 201);
    }

    /** GET /api/toilets/{toilet}/reports (owner/admin only) */
    public function index(Request $request, Toilet $toilet)
    {
        $me = $request->user();
        $role = DB::table('roles')->where('id', $me->role_id)->value('code');
        $isAdmin = $role === 'admin';

        if (! $isAdmin && (int)$toilet->owner_id !== (int)$me->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $perPage = min(100, max(1, (int)$request->input('perPage', 20)));

        $q = DB::table('toilet_reports')
            ->leftJoin('users', 'toilet_reports.user_id', '=', 'users.id')
            ->where('toilet_reports.toilet_id', $toilet->id)
            ->orderByDesc('toilet_reports.created_at')
            ->select('toilet_reports.*', 'users.name as reporter_name');

        $page = $q->paginate($perPage);

        // Format each report row with ApiFormatter
        $items = array_map(function ($row) {
            return ApiFormatter::toiletReport($row);
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

    /** POST /api/toilets/{toilet}/reports/{reportId}/resolve (owner/admin) */
    public function resolve(Request $request, Toilet $toilet, int $reportId)
    {
        $me = $request->user();
        $role = DB::table('roles')->where('id', $me->role_id)->value('code');
        $isAdmin = $role === 'admin';

        if (! $isAdmin && (int)$toilet->owner_id !== (int)$me->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $affected = DB::table('toilet_reports')
            ->where('id', $reportId)
            ->where('toilet_id', $toilet->id)
            ->update([
                'resolved_at' => now(),
                'updated_at'  => now(),
            ]);

        if (!$affected) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        $report = DB::table('toilet_reports')
            ->leftJoin('users', 'toilet_reports.user_id', '=', 'users.id')
            ->select('toilet_reports.*', 'users.name as reporter_name')
            ->where('toilet_reports.id', $reportId)
            ->first();

        return response()->json([
            'message' => 'Report resolved',
            'data'    => ApiFormatter::toiletReport($report),
        ]);
    }
}
