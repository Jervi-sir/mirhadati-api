<?php

namespace App\Http\Controllers\Api\Auth;

use App\Helpers\ApiFormatter;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = Auth::user();

        // Helpers — keep leading 0, don't force +213
        $sanitizePhone = function (?string $raw) {
            if ($raw === null) return null;
            $only = preg_replace('/[^\d+]/', '', $raw); // keep digits and plus
            return $only[0] === '+'
                ? '+' . preg_replace('/\+/', '', substr($only, 1))
                : preg_replace('/\+/', '', $only);
        };

        $normalizeDZ = function (?string $s) {
            if (!$s) return $s;
            if (str_starts_with($s, '+')) return $s;   // keep international as-is
            if (str_starts_with($s, '00')) return substr($s, 2); // drop "00"
            return $s; // keep leading 0 if present
        };

        // All fields optional; only validate those present
        $data = $request->validate([
            'name'                  => ['sometimes', 'string', 'max:100'],
            'email'                 => ['sometimes', 'nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'                 => ['sometimes', 'string', 'max:40', Rule::unique('users', 'phone')->ignore($user->id)],
            // you can add a loose shape if you like:
            // 'phone'              => ['sometimes', 'string', 'max:40', 'regex:/^\+?\d{7,15}$/', Rule::unique(...)]
            'password'              => ['sometimes', 'string', 'min:6', 'confirmed'],
            'password_confirmation' => ['sometimes', 'string', 'min:6'], // only checked when 'password' present (via 'confirmed')
        ]);

        // Apply only provided fields
        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
        }

        if (array_key_exists('email', $data)) {
            $user->email = $data['email'] ?? null;
        }

        if (array_key_exists('phone', $data)) {
            $phone = $normalizeDZ($sanitizePhone($data['phone'] ?? ''));
            $user->phone = $phone;
        }

        if (array_key_exists('password', $data) && !empty($data['password'])) {
            $user->password = Hash::make($data['password']);
            // Dev-only: sync plain text if column exists (NEVER in prod)
            if (config('app.env') !== 'production' && Schema::hasColumn('users', 'password_plain_text')) {
                $user->password_plain_text = $data['password'];
            }
        }

        // If nothing provided, still return current profile
        if ($user->isDirty()) {
            $user->save();
        }

        $user->load(['role', 'wilaya']);

        return response()->json([
            'data' => ApiFormatter::auth($user),
        ]);
    }
}
