<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RadGroupReply extends Model
{
    use HasFactory;

    protected $connection = 'radius';
    protected $table = 'radgroupreply';

    protected $fillable = [
        'groupname',
        'attribute',
        'op',
        'value',
    ];
}
