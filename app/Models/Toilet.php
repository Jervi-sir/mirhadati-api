<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Toilet extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'toilet_category_id',
        'wilaya_id',
        'name',
        'description',
        'phone_numbers',
        'lat',
        'lng',
        'address_line',
        'place_hint',
        'access_method',
        'capacity',
        'is_unisex',
        'amenities',
        'rules',
        'is_free',
        'price_cents',
        'pricing_model',
        'status',
        'avg_rating',
        'reviews_count',
        'photos_count',
    ];

    protected $casts = [
        'phone_numbers' => 'array',
        'amenities' => 'array',
        'rules' => 'array',
        'is_free' => 'boolean',
        'avg_rating' => 'decimal:2',
        'lat' => 'decimal:6',
        'lng' => 'decimal:6',
    ];

    // Relations
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
    public function category()
    {
        return $this->belongsTo(ToiletCategory::class, 'toilet_category_id');
    }

    public function wilaya()
    {
        return $this->belongsTo(Wilaya::class);
    }

    public function photos()
    {
        return $this->hasMany(ToiletPhoto::class);
    }
    public function openHours()
    {
        return $this->hasMany(ToiletOpenHour::class);
    }
    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }
    public function sessions()
    {
        return $this->hasMany(ToiletSession::class);
    }
    public function reviews()
    {
        return $this->hasMany(ToiletReview::class);
    }
    public function reports()
    {
        return $this->hasMany(ToiletReport::class);
    }
}
