<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ToiletOpenHour extends Model
{
    use HasFactory;

    protected $fillable = ['toilet_id', 'day_of_week', 'opens_at', 'closes_at', 'sequence'];

    public function toilet() { return $this->belongsTo(Toilet::class); }
}
