<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ToiletSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'toilet_id', 'user_id', 'started_at', 'ended_at',
        'charge_cents', 'start_method', 'end_method'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function toilet() { return $this->belongsTo(Toilet::class); }
    public function user()   { return $this->belongsTo(User::class); }
}
