<?php

namespace App;

use App\Member\Address;
use App\Member\Favorite;
use App\Member\Point;
use App\Member\PointHistory;
use App\Member\Tabungan;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Lumen\Auth\Authorizable;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

    protected $table = 'tm_members';
    protected $guarded = [];
    protected $hidden = ['member_code', 'member_parent_id', 'log', 'modified'];

    protected $dates = ['last_login', 'token_expired'];
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'last_login' => 'datetime:Y-m-d H:i:s',
        'token_expired' => 'datetime:Y-m-d H:i:s',
    ];

    protected $appends = ['login'];

    public function addresses()
    {
        return $this->hasMany(Address::class, 'member_id');
    }
    
    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'member_id');
    }

    public function tabungans()
    {
        return $this->hasMany(Tabungan::class, 'member_id');
    }

    public function getLoginAttribute()
    {
        return 1;
    }

    public function scopeWithTabungan($query)
    {
        $tabungan = Tabungan::select(DB::raw("SUM(CASE WHEN amount_type = 1 THEN amount ELSE 0 END) - SUM(CASE WHEN amount_type = 2 THEN amount ELSE 0 END) AS tabungan"))
            ->whereColumn('member_id', 'tm_members.id')
            ->groupBy('member_id')
            ->limit(1)
            ->getQuery();

        return $query->selectSub($tabungan, 'tabungan');
    }

    public function primaryAddress()
    {
        return $this->belongsTo(Address::class, 'primary_address_id');
    }

    public function scopeWithPrimaryAddress($query)
    {
        $address = Address::select('id')
            ->whereColumn('member_id', 'tm_member_addresses.id')
            ->active()
            ->where('is_primary', 1)
            ->orderBy('is_primary', 'desc')
            ->limit(1)
            ->getQuery();

        return $query->selectSub($address, 'primary_address_id')
            ->with('primaryAddress');
    }

    public function myPoint()
    {
        return $this->hasOne(Point::class, 'member_id');
    }

    public function points()
    {
        return $this->hasMany(PointHistory::class, 'member_id');
    }

    public function currentPoint()
    {
        $point = $this->points()
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $point ? $point->current_point : 0;
    }
}
