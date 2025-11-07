<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ToiletReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'toilet_id', 'user_id', 'rating', 'text', 'cleanliness', 'smell', 'stock'
    ];

    public function toilet() { return $this->belongsTo(Toilet::class); }
    public function user()   { return $this->belongsTo(User::class); }
}
