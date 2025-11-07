<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ToiletCategory extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'icon', 'en', 'fr', 'ar'];

    public function toilets()
    {
        return $this->hasMany(Toilet::class);
    }
}
