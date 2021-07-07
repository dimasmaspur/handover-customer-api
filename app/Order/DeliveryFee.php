<?php

namespace App\Order;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryFee extends Model
{
    use SoftDeletes;
    
    protected $table = 'tm_delivery_fees';

    public function scopeActive($query)
    {
        $now = date('Y-m-d H:i:s');

        return $query->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            // ->where('status', 1)
            // ->orderBy('status', 'desc')
            ->orderBy('min_order', 'desc')
            ->orderBy('end_date');
    }
}
