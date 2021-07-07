<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ongkir extends Model
{   
    protected $table = 'tm_delivery_fees';
    protected $guarded = [];

    // public function scopeActive($query)
    // {
    //     return $query->where('status', 1)
    //         ->where('publish', 1)
    //         ->whereDate('start_date', '<=', date('Y-m-d'))
    //         ->whereDate('end_date', '>=', date('Y-m-d'));
    // }
}
