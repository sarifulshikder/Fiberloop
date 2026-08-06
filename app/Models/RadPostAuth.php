<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RadPostAuth extends Model
{
    use HasFactory;

    protected $connection = 'radius';
    protected $table = 'radpostauth';

    protected $fillable = [
        'username',
        'pass',
        'reply',
        'authdate',
        'class',
    ];

    protected $casts = [
        'authdate' => 'datetime',
    ];
}
