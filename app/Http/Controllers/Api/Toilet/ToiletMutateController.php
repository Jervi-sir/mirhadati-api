<?php

namespace App\Http\Controllers\Api\Toilet;

use App\Http\Controllers\Controller;
use App\Models\Toilet;
use App\Models\ToiletOpenHour;
use App\Models\ToiletPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ToiletMutateController extends Controller
{
    public function store(Request $r)
    {
        $data = $this->validatePayload($r);

        return DB::transaction(function () use ($data) {
            $data['owner_id'] = $data['owner_id'] ?? Auth::id();
            if (!empty($data['is_free'])) {
                $data['price_cents'] = null;
                $data['pricing_model'] = null;
            }

            /** @var Toilet $toilet */
            $toilet = Toilet::create([
                'owner_id'           => $data['owner_id'] ?? null,
                'toilet_category_id' => $data['toilet_category_id'],
                'wilaya_id'          => $data['wilaya_id'],
                'name'               => $data['name'],
                'description'        => $data['description'] ?? null,
                'phone_numbers'      => $data['phone_numbers'] ?? null,
                'lat'                => $data['lat'],
                'lng'                => $data['lng'],
                'address_line'       => $data['address_line'],
                'place_hint'         => $data['place_hint'] ?? null,
                'access_method'      => $data['access_method'],
                'capacity'           => $data['capacity'],
                'is_unisex'          => $data['is_unisex'] ?? true,
                'amenities'          => $data['amenities'] ?? null,
                'rules'              => $data['rules'] ?? null,
                'is_free'            => $data['is_free'],
                'price_cents'        => $data['price_cents'] ?? null,
                'pricing_model'      => $data['pricing_model'] ?? null,
                'status'             => 'active', // $data['status'] ?? 'pending',
            ]);

            if (!empty($data['photos'])) {
                $this->replacePhotos($toilet, $data['photos']);
            }
            if (!empty($data['open_hours'])) {
                $this->replaceOpenHours($toilet, $data['open_hours']);
            }

            $toilet->load(['photos', 'openHours', 'category', 'wilaya', 'owner']);

            return response()->json(['data' => $this->formatToilet($toilet)], 201);
        });
    }

    public function update(Request $r, Toilet $toilet)
    {
        // $this->authorize('update', $toilet);

        $data = $this->validatePayload($r, updating: true);

        return DB::transaction(function () use ($toilet, $data) {
            if (array_key_exists('is_free', $data) && $data['is_free']) {
                $data['price_cents'] = null;
                $data['pricing_model'] = null;
            }

            $toilet->update([
                'owner_id'           => $data['owner_id']           ?? $toilet->owner_id,
                'toilet_category_id' => $data['toilet_category_id'] ?? $toilet->toilet_category_id,
                'wilaya_id'          => $data['wilaya_id']          ?? $toilet->wilaya_id,
                'name'               => $data['name']               ?? $toilet->name,
                'description'        => array_key_exists('description', $data) ? $data['description'] : $toilet->description,
                'phone_numbers'      => array_key_exists('phone_numbers', $data) ? $data['phone_numbers'] : $toilet->phone_numbers,
                'lat'                => $data['lat']                ?? $toilet->lat,
                'lng'                => $data['lng']                ?? $toilet->lng,
                'address_line'       => $data['address_line']       ?? $toilet->address_line,
                'place_hint'         => array_key_exists('place_hint', $data) ? $data['place_hint'] : $toilet->place_hint,
                'access_method'      => $data['access_method']      ?? $toilet->access_method,
                'capacity'           => $data['capacity']           ?? $toilet->capacity,
                'is_unisex'          => array_key_exists('is_unisex', $data) ? $data['is_unisex'] : $toilet->is_unisex,
                'amenities'          => array_key_exists('amenities', $data) ? $data['amenities'] : $toilet->amenities,
                'rules'              => array_key_exists('rules', $data) ? $data['rules'] : $toilet->rules,
                'is_free'            => array_key_exists('is_free', $data) ? $data['is_free'] : $toilet->is_free,
                'price_cents'        => array_key_exists('price_cents', $data) ? $data['price_cents'] : $toilet->price_cents,
                'pricing_model'      => array_key_exists('pricing_model', $data) ? $data['pricing_model'] : $toilet->pricing_model,
                'status'             => $data['status'] ?? $toilet->status,
            ]);

            if (array_key_exists('photos', $data)) {
                $this->replacePhotos($toilet, $data['photos'] ?? []);
            }
            if (array_key_exists('open_hours', $data)) {
                $this->replaceOpenHours($toilet, $data['open_hours'] ?? []);
            }

            $toilet->load(['photos', 'openHours', 'category', 'wilaya', 'owner']);

            return response()->json(['data' => $this->formatToilet($toilet)]);
        });
    }

    protected function validatePayload(Request $r, bool $updating = false): array
    {
        $accessMethods = ['public', 'code', 'staff', 'key', 'app'];
        $pricingModels = ['flat', 'per-visit', 'per-30-min', 'per-60-min'];

        $rules = [
            'owner_id'           => ['nullable', 'exists:users,id'],
            'toilet_category_id' => [$updating ? 'sometimes' : 'required', 'exists:toilet_categories,id'],
            'wilaya_id'          => [$updating ? 'sometimes' : 'required', 'exists:wilayas,id'],

            'name'               => [$updating ? 'sometimes' : 'required', 'string', 'max:120'],
            'description'        => ['nullable', 'string', 'max:10000'],
            'phone_numbers'      => ['nullable', 'array', 'max:10'],
            'phone_numbers.*'    => ['string', 'max:40'],

            'lat'                => [$updating ? 'sometimes' : 'required', 'numeric', 'between:-90,90'],
            'lng'                => [$updating ? 'sometimes' : 'required', 'numeric', 'between:-180,180'],
            'address_line'       => [$updating ? 'sometimes' : 'required', 'string', 'max:180'],
            'place_hint'         => ['nullable', 'string', 'max:120'],

            'access_method'      => [$updating ? 'sometimes' : 'required', Rule::in($accessMethods)],
            'capacity'           => [$updating ? 'sometimes' : 'required', 'integer', 'min:1', 'max:500'],
            'is_unisex'          => ['sometimes', 'boolean'],

            'amenities'          => ['nullable', 'array', 'max:30'],
            'amenities.*'        => ['string', 'max:50'],
            'rules'              => ['nullable', 'array', 'max:30'],
            'rules.*'            => ['string', 'max:50'],

            'is_free'            => [$updating ? 'sometimes' : 'required', 'boolean'],
            'price_cents'        => ['nullable', 'integer', 'min:0'],
            'pricing_model'      => ['nullable', Rule::in($pricingModels)],

            'status'             => ['sometimes', Rule::in(['pending','active','suspended'])],

            // Photos (URLs generated by the uploader)
            'photos'             => ['sometimes', 'array', 'max:30'],
            'photos.*.url'       => ['required_with:photos', 'url', 'max:255'],
            'photos.*.is_cover'  => ['nullable', 'boolean'],

            // Optional opening hours
            'open_hours'                 => ['sometimes', 'array', 'max:70'],
            'open_hours.*.day_of_week'   => ['required_with:open_hours', 'integer', 'min:0', 'max:6'],
            'open_hours.*.opens_at'      => ['required_with:open_hours', 'date_format:H:i:s'],
            'open_hours.*.closes_at'     => ['required_with:open_hours', 'date_format:H:i:s'],
            'open_hours.*.sequence'      => ['nullable', 'integer', 'min:0', 'max:10'],
        ];

        $validated = validator($r->all(), $rules)->validate();

        if (array_key_exists('is_free', $validated) && $validated['is_free']) {
            $validated['price_cents'] = null;
            $validated['pricing_model'] = null;
        }

        return $validated;
    }

    protected function replacePhotos(Toilet $toilet, array $photos): void
    {
        $toilet->photos()->delete();

        $hasCover = false;
        foreach ($photos as $p) {
            $is = !empty($p['is_cover']);
            $hasCover = $hasCover || $is;

            ToiletPhoto::create([
                'toilet_id' => $toilet->id,
                'url'       => $p['url'],
                'is_cover'  => $is,
            ]);
        }

        if (!$hasCover && $toilet->photos()->exists()) {
            $first = $toilet->photos()->oldest('id')->first();
            $first->is_cover = true;
            $first->save();
        }

        $toilet->update(['photos_count' => $toilet->photos()->count()]);
    }

    protected function replaceOpenHours(Toilet $toilet, array $rows): void
    {
        $toilet->openHours()->delete();

        $payload = [];
        foreach ($rows as $r) {
            $payload[] = [
                'toilet_id'   => $toilet->id,
                'day_of_week' => (int)$r['day_of_week'],
                'opens_at'    => $r['opens_at'],
                'closes_at'   => $r['closes_at'],
                'sequence'    => isset($r['sequence']) ? (int)$r['sequence'] : 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }
        if ($payload) ToiletOpenHour::insert($payload);
    }

    /** Minimal inline formatter to ensure relations in response */
    protected function formatToilet(Toilet $t): array
    {
        $t->setRelation('photos', $t->photos()->orderByDesc('is_cover')->oldest('id')->get());

        return [
            'id'                 => (int)$t->id,
            'owner_id'           => $t->owner_id ? (int)$t->owner_id : null,
            'toilet_category_id' => (int)$t->toilet_category_id,
            'wilaya_id'          => (int)$t->wilaya_id,
            'name'               => (string)$t->name,
            'description'        => $t->description,
            'phone_numbers'      => $t->phone_numbers,
            'lat'                => (float)$t->lat,
            'lng'                => (float)$t->lng,
            'address_line'       => (string)$t->address_line,
            'place_hint'         => $t->place_hint,
            'access_method'      => (string)$t->access_method,
            'capacity'           => (int)$t->capacity,
            'is_unisex'          => (bool)$t->is_unisex,
            'amenities'          => $t->amenities,
            'rules'              => $t->rules,
            'is_free'            => (bool)$t->is_free,
            'price_cents'        => $t->price_cents ? (int)$t->price_cents : null,
            'pricing_model'      => $t->pricing_model,
            'status'             => (string)$t->status,
            'avg_rating'         => (float)$t->avg_rating,
            'reviews_count'      => (int)$t->reviews_count,
            'photos_count'       => (int)$t->photos_count,
            'created_at'         => $t->created_at?->toISOString(),
            'updated_at'         => $t->updated_at?->toISOString(),
            'photos'             => $t->photos->map(fn($p) => [
                'id'       => (int)$p->id,
                'url'      => (string)$p->url,
                'is_cover' => (bool)$p->is_cover,
            ])->values(),
            // include lightweight category/wilaya labels if you want:
            'category'           => $t->relationLoaded('category') ? [
                'id' => (int)$t->category->id,
                'code' => $t->category->code,
                'icon' => $t->category->icon,
                'label' => $t->category->fr ?? $t->category->en ?? $t->category->ar ?? $t->category->code,
            ] : null,
            'wilaya'             => $t->relationLoaded('wilaya') ? [
                'id' => (int)$t->wilaya->id,
                'number' => (int)$t->wilaya->number,
                'code' => $t->wilaya->code,
                'label' => $t->wilaya->fr ?? $t->wilaya->en ?? $t->wilaya->ar ?? $t->wilaya->code,
            ] : null,
        ];
    }
}
