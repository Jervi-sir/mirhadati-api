<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wilaya extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'number', 'en', 'fr', 'ar',
        'center_lat', 'center_lng', 'default_radius_km', 
        'min_lat', 'max_lat', 'min_lng', 'max_lng',     
    ];

    protected $casts = [
        'center_lat' => 'decimal:6',
        'center_lng' => 'decimal:6',
        'min_lat' => 'decimal:6',
        'max_lat' => 'decimal:6',
        'min_lng' => 'decimal:6',
        'max_lng' => 'decimal:6',
    ];


    public function toilets()
    {
        return $this->hasMany(Toilet::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
