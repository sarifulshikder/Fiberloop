<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RadiusUser extends Model
{
    use HasFactory;

    protected $connection = 'radius';
    protected $table = 'radcheck';

    protected $fillable = [
        'username',
        'attribute',
        'op',
        'value',
    ];

    public function replies(): HasMany
    {
        return $this->hasMany(RadReply::class, 'username', 'username');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(RadCheck::class, 'username', 'username');
    }

    public function activeSession(): HasOne
    {
        return $this->hasOne(RadAcct::class, 'username', 'username')
            ->whereNull('acctstoptime')
            ->latestOfMany('acctstarttime');
    }

    public function radiusCustomer(): HasOne
    {
        return $this->hasOne(RadiusCustomer::class, 'radius_username', 'username');
    }
}
