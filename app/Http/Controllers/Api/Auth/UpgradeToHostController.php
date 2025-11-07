<?php

namespace App\Http\Controllers\Api\Auth;

use App\Helpers\ApiFormatter;
use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpgradeToHostController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        // Fetch or create the host role
        $hostRole = Role::firstOrCreate(['code' => Role::HOST]);

        // Idempotency: already host?
        if ((string) optional($user->role)->code === Role::HOST || (int) $user->role_id === (int) $hostRole->id) {
            return response()->json([
                'message' => 'You are already a host.',
                'data'    => $user->load('role'),
            ], 200);
        }

        DB::transaction(function () use ($user, $hostRole) {
            $user->role_id = $hostRole->id;
            $user->save();
        });

        // Re-load with role relation
        $user->load('role');

        return response()->json([
            'message' => 'Upgraded to host successfully.',
            'data'    => ApiFormatter::auth($user),
        ], 200);
    }

}
