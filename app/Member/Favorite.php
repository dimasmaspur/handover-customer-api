<?php

namespace App\Member;

use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    protected $table = 'tm_member_favorites';
    protected $guarded = [];
    // public $timestamps = false;

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
