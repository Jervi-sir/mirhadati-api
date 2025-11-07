<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'code',
    ];

    public const USER  = 'user';
    public const HOST  = 'host';
    public const ADMIN = 'admin';

}
