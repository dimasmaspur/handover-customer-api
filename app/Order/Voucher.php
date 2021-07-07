<?php

namespace App\Order;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use SoftDeletes;

    protected $table = 'tm_voucher';
    protected $guarded = [];

    /* protected $dates = [
        'start_date', 'end_date', 'created_at', 'updated_at'
    ]; */

    /* protected $casts = [
        'start_date' => 'datetime:Y-m-d H:i:s',
        'end_date' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ]; */

    public function scopeActive($query)
    {
        return $query->where('status', 1)
            ->where('start_date', '<=', Carbon::now())
            ->where('end_date', '>=', Carbon::now());
    }
    
    public function scopePublish($query)
    {
        return $query->where('publish', 1)
            ->where('start_date', '<=', Carbon::now())
            ->where('end_date', '>=', Carbon::now());
    }
}
