<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiFormatter;
use App\Http\Controllers\Controller;
use App\Models\Toilet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ToiletController extends Controller
{
    /** Utility: check if current user can manage this toilet (admin or owner) */
    private function authorizeManage(Request $request, Toilet $toilet = null): void
    {
        $user = $request->user();
        if (!$user) abort(401);

        $roleCode = DB::table('roles')->where('id', $user->role_id)->value('code');
        $isAdmin = $roleCode === 'admin';

        if ($toilet) {
            if ($isAdmin || (int)$toilet->owner_id === (int)$user->id) return;
            abort(403, 'Not allowed to manage this toilet.');
        }
        // Creating: hosts/admins only (relax if you want)
        if (!$isAdmin && $roleCode !== 'host') {
            abort(403, 'Only hosts or admins can create toilets.');
        }
    }


    /**
     * GET /api/toilets/{toilet}
     */
    public function show(Request $r, Toilet $toilet)
    {
        // Hide non-active unless owner/admin
        if ($toilet->status !== 'active') {
            $me = $r->user();
            $role = $me ? DB::table('roles')->where('id', $me->role_id)->value('code') : null;
            if (!$me || ($role !== 'admin' && (int)$toilet->owner_id !== (int)$me->id)) {
                abort(403, 'This toilet is not public.');
            }
        }

        return response()->json(['data' => ApiFormatter::toilet($toilet)]);
    }

    /**
     * POST /api/toilets  (Create “offer” = create toilet listing)
     */
    public function store(Request $r)
    {
        $this->authorizeManage($r);

        $data = $r->validate([
            'toilet_category_id' => 'required|exists:toilet_categories,id',
            'name'               => 'required|string|max:120',
            'description'        => 'nullable|string|max:2000',
            'phone_numbers'      => 'nullable|array|max:3',
            'phone_numbers.*'    => 'string|max:30',
            'lat'                => 'required|numeric|between:-90,90',
            'lng'                => 'required|numeric|between:-180,180',
            'address_line'       => 'required|string|max:180',
            'wilaya_id'          => 'required|exists:wilayas,id',
            'place_hint'         => 'nullable|string|max:120',

            'access_method'      => ['required', Rule::in(['public', 'code', 'staff', 'key', 'app'])],
            'capacity'           => 'nullable|integer|min:1|max:50',
            'is_unisex'          => 'boolean',

            'amenities'          => 'nullable|array|max:10',
            'amenities.*'        => 'string|max:40',
            'rules'              => 'nullable|array|max:10',
            'rules.*'            => 'string|max:40',

            'is_free'            => 'required|boolean',
            'price_cents'        => 'nullable|integer|min:0',
            'pricing_model'      => ['nullable', Rule::in(['flat', 'per-visit', 'per-30-min', 'per-60-min'])],

            'status'             => ['nullable', Rule::in(['pending', 'active', 'suspended'])],
        ]);

        // price consistency
        if ($data['is_free']) {
            $data['price_cents'] = null;
            $data['pricing_model'] = null;
        } else {
            if ($data['price_cents'] === null) {
                return response()->json(['message' => 'price_cents is required when not free'], 422);
            }
        }

        $toilet = new Toilet();
        $toilet->fill([
            ...$data,
            'owner_id' => $r->user()->id,
        ]);
        if (isset($data['phone_numbers'])) $toilet->phone_numbers = array_values($data['phone_numbers']);
        if (isset($data['amenities']))     $toilet->amenities     = array_values($data['amenities']);
        if (isset($data['rules']))         $toilet->rules         = array_values($data['rules']);

        if (empty($data['status'])) $toilet->status = 'pending';

        $toilet->save();

        return response()->json([
            'message' => 'Toilet created',
            'data'    => ApiFormatter::toilet($toilet),
        ], 201);
    }

    /**
     * PUT/PATCH /api/toilets/{toilet}
     */
    public function update(Request $r, Toilet $toilet)
    {
        $this->authorizeManage($r, $toilet);

        $data = $r->validate([
            'toilet_category_id' => 'sometimes|exists:toilet_categories,id',
            'name'               => 'sometimes|string|max:120',
            'description'        => 'nullable|string|max:2000',
            'phone_numbers'      => 'nullable|array|max:3',
            'phone_numbers.*'    => 'string|max:30',
            'lat'                => 'sometimes|numeric|between:-90,90',
            'lng'                => 'sometimes|numeric|between:-180,180',
            'address_line'       => 'sometimes|string|max:180',
            'wilaya_id'          => 'sometimes|exists:wilayas,id',
            'place_hint'         => 'nullable|string|max:120',

            'access_method'      => ['sometimes', Rule::in(['public', 'code', 'staff', 'key', 'app'])],
            'capacity'           => 'nullable|integer|min:1|max:50',
            'is_unisex'          => 'boolean',

            'amenities'          => 'nullable|array|max:10',
            'amenities.*'        => 'string|max:40',
            'rules'              => 'nullable|array|max:10',
            'rules.*'            => 'string|max:40',

            'is_free'            => 'boolean',
            'price_cents'        => 'nullable|integer|min:0',
            'pricing_model'      => ['nullable', Rule::in(['flat', 'per-visit', 'per-30-min', 'per-60-min'])],

            'status'             => ['nullable', Rule::in(['pending', 'active', 'suspended'])],
        ]);

        // price consistency if toggled
        if (array_key_exists('is_free', $data)) {
            if ($data['is_free']) {
                $data['price_cents'] = null;
                $data['pricing_model'] = null;
            } else {
                if (array_key_exists('price_cents', $data) && $data['price_cents'] === null) {
                    return response()->json(['message' => 'price_cents is required when not free'], 422);
                }
            }
        }

        $toilet->fill($data);
        if (array_key_exists('phone_numbers', $data)) $toilet->phone_numbers = $data['phone_numbers'] ?? null;
        if (array_key_exists('amenities', $data))     $toilet->amenities     = $data['amenities'] ?? null;
        if (array_key_exists('rules', $data))         $toilet->rules         = $data['rules'] ?? null;

        $toilet->save();

        return response()->json([
            'message' => 'Toilet updated',
            'data'    => ApiFormatter::toilet($toilet),
        ]);
    }

    /**
     * DELETE /api/toilets/{toilet}
     */
    public function destroy(Request $r, Toilet $toilet)
    {
        $this->authorizeManage($r, $toilet);
        $toilet->delete();

        return response()->json(['message' => 'Toilet deleted']);
    }

    /**
     * POST /api/toilets/{toilet}/status
     * Light helper to change status (pending/active/suspended)
     */
    public function setStatus(Request $r, Toilet $toilet)
    {
        $this->authorizeManage($r, $toilet);

        $data = $r->validate([
            'status' => ['required', Rule::in(['pending', 'active', 'suspended'])],
        ]);

        $toilet->update(['status' => $data['status']]);

        return response()->json([
            'message' => 'Status updated',
            'data'    => ApiFormatter::toilet($toilet),
        ]);
    }
}
