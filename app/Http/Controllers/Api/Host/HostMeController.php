<?php

namespace App\Http\Controllers\Api\Host;

use App\Helpers\ApiFormatter;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Toilet;

class HostMeController extends Controller
{
    /**
     * GET /api/host/me
     * Returns authenticated host info + a couple of quick stats.
     */
    public function __invoke(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        // Allow only host or admin to use the host area
        $roleName = $user->role->code;
        if (!in_array($roleName, ['host', 'admin'], true)) {
            abort(403, 'Only hosts/admins can access host endpoints.');
        }

        // Quick counts for dashboard
        $totalToilets   = Toilet::where('owner_id', $user->id)->count();
        $activeToilets  = Toilet::where('owner_id', $user->id)->where('status', 'active')->count();
        $pendingToilets = Toilet::where('owner_id', $user->id)->where('status', 'pending')->count();

        $user->load(['role']);

        $data = ApiFormatter::auth($user);
        $data['stats'] = [
            'toilets' => [
                'total'   => (int) $totalToilets,
                'active'  => (int) $activeToilets,
                'pending' => (int) $pendingToilets,
            ],
        ];

        return response()->json($data);
    }
}
