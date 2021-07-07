<?php

namespace App\Order;

use Illuminate\Database\Eloquent\Model;

class OrderHistory extends Model
{
    const CREATED_AT = 'created';
    const UPDATED_AT = 'modified';

    protected $table = 'tr_order_histories';
    protected $guarded = [];
    protected $casts = [
        'created' => 'datetime:Y-m-d H:i:s',
        'modified' => 'datetime:Y-m-d H:i:s',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
