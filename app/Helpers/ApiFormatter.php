<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ApiFormatter
{
    /* ----------------------- Auth ----------------------- */
    public static function auth($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'role_code' => $user->role?->code,
            'wilaya' => $user->wilaya ? [
                'id' => $user->wilaya->id,
                'code' => $user->wilaya->code,
                'number' => $user->wilaya->number,
                'en' => $user->wilaya->en,
                'fr' => $user->wilaya->fr,
                'ar' => $user->wilaya->ar,
                'center_lat' => $user->wilaya->center_lat,
                'center_lng' => $user->wilaya->center_lng,
                'default_radius_km' => $user->wilaya->default_radius_km,
                'min_lat' => $user->wilaya->min_lat,
                'max_lat' => $user->wilaya->max_lat,
                'min_lng' => $user->wilaya->min_lng,
                'max_lng' => $user->wilaya->max_lng,
            ] : null,
            'created_at' => $user->created_at?->toISOString(),
        ];
    }

    /* ---------------------------- Toilets ---------------------------- */
    /**
     * Format a Toilet model/row.
     *
     * Options:
     * - all: bool => if true, include all fields/relations (same as include=all)
     * - include: array|string|true|'all'|'*' => which fields/relations to include (null/empty => all)
     * - groups: array|string|'all' => shortcut bundles: coords, pricing, labels, counts, meta, relations
     * - exclude: array => keys to always remove (overrides include)
     * - drop_nulls: bool => remove keys with null values
     * - drop_empty_arrays: bool => remove keys with empty arrays
     */
    public static function toilet($t, array $opts = []): array
    {
        // Quick "all" switch
        if (!empty($opts['all'])) {
            $opts['include'] = null; // null => ALL
            $opts['groups']  = null;
        }

        // ------- options -------
        $include   = self::normalizeIncludes($opts['include'] ?? null, $opts['groups'] ?? null);
        $exclude   = array_flip($opts['exclude'] ?? []);
        $dropNulls = (bool)($opts['drop_nulls'] ?? false);
        $dropEmpty = (bool)($opts['drop_empty_arrays'] ?? false);

        // helper to decide if a key should be included before computing it
        $want = function (string $key) use ($include, $exclude): bool {
            if (isset($exclude[$key])) return false;
            // include === null => include everything
            if ($include === null) return true;
            return in_array($key, $include, true);
        };

        $out = [];

        /* --------- Scalars (compute only if wanted) --------- */
        if ($want('id'))                  $out['id']                 = (int) self::getProp($t, 'id');
        if ($want('owner_id'))            $out['owner_id']           = self::hasProp($t, 'owner_id') ? (int) self::getProp($t, 'owner_id') : null;
        if ($want('toilet_category_id'))  $out['toilet_category_id'] = (int) self::getProp($t, 'toilet_category_id');

        if ($want('name'))                $out['name']               = (string) self::getProp($t, 'name');
        if ($want('description'))         $out['description']        = self::nullableString(self::getProp($t, 'description', null));
        if ($want('phone_numbers'))       $out['phone_numbers']      = self::jsonToArray(self::getProp($t, 'phone_numbers', null));

        if ($want('lat'))                 $out['lat']                = self::toFloat(self::getProp($t, 'lat'));
        if ($want('lng'))                 $out['lng']                = self::toFloat(self::getProp($t, 'lng'));
        if ($want('address_line'))        $out['address_line']       = (string) self::getProp($t, 'address_line');
        if ($want('wilaya_id'))           $out['wilaya_id']          = (int) self::getProp($t, 'wilaya_id');
        if ($want('place_hint'))          $out['place_hint']         = self::nullableString(self::getProp($t, 'place_hint', null));

        if ($want('access_method'))       $out['access_method']      = (string) self::getProp($t, 'access_method');
        if ($want('capacity'))            $out['capacity']           = self::hasProp($t, 'capacity') ? (int) self::getProp($t, 'capacity') : 1;
        if ($want('is_unisex'))           $out['is_unisex']          = self::toBool(self::getProp($t, 'is_unisex', true));

        if ($want('amenities'))           $out['amenities']          = self::jsonToArray(self::getProp($t, 'amenities', null));
        if ($want('rules'))               $out['rules']              = self::jsonToArray(self::getProp($t, 'rules', null));
        if ($want('amenities_meta')) {
            $codes = $out['amenities'] ?? self::jsonToArray(self::getProp($t, 'amenities', null));
            $out['amenities_meta'] = self::buildCodeMetaList($codes, self::getAmenities());
        }

        if ($want('rules_meta')) {
            $codes = $out['rules'] ?? self::jsonToArray(self::getProp($t, 'rules', null));
            $out['rules_meta'] = self::buildCodeMetaList($codes, self::getRules());
        }

        if ($want('is_free'))             $out['is_free']            = self::toBool(self::getProp($t, 'is_free', true));
        if ($want('price_cents'))         $out['price_cents']        = self::hasProp($t, 'price_cents') ? self::toIntOrNull(self::getProp($t, 'price_cents')) : null;
        if ($want('pricing_model'))       $out['pricing_model']      = self::nullableString(self::getProp($t, 'pricing_model', null));

        if ($want('status'))              $out['status']             = (string) self::getProp($t, 'status');
        if ($want('avg_rating'))          $out['avg_rating']         = self::hasProp($t, 'avg_rating') ? (float) self::getProp($t, 'avg_rating') : 0.0;
        if ($want('reviews_count'))       $out['reviews_count']      = self::hasProp($t, 'reviews_count') ? (int) self::getProp($t, 'reviews_count') : 0;

        if ($want('photos_count'))        $out['photos_count']       = self::hasProp($t, 'photos_count') ? (int) self::getProp($t, 'photos_count') : 0;

        if ($want('created_at'))          $out['created_at']         = self::iso(self::getProp($t, 'created_at', null));
        if ($want('updated_at'))          $out['updated_at']         = self::iso(self::getProp($t, 'updated_at', null));

        // ---- Cover photo URL (no extra queries; uses provided fields/loaded relations) ----
        if ($want('cover_photo')) {
            $cover = null;

            // 1) Prefer explicit field from controller subselect (cheap, already present)
            if (self::hasProp($t, 'cover_photo')) {
                $cover = self::getProp($t, 'cover_photo');
            } elseif (self::hasProp($t, 'cover_photo_url')) {
                $cover = self::getProp($t, 'cover_photo_url');
            }

            // 2) If not present, inspect loaded photos relation / photos prop
            if ($cover === null || $cover === '') {
                $photos = null;

                if (self::isModel($t) && self::relLoaded($t, 'photos')) {
                    $photos = $t->photos; // Eloquent Collection
                } elseif (self::hasProp($t, 'photos')) {
                    $photos = self::getProp($t, 'photos'); // array/iterable of photos
                }

                if ($photos && is_iterable($photos)) {
                    $coverObj = null;

                    // (a) try a photo marked as cover
                    foreach ($photos as $p) {
                        if (self::toBool(self::getProp($p, 'is_cover', false))) {
                            $coverObj = $p;
                            break;
                        }
                    }

                    // (b) fallback: first photo
                    if (!$coverObj) {
                        foreach ($photos as $p) {
                            $coverObj = $p;
                            break;
                        }
                    }

                    if ($coverObj) {
                        $cover = self::getProp($coverObj, 'url', null);
                    }
                }
            }

            $out['cover_photo'] = self::nullableString($cover);
        }


        /* ----------- Conditional relations (only if wanted & present) ----------- */

        if ($want('category')) {
            $cat = null;
            if (self::isModel($t) && self::relLoaded($t, 'category'))        $cat = $t->category;
            elseif (self::hasProp($t, 'category'))                           $cat = self::getProp($t, 'category');
            if ($cat) $out['category'] = self::toiletCategory($cat);
        }

        if ($want('wilaya')) {
            $wil = null;
            if (self::isModel($t) && self::relLoaded($t, 'wilaya'))          $wil = $t->wilaya;
            elseif (self::hasProp($t, 'wilaya'))                             $wil = self::getProp($t, 'wilaya');
            if ($wil) {
                $wil_obj = self::wilaya($wil);
                $out['wilaya'] = $wil_obj;
                $out['wilaya_text'] = [
                    'code'  => $wil_obj['number'] . ' - ' . $wil_obj['code'],
                    'en'    => $wil_obj['number'] . ' - ' . $wil_obj['en'],
                    'fr'    => $wil_obj['number'] . ' - ' . $wil_obj['fr'],
                    'ar'    => $wil_obj['number'] . ' - ' . $wil_obj['ar'],
                ];
            }
        }

        if ($want('owner')) {
            $own = null;
            if (self::isModel($t) && self::relLoaded($t, 'owner'))           $own = $t->owner;
            elseif (self::hasProp($t, 'owner'))                              $own = self::getProp($t, 'owner');
            if ($own) {
                $out['owner'] = [
                    'id'   => isset($own->id) ? (int) $own->id : (isset($own['id']) ? (int) $own['id'] : null),
                    'name' => isset($own->name) ? (string) $own->name : (isset($own['name']) ? (string) $own['name'] : null),
                ];
            }
        }

        if ($want('photos')) {
            $photos = null;
            if (self::isModel($t) && self::relLoaded($t, 'photos'))          $photos = $t->photos;
            elseif (self::hasProp($t, 'photos'))                              $photos = self::getProp($t, 'photos');
            if ($photos) $out['photos'] = self::list($photos, [self::class, 'toiletPhoto']);
        }

        if ($want('open_hours')) {
            $hours = null;
            if (self::isModel($t) && self::relLoaded($t, 'openHours'))       $hours = $t->openHours;
            elseif (self::hasProp($t, 'open_hours'))                          $hours = self::getProp($t, 'open_hours');
            if ($hours) $out['open_hours'] = self::list($hours, [self::class, 'toiletOpenHour']);
        }

        if ($want('is_favorite')) {
            if (self::hasProp($t, 'is_favorite')) {
                $out['is_favorite'] = self::toBool(self::getProp($t, 'is_favorite'));
            } elseif (self::isModel($t) && self::relLoaded($t, 'favorites')) {
                $out['is_favorite'] = (bool) ($t->favorites?->count() ?? 0);
            }
        }

        /* ------------------------ Optional cleanup ------------------------ */
        if ($dropNulls || $dropEmpty) {
            foreach ($out as $k => $v) {
                if ($dropNulls && $v === null) {
                    unset($out[$k]);
                    continue;
                }
                if ($dropEmpty && is_array($v) && empty($v)) {
                    unset($out[$k]);
                }
            }
        }

        return $out;
    }

    /* ----------------------- Toilet Categories ----------------------- */
    public static function toiletCategory($c): array
    {
        return [
            'id'    => (int) self::getProp($c, 'id'),
            'code'  => (string) self::getProp($c, 'code'),
            'icon'  => self::nullableString(self::getProp($c, 'icon', null)),
            'en'    => self::nullableString(self::getProp($c, 'en', null)),
            'fr'    => self::nullableString(self::getProp($c, 'fr', null)),
            'ar'    => self::nullableString(self::getProp($c, 'ar', null)),

            'created_at' => self::iso(self::getProp($c, 'created_at', null)),
            'updated_at' => self::iso(self::getProp($c, 'updated_at', null)),
        ];
    }

    /* ------------------------- Toilet Photos ------------------------- */
    public static function toiletPhoto($p): array
    {
        return [
            'id'         => (int) self::getProp($p, 'id'),
            'toilet_id'  => (int) self::getProp($p, 'toilet_id'),
            'url'        => (string) self::getProp($p, 'url'),
            'is_cover'   => self::toBool(self::getProp($p, 'is_cover', false)),

            'created_at' => self::iso(self::getProp($p, 'created_at', null)),
            'updated_at' => self::iso(self::getProp($p, 'updated_at', null)),
        ];
    }

    /* ---------------------- Toilet Opening Hours --------------------- */
    public static function toiletOpenHour($h): array
    {
        return [
            'id'           => (int) self::getProp($h, 'id'),
            'toilet_id'    => (int) self::getProp($h, 'toilet_id'),
            'day_of_week'  => (int) self::getProp($h, 'day_of_week'),
            'opens_at'     => (string) self::getProp($h, 'opens_at'),
            'closes_at'    => (string) self::getProp($h, 'closes_at'),
            'sequence'     => self::hasProp($h, 'sequence') ? (int) self::getProp($h, 'sequence') : 0,

            'created_at'   => self::iso(self::getProp($h, 'created_at', null)),
            'updated_at'   => self::iso(self::getProp($h, 'updated_at', null)),
        ];
    }

    /* ------------------------ Toilet Sessions ------------------------ */
    public static function toiletSession($s): array
    {
        return [
            'id'           => (int) self::getProp($s, 'id'),
            'toilet_id'    => (int) self::getProp($s, 'toilet_id'),
            'user_id'      => self::toIntOrNull(self::getProp($s, 'user_id', null)),

            'started_at'   => self::iso(self::getProp($s, 'started_at', null)),
            'ended_at'     => self::iso(self::getProp($s, 'ended_at', null)),
            'charge_cents' => self::toIntOrNull(self::getProp($s, 'charge_cents', null)),

            'start_method' => self::nullableString(self::getProp($s, 'start_method', null)),
            'end_method'   => self::nullableString(self::getProp($s, 'end_method', null)),

            'created_at'   => self::iso(self::getProp($s, 'created_at', null)),
            'updated_at'   => self::iso(self::getProp($s, 'updated_at', null)),
        ];
    }

    /* ------------------------- Toilet Reviews ------------------------ */
    public static function toiletReview($r): array
    {
        return [
            'id'           => (int) self::getProp($r, 'id'),
            'toilet_id'    => (int) self::getProp($r, 'toilet_id'),
            'user_id'      => (int) self::getProp($r, 'user_id'),

            'rating'       => (int) self::getProp($r, 'rating'),
            'text'         => self::nullableString(self::getProp($r, 'text', null)),
            'cleanliness'  => self::toIntOrNull(self::getProp($r, 'cleanliness', null)),
            'smell'        => self::toIntOrNull(self::getProp($r, 'smell', null)),
            'stock'        => self::toIntOrNull(self::getProp($r, 'stock', null)),

            'author_name'  => self::hasProp($r, 'author_name') ? (string) self::getProp($r, 'author_name') : null,

            'created_at'   => self::iso(self::getProp($r, 'created_at', null)),
            'updated_at'   => self::iso(self::getProp($r, 'updated_at', null)),
        ];
    }

    /* ------------------------- Toilet Reports ------------------------ */
    public static function toiletReport($rp): array
    {
        return [
            'id'            => (int) self::getProp($rp, 'id'),
            'toilet_id'     => (int) self::getProp($rp, 'toilet_id'),
            'user_id'       => self::toIntOrNull(self::getProp($rp, 'user_id', null)),

            'reason'        => (string) self::getProp($rp, 'reason'),
            'details'       => self::nullableString(self::getProp($rp, 'details', null)),
            'resolved_at'   => self::iso(self::getProp($rp, 'resolved_at', null)),

            'reporter_name' => self::hasProp($rp, 'reporter_name') ? (string) self::getProp($rp, 'reporter_name') : null,

            'created_at'    => self::iso(self::getProp($rp, 'created_at', null)),
            'updated_at'    => self::iso(self::getProp($rp, 'updated_at', null)),
        ];
    }

    /* ------------------------------ Wilaya --------------------------- */
    public static function wilaya($w): array
    {
        return [
            'id'                => (int) $w->id,
            'code'              => (string) $w->code,
            'number'            => (int) $w->number,
            'en'                => self::nullableString($w->en ?? null),
            'fr'                => self::nullableString($w->fr ?? null),
            'ar'                => self::nullableString($w->ar ?? null),

            'center_lat'        => isset($w->center_lat) ? (float) $w->center_lat : null,
            'center_lng'        => isset($w->center_lng) ? (float) $w->center_lng : null,
            'default_radius_km' => isset($w->default_radius_km) ? (float) $w->default_radius_km : null,

            'min_lat'           => isset($w->min_lat) ? (float) $w->min_lat : null,
            'max_lat'           => isset($w->max_lat) ? (float) $w->max_lat : null,
            'min_lng'           => isset($w->min_lng) ? (float) $w->min_lng : null,
            'max_lng'           => isset($w->max_lng) ? (float) $w->max_lng : null,

            'created_at'        => self::iso($w->created_at ?? null),
            'updated_at'        => self::iso($w->updated_at ?? null),
        ];
    }

    /* ------------------------- Bulk helpers -------------------------- */
    public static function list(iterable $items, callable $formatter): array
    {
        $out = [];
        foreach ($items as $item) {
            $out[] = $formatter($item);
        }
        return $out;
    }

    /* ---------------------- internal utilities ----------------------- */
    private static function isModel($x): bool
    {
        return $x instanceof Model;
    }

    private static function relLoaded(Model $m, string $relation): bool
    {
        return method_exists($m, 'relationLoaded') && $m->relationLoaded($relation);
    }

    private static function getProp($obj, string $key, $default = null)
    {
        if (is_array($obj)) {
            return array_key_exists($key, $obj) ? $obj[$key] : $default;
        }
        if (is_object($obj)) {
            return isset($obj->{$key}) ? $obj->{$key} : $default;
        }
        return $default;
    }

    private static function hasProp($obj, string $key): bool
    {
        if (is_array($obj)) return array_key_exists($key, $obj);
        if (is_object($obj)) return isset($obj->{$key});
        return false;
    }

    private static function iso($ts): ?string
    {
        if ($ts === null) return null;
        if ($ts instanceof \DateTimeInterface) return $ts->format(Carbon::ISO8601);
        try {
            return Carbon::parse((string) $ts)->toIso8601String();
        } catch (\Throwable) {
            return (string) $ts;
        }
    }

    private static function jsonToArray($v): array
    {
        if (is_array($v)) return array_values($v);
        if (is_string($v) && $v !== '') {
            $decoded = json_decode($v, true);
            return is_array($decoded) ? array_values($decoded) : [];
        }
        return [];
    }

    private static function nullableString($v): ?string
    {
        if ($v === null) return null;
        $s = (string) $v;
        return $s === '' ? null : $s;
    }

    private static function toFloat($v): float
    {
        return (float) $v;
    }

    private static function toBool($v): bool
    {
        if (is_bool($v)) return $v;
        if (is_numeric($v)) return (int)$v === 1;
        $s = strtolower((string)$v);
        return in_array($s, ['1', 'true', 'yes', 'on'], true);
    }

    private static function toIntOrNull($v): ?int
    {
        if ($v === null) return null;
        if ($v === '') return null;
        return (int) $v;
    }

    /**
     * include/groups normalizer
     * Returns null to mean “include everything”.
     * Accepts: include=true|'all'|'*' / groups='all' to mean ALL.
     */
    private static function normalizeIncludes($include, $groups): ?array
    {
        $isAll = function ($v): bool {
            if ($v === true) return true;
            if (is_string($v)) {
                $s = strtolower(trim($v));
                return $s === 'all' || $s === '*' || $s === 'everything';
            }
            return false;
        };

        if ($isAll($include) || $isAll($groups)) {
            return null; // ALL
        }

        $groupsMap = [
            'coords'     => ['lat', 'lng'],
            'pricing'    => ['is_free', 'price_cents', 'pricing_model'],
            'labels'     => ['name', 'address_line', 'place_hint', 'access_method'],
            'counts'     => ['reviews_count', 'photos_count'],
            'meta'       => ['created_at', 'updated_at'],
            'relations'  => ['category', 'wilaya', 'owner', 'photos', 'open_hours', 'is_favorite'],
        ];

        $list = [];

        if (is_string($include) && $include !== '') {
            $list = array_map('trim', explode(',', $include));
        } elseif (is_array($include) && !empty($include)) {
            $list = array_values(array_unique(array_map('strval', $include)));
        }

        if (is_string($groups) && $groups !== '') {
            $groups = array_map('trim', explode(',', $groups));
        } elseif (!is_array($groups)) {
            $groups = [];
        }

        foreach ($groups as $g) {
            if (isset($groupsMap[$g])) {
                $list = array_merge($list, $groupsMap[$g]);
            }
        }

        $list = array_values(array_unique(array_filter($list)));
        return empty($list) ? null : $list; // empty => ALL
    }


    /** ------------------------
    * Taxnonomy Info
    * ------------------------- */
    public static function buildAccessMethods()
    {
        // Must match your toilets.access_method enum
        return [
            ['code' => 'public', 'icon' => 'door-open', 'en' => 'Public',           'fr' => 'Public',            'ar' => 'عمومي'],
            ['code' => 'code',   'icon' => 'dialpad',     'en' => 'Door code',       'fr' => 'Code porte',        'ar' => 'رمز الباب'],
            ['code' => 'staff',  'icon' => 'account',    'en' => 'Ask staff',       'fr' => 'Demander au staff', 'ar' => 'اسأل الموظفين'],
            ['code' => 'key',    'icon' => 'key',        'en' => 'Key required',    'fr' => 'Clé requise',       'ar' => 'مفتاح مطلوب'],
            ['code' => 'app',    'icon' => 'cellphone',  'en' => 'App controlled',  'fr' => 'Par application',   'ar' => 'عن طريق التطبيق'],
        ];
    }

    public static function getAmenities()
    {
        // Stable codes; safe to add more later without breaking clients
        return [
            // --- Basics you already had ---
            ['code' => 'paper',        'icon' => 'paper-roll',        'en' => 'Toilet paper',         'fr' => 'Papier toilette',             'ar' => 'ورق المرحاض'],
            ['code' => 'soap',         'icon' => 'hand-wash',               'en' => 'Soap',                 'fr' => 'Savon',                       'ar' => 'صابون'],
            ['code' => 'water',        'icon' => 'water',              'en' => 'Water',                'fr' => 'Eau',                         'ar' => 'ماء'],
            // ['code' => 'bidet',        'icon' => 'bidet',              'en' => 'Bidet / Shattaf',      'fr' => 'Bidet / Douchette',           'ar' => 'شطّاف'],
            ['code' => 'handwash',     'icon' => 'hand-water',         'en' => 'Hand wash',            'fr' => 'Lavage des mains',            'ar' => 'غسل اليدين'],
            ['code' => 'dryers',       'icon' => 'hair-dryer',         'en' => 'Hair dryers',          'fr' => 'Sèche-cheveux',                 'ar' => 'مجفف شعر'],
            ['code' => 'wheelchair',   'icon' => 'wheelchair',         'en' => 'Wheelchair access',    'fr' => 'Accès PMR',                   'ar' => 'ولوج للكراسي'],
            ['code' => 'baby_change',  'icon' => 'baby-bottle',        'en' => 'Baby changing',        'fr' => 'Table à langer',              'ar' => 'تغيير الرضّع'],

            // --- Frequently requested extras ---
            ['code' => 'wifi',         'icon' => 'wifi',               'en' => 'Wi-Fi',                'fr' => 'Wi-Fi',                       'ar' => 'واي فاي'],
            ['code' => 'outlets',      'icon' => 'power-socket',       'en' => 'Power outlets',        'fr' => 'Prises électriques',          'ar' => 'مقابس كهرباء'],
            ['code' => 'mirror',       'icon' => 'mirror',             'en' => 'Mirror',               'fr' => 'Miroir',                      'ar' => 'مرآة'],
            // ['code' => 'sanitary_bin', 'icon' => 'trash-can-outline',  'en' => 'Sanitary bin',         'fr' => 'Poubelle hygiénique',         'ar' => 'سلة نفايات صحية'],
            // ['code' => 'paper_towels', 'icon' => 'paper-roll-outline', 'en' => 'Paper towels',         'fr' => 'Essuie-mains papier',         'ar' => 'مناديل ورقية'],
            // ['code' => 'sanitizer',    'icon' => 'hand-wash',     'en' => 'Hand sanitizer',       'fr' => 'Gel hydroalcoolique',         'ar' => 'معقم يدين'],
            // ['code' => 'air_freshener','icon' => 'spray',              'en' => 'Air freshener',        'fr' => 'Désodorisant',                'ar' => 'معطّر هواء'],
            // ['code' => 'urinal',       'icon' => 'human-male',         'en' => 'Urinals',              'fr' => 'Urinoirs',                    'ar' => 'مَبُولَة'],
            // ['code' => 'private_stalls','icon'=> 'door',               'en' => 'Private stalls',       'fr' => 'Cabines privées',             'ar' => 'حجرات خاصة'],
            ['code' => 'western_wc',   'icon' => 'toilet',             'en' => 'Western toilet',       'fr' => 'WC à l’anglaise',             'ar' => 'مرحاض غربي (جلوس)'],
            ['code' => 'squat_wc',     'icon' => 'toilet',             'en' => 'Squat toilet',         'fr' => 'WC à la turque',              'ar' => 'مرحاض عربي (قرفصة)'],
            ['code' => 'gender_neutral','icon'=> 'human-male-female',  'en' => 'Gender-neutral',       'fr' => 'Non genré (mixte)',           'ar' => 'مراحيض للجميع'],
            // ['code' => 'family_room',  'icon' => 'human-male-female-child','en'=>'Family room',       'fr' => 'Espace famille',              'ar' => 'غرفة عائلية'],
            ['code' => 'shower',       'icon' => 'shower',             'en' => 'Shower',               'fr' => 'Douche',                      'ar' => 'دش'],
            ['code' => 'hot_water',    'icon' => 'coolant-temperature','en' => 'Hot water',            'fr' => 'Eau chaude',                  'ar' => 'ماء ساخن'],
            // ['code' => 'ac',           'icon' => 'air-conditioner',    'en' => 'Air conditioning',     'fr' => 'Climatisation',               'ar' => 'تكييف'],
            // ['code' => 'heating',      'icon' => 'radiator',           'en' => 'Heating',              'fr' => 'Chauffage',                   'ar' => 'تدفئة'],
            ['code' => 'braille',      'icon' => 'braille',            'en' => 'Braille signage',      'fr' => 'Signalétique braille',        'ar' => 'لافتات برايل'],
            ['code' => 'wide_door',    'icon' => 'door-sliding',       'en' => 'Wide door',            'fr' => 'Porte large',                 'ar' => 'باب واسع'],
            ['code' => 'grab_bars',    'icon' => 'hand-back-right',    'en' => 'Grab bars',            'fr' => 'Barres d’appui',              'ar' => 'مسكات دعم'],
        ];
    }

    public static function getRules()
    {
        return [
            // Existing
            ['code' => 'no_smoking',         'icon' => 'smoke-off',        'en' => 'No smoking',              'fr' => 'Interdit de fumer',          'ar' => 'ممنوع التدخين'],
            ['code' => 'for_customers_only', 'icon' => 'account-card',     'en' => 'Customers only',          'fr' => 'Clients uniquement',         'ar' => 'للزبائن فقط'],
            ['code' => 'no_pets',            'icon' => 'dog-off',          'en' => 'No pets',                 'fr' => 'Animaux interdits',          'ar' => 'ممنوع الحيوانات'],
            ['code' => 'no_photos',          'icon' => 'camera-off',       'en' => 'No photography',          'fr' => 'Photos interdites',          'ar' => 'ممنوع التصوير'],

            // Useful additions
            // ['code' => 'purchase_required',  'icon' => 'cash',             'en' => 'Purchase required',       'fr' => 'Achat requis',               'ar' => 'يجب شراء منتج'],
            // ['code' => 'time_limited',       'icon' => 'timer-outline',    'en' => 'Time-limited use',        'fr' => 'Usage à durée limitée',      'ar' => 'استخدام بمدة محدودة'],
            // ['code' => 'paid_entry',         'icon' => 'ticket-outline',   'en' => 'Paid entry',              'fr' => 'Accès payant',               'ar' => 'دخول مدفوع'],
            ['code' => 'cctv',               'icon' => 'cctv',             'en' => 'CCTV in operation',       'fr' => 'Sous vidéosurveillance',     'ar' => 'مراقَب بالكاميرات'],
            ['code' => 'no_vaping',          'icon' => 'smoking-off',      'en' => 'No vaping',               'fr' => 'Vapotage interdit',          'ar' => 'ممنوع السجائر الإلكترونية'],
            // ['code' => 'no_loitering',       'icon' => 'account-off',      'en' => 'No loitering',            'fr' => 'Défense de traîner',         'ar' => 'ممنوع التسكع'],
            ['code' => 'no_alcohol_drugs',   'icon' => 'glass-cocktail-off','en'=> 'No alcohol/drugs',        'fr' => 'Alcool/drogues interdits',   'ar' => 'ممنوع الكحول والمخدرات'],
            ['code' => 'keep_clean',         'icon' => 'broom',            'en' => 'Keep it clean',           'fr' => 'Merci de laisser propre',    'ar' => 'يُرجى إبقاء المكان نظيفًا'],
            ['code' => 'respect_queue',      'icon' => 'format-list-numbered','en'=>'Respect the queue',     'fr' => 'Respectez la file',          'ar' => 'احترام الطابور'],
        ];
    }

    protected static function buildCodeMetaList(?array $codes, array $allRows): array
    {
        if (!$codes || !is_array($codes)) return [];

        // Index canonical rows by code for O(1) lookups
        $dict = [];
        foreach ($allRows as $r) {
            if (!isset($r['code'])) continue;
            $dict[$r['code']] = $r;
        }

        $out = [];
        foreach ($codes as $code) {
            $row = $dict[$code] ?? null;

            // If code not found in canonical list, still return a minimal entry
            if (!$row) {
                $out[] = [
                    'code'  => (string)$code,
                    'icon'  => null,
                    'en'    => ucfirst(str_replace('_', ' ', (string)$code)),
                    'fr'    => ucfirst(str_replace('_', ' ', (string)$code)),
                    'ar'    => (string)$code, // or keep null if you prefer
                    'label' => ucfirst(str_replace('_', ' ', (string)$code)),
                ];
                continue;
            }

            // Choose preferred label, fallback to EN
            $label = $row['en'] ?? null;

            $out[] = [
                'code'  => $row['code'],
                'icon'  => $row['icon'] ?? null,
                'en'    => $row['en'] ?? null,
                'fr'    => $row['fr'] ?? null,
                'ar'    => $row['ar'] ?? null,
                'label' => $label,
            ];
        }

        return $out;
    }

}
