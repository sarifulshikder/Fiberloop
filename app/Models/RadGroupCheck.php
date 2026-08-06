<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RadGroupCheck extends Model
{
    use HasFactory;

    protected $connection = 'radius';
    protected $table = 'radgroupcheck';

    protected $fillable = [
        'groupname',
        'attribute',
        'op',
        'value',
    ];
}
