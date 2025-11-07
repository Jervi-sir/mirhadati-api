<?php

namespace App\Http\Controllers\Api\Auth;

use App\Helpers\ApiFormatter;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** POST /api/auth/register */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'phone'      => 'required|string|max:40|unique:users,phone',
            'password'   => 'required|string|min:6',
            'email'      => 'nullable|email|unique:users,email',
            'wilaya_id'  => 'nullable|exists:wilayas,id', // make it required if you prefer
        ]);

        // (Optional) normalize phone: strip spaces
        $data['phone'] = trim(preg_replace('/\s+/', '', $data['phone']));

        // ensure default "user" role exists
        $roleId = DB::table('roles')->where('code', 'user')->value('id');
        if (!$roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'code' => 'user',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $user = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'password' => Hash::make($data['password']),
            'password_plain_text' => $data['password'], // ⚠️ dev-only; remove in prod
            'role_id' => $roleId,
            'wilaya_id' => $data['wilaya_id'] ?? null,
        ]);

        $token = $user->createToken('api_token')->plainTextToken;
        $user->load(['wilaya']);

        return response()->json([
            'message' => 'Registered successfully',
            'data' => [
                'user' => ApiFormatter::auth($user),
                'token' => $token,
            ],
        ], 201);
    }

    /** POST /api/auth/login */
    public function login(Request $request)
    {
        $data = $request->validate([
            'phone'    => 'required|string|max:40',
            'password' => 'required|string',
        ]);

        $phone = trim(preg_replace('/\s+/', '', $data['phone']));
        $user = User::where('phone', $phone)->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['Invalid credentials.'],
            ]);
        }

        // single-session policy (optional)
        $user->tokens()->delete();
        $token = $user->createToken('api_token')->plainTextToken;
        $user->load(['wilaya']);

        return response()->json([
            'message' => 'Logged in successfully',
            'data' => [
                'user' => ApiFormatter::auth($user),
                'token' => $token,
            ],
        ]);
    }

    /** GET /api/auth/me */
    public function me(Request $request)
    {
        $user = Auth::user(); // the currently authenticated user
        // eager-load related models
        $user->load(['role', 'wilaya']);
        return response()->json([
            'data' => ApiFormatter::auth($user),
        ]);
    }

    /** POST /api/auth/logout */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
