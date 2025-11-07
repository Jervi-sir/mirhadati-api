<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ToiletPhoto extends Model
{
    use HasFactory;

    protected $fillable = ['toilet_id', 'url', 'is_cover'];
    protected $casts = ['is_cover' => 'boolean'];

    public function toilet() { return $this->belongsTo(Toilet::class); }
}
