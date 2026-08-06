<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RadUserGroup extends Model
{
    use HasFactory;

    protected $connection = 'radius';
    protected $table = 'radusergroup';

    protected $fillable = [
        'username',
        'groupname',
        'priority',
    ];

    protected $casts = [
        'priority' => 'integer',
    ];
}
