<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nas extends Model
{
    use HasFactory;

    protected $connection = 'radius';
    protected $table = 'nas';

    protected $fillable = [
        'nasname',
        'shortname',
        'type',
        'ports',
        'secret',
        'server',
        'community',
        'description',
    ];

    protected $casts = [
        'secret' => 'encrypted',
        'ports' => 'integer',
    ];
}
